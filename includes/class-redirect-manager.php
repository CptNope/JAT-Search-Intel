<?php
namespace Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Redirect Manager — 301/302/307/410 redirects with exact, prefix, and regex matching.
 *
 * @since 2.0.0
 */
class Redirect_Manager {

	/**
	 * Custom DB table name (without prefix).
	 */
	private const TABLE_REDIRECTS = 'search_intel_redirects';

	/**
	 * Custom DB table name for 404 log (without prefix).
	 */
	private const TABLE_404_LOG = 'search_intel_404_log';

	/**
	 * Valid HTTP status codes for redirects.
	 *
	 * @var array<int,string>
	 */
	public const STATUS_CODES = [
		301 => '301 Permanent',
		302 => '302 Temporary',
		307 => '307 Temporary (strict)',
		410 => '410 Gone',
	];

	/**
	 * Valid match types.
	 *
	 * @var array<string,string>
	 */
	public const MATCH_TYPES = [
		'exact'  => 'Exact Match',
		'prefix' => 'Prefix Match',
		'regex'  => 'Regex Match',
	];

	/** @var array */
	private $settings;

	/**
	 * Constructor — hook into WordPress template_redirect for front-end interception.
	 *
	 * @param array $settings Plugin settings.
	 */
	public function __construct( array $settings ) {
		$this->settings = $settings;

		if ( ! empty( $settings['enable_redirects'] ) ) {
			add_action( 'template_redirect', [ $this, 'maybe_redirect' ], 1 );
			add_action( 'template_redirect', [ $this, 'log_404' ], 99 );
		}

		if ( is_admin() ) {
			add_action( 'admin_menu', [ $this, 'register_admin_page' ] );
			add_action( 'wp_ajax_si_save_redirect', [ $this, 'ajax_save_redirect' ] );
			add_action( 'wp_ajax_si_delete_redirect', [ $this, 'ajax_delete_redirect' ] );
			add_action( 'wp_ajax_si_export_redirects', [ $this, 'ajax_export_csv' ] );
			add_action( 'wp_ajax_si_import_redirects', [ $this, 'ajax_import_csv' ] );
			add_action( 'wp_ajax_si_clear_404_log', [ $this, 'ajax_clear_404_log' ] );
		}
	}

	/**
	 * Create custom database tables on plugin activation.
	 */
	public static function create_tables(): void {
		global $wpdb;

		$charset = $wpdb->get_charset_collate();

		$redirects_table = $wpdb->prefix . self::TABLE_REDIRECTS;
		$log_table       = $wpdb->prefix . self::TABLE_404_LOG;

		$sql_redirects = "CREATE TABLE {$redirects_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			source_url varchar(2048) NOT NULL,
			target_url varchar(2048) NOT NULL DEFAULT '',
			status_code smallint(3) NOT NULL DEFAULT 301,
			match_type varchar(10) NOT NULL DEFAULT 'exact',
			hit_count bigint(20) unsigned NOT NULL DEFAULT 0,
			last_hit datetime DEFAULT NULL,
			enabled tinyint(1) NOT NULL DEFAULT 1,
			note varchar(255) NOT NULL DEFAULT '',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY source_url (source_url(191)),
			KEY match_type (match_type),
			KEY enabled (enabled)
		) {$charset};";

		$sql_404_log = "CREATE TABLE {$log_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			url varchar(2048) NOT NULL,
			referrer varchar(2048) NOT NULL DEFAULT '',
			user_agent varchar(512) NOT NULL DEFAULT '',
			ip_hash varchar(64) NOT NULL DEFAULT '',
			hit_count bigint(20) unsigned NOT NULL DEFAULT 1,
			first_hit datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			last_hit datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY url (url(191)),
			KEY last_hit (last_hit)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql_redirects );
		dbDelta( $sql_404_log );
	}

	/**
	 * Intercept the current request and apply a matching redirect rule.
	 */
	public function maybe_redirect(): void {
		if ( is_admin() ) {
			return;
		}

		$request_path = $this->get_request_path();
		$redirect     = $this->find_matching_redirect( $request_path );

		if ( ! $redirect ) {
			return;
		}

		$this->increment_hit_count( (int) $redirect->id );

		// 410 Gone — no redirect target, just set status and exit.
		if ( 410 === (int) $redirect->status_code ) {
			status_header( 410 );
			nocache_headers();
			echo '<h1>410 Gone</h1><p>This resource is no longer available.</p>';
			exit;
		}

		$target = $redirect->target_url;

		// For regex matches, apply backreferences.
		if ( 'regex' === $redirect->match_type ) {
			$target = preg_replace( '#' . $redirect->source_url . '#', $redirect->target_url, $request_path );
		}

		// Loop detection — don't redirect to same URL.
		$target_path = wp_parse_url( $target, PHP_URL_PATH ) ?: $target;
		if ( $target_path === $request_path ) {
			return;
		}

		wp_redirect( $target, (int) $redirect->status_code );
		exit;
	}

	/**
	 * Log 404 errors for auto-suggest redirect targets.
	 */
	public function log_404(): void {
		if ( ! is_404() ) {
			return;
		}

		global $wpdb;

		$table = $wpdb->prefix . self::TABLE_404_LOG;
		$url   = $this->get_request_path();

		// Hash the IP for privacy (don't store raw IPs).
		$ip_hash    = hash( 'sha256', $this->get_client_ip() . wp_salt() );
		$referrer   = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

		// Upsert — increment hit_count if URL already logged.
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE url = %s LIMIT 1",
				$url
			)
		);

		if ( $existing ) {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table} SET hit_count = hit_count + 1, last_hit = NOW(), referrer = %s, user_agent = %s, ip_hash = %s WHERE id = %d",
					$referrer,
					$user_agent,
					$ip_hash,
					$existing
				)
			);
		} else {
			$wpdb->insert(
				$table,
				[
					'url'        => $url,
					'referrer'   => $referrer,
					'user_agent' => $user_agent,
					'ip_hash'    => $ip_hash,
				],
				[ '%s', '%s', '%s', '%s' ]
			);
		}
	}

	/**
	 * Find a matching redirect rule for a given request path.
	 *
	 * @param string $request_path The incoming request path.
	 * @return object|null The matching redirect row, or null.
	 */
	public function find_matching_redirect( string $request_path ) {
		global $wpdb;

		$table = $wpdb->prefix . self::TABLE_REDIRECTS;

		// 1. Exact match (fastest).
		$exact = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE match_type = 'exact' AND source_url = %s AND enabled = 1 LIMIT 1",
				$request_path
			)
		);

		if ( $exact ) {
			return $exact;
		}

		// 2. Prefix match — find longest prefix that matches.
		$prefixes = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE match_type = 'prefix' AND enabled = 1 AND %s LIKE CONCAT(source_url, '%%') ORDER BY CHAR_LENGTH(source_url) DESC LIMIT 1",
				$request_path
			)
		);

		if ( ! empty( $prefixes ) ) {
			return $prefixes[0];
		}

		// 3. Regex match — test each pattern.
		$regexes = $wpdb->get_results(
			"SELECT * FROM {$table} WHERE match_type = 'regex' AND enabled = 1"
		);

		foreach ( $regexes as $rule ) {
			if ( @preg_match( '#' . $rule->source_url . '#', $request_path ) ) {
				return $rule;
			}
		}

		return null;
	}

	/**
	 * Get a single redirect by ID.
	 *
	 * @param int $id Redirect ID.
	 * @return object|null
	 */
	public function get_redirect( int $id ) {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_REDIRECTS;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
	}

	/**
	 * Get paginated list of redirects.
	 *
	 * @param int    $page    Page number (1-indexed).
	 * @param int    $per_page Items per page.
	 * @param string $search  Optional search string.
	 * @param string $orderby Column to order by.
	 * @param string $order   ASC or DESC.
	 * @return array{items: array, total: int}
	 */
	public function get_redirects( int $page = 1, int $per_page = 25, string $search = '', string $orderby = 'id', string $order = 'DESC' ): array {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_REDIRECTS;

		$allowed_columns = [ 'id', 'source_url', 'target_url', 'status_code', 'hit_count', 'last_hit', 'created_at' ];
		if ( ! in_array( $orderby, $allowed_columns, true ) ) {
			$orderby = 'id';
		}
		$order = strtoupper( $order ) === 'ASC' ? 'ASC' : 'DESC';

		$where = '';
		if ( $search ) {
			$like  = '%' . $wpdb->esc_like( $search ) . '%';
			$where = $wpdb->prepare( " WHERE source_url LIKE %s OR target_url LIKE %s OR note LIKE %s", $like, $like, $like );
		}

		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}{$where}" );

		$offset = ( $page - 1 ) * $per_page;
		$items  = $wpdb->get_results(
			"SELECT * FROM {$table}{$where} ORDER BY {$orderby} {$order} LIMIT {$per_page} OFFSET {$offset}"
		);

		return [
			'items' => $items ?: [],
			'total' => $total,
		];
	}

	/**
	 * Save (insert or update) a redirect rule.
	 *
	 * @param array $data Redirect data.
	 * @return int|false The redirect ID on success, false on failure.
	 */
	public function save_redirect( array $data ) {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_REDIRECTS;

		$source = trim( $data['source_url'] ?? '' );
		$target = trim( $data['target_url'] ?? '' );

		if ( empty( $source ) ) {
			return false;
		}

		$status_code = (int) ( $data['status_code'] ?? 301 );
		if ( ! isset( self::STATUS_CODES[ $status_code ] ) ) {
			$status_code = 301;
		}

		$match_type = $data['match_type'] ?? 'exact';
		if ( ! isset( self::MATCH_TYPES[ $match_type ] ) ) {
			$match_type = 'exact';
		}

		// Validate regex pattern.
		if ( 'regex' === $match_type && @preg_match( '#' . $source . '#', '' ) === false ) {
			return false;
		}

		// Loop detection for non-regex.
		if ( 'exact' === $match_type && $source === $target ) {
			return false;
		}

		$row = [
			'source_url'  => $source,
			'target_url'  => $target,
			'status_code' => $status_code,
			'match_type'  => $match_type,
			'enabled'     => ! empty( $data['enabled'] ) ? 1 : 0,
			'note'        => sanitize_text_field( $data['note'] ?? '' ),
		];

		$format = [ '%s', '%s', '%d', '%s', '%d', '%s' ];

		$id = (int) ( $data['id'] ?? 0 );

		if ( $id > 0 ) {
			$wpdb->update( $table, $row, [ 'id' => $id ], $format, [ '%d' ] );
			return $id;
		}

		$inserted = $wpdb->insert( $table, $row, $format );
		return $inserted ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Delete a redirect by ID.
	 *
	 * @param int $id Redirect ID.
	 * @return bool
	 */
	public function delete_redirect( int $id ): bool {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_REDIRECTS;
		return (bool) $wpdb->delete( $table, [ 'id' => $id ], [ '%d' ] );
	}

	/**
	 * Get paginated 404 log entries.
	 *
	 * @param int $page     Page number.
	 * @param int $per_page Items per page.
	 * @return array{items: array, total: int}
	 */
	public function get_404_log( int $page = 1, int $per_page = 25 ): array {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_404_LOG;

		$total  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		$offset = ( $page - 1 ) * $per_page;
		$items  = $wpdb->get_results(
			"SELECT * FROM {$table} ORDER BY hit_count DESC, last_hit DESC LIMIT {$per_page} OFFSET {$offset}"
		);

		return [
			'items' => $items ?: [],
			'total' => $total,
		];
	}

	/**
	 * Clear all entries from the 404 log.
	 */
	public function clear_404_log(): void {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_404_LOG;
		$wpdb->query( "TRUNCATE TABLE {$table}" );
	}

	/**
	 * Export all redirects as CSV string.
	 *
	 * @return string CSV content.
	 */
	public function export_csv(): string {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_REDIRECTS;
		$rows  = $wpdb->get_results( "SELECT source_url, target_url, status_code, match_type, enabled, note FROM {$table} ORDER BY id ASC", ARRAY_A );

		$output = fopen( 'php://temp', 'r+' );
		fputcsv( $output, [ 'source_url', 'target_url', 'status_code', 'match_type', 'enabled', 'note' ] );

		foreach ( $rows as $row ) {
			fputcsv( $output, $row );
		}

		rewind( $output );
		$csv = stream_get_contents( $output );
		fclose( $output );

		return $csv;
	}

	/**
	 * Import redirects from CSV string.
	 *
	 * @param string $csv CSV content.
	 * @return array{imported: int, skipped: int, errors: int}
	 */
	public function import_csv( string $csv ): array {
		$result = [ 'imported' => 0, 'skipped' => 0, 'errors' => 0 ];

		$lines = explode( "\n", trim( $csv ) );
		if ( count( $lines ) < 2 ) {
			return $result;
		}

		// Skip header row.
		array_shift( $lines );

		foreach ( $lines as $line ) {
			$cols = str_getcsv( $line );
			if ( count( $cols ) < 2 ) {
				$result['skipped']++;
				continue;
			}

			$data = [
				'source_url'  => $cols[0] ?? '',
				'target_url'  => $cols[1] ?? '',
				'status_code' => (int) ( $cols[2] ?? 301 ),
				'match_type'  => $cols[3] ?? 'exact',
				'enabled'     => $cols[4] ?? '1',
				'note'        => $cols[5] ?? '',
			];

			$saved = $this->save_redirect( $data );
			if ( $saved ) {
				$result['imported']++;
			} else {
				$result['errors']++;
			}
		}

		return $result;
	}

	/**
	 * Detect slug changes on post save and auto-create redirect.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @param \WP_Post $post_before Previous post object.
	 */
	public function detect_slug_change( int $post_id, $post, $post_before ): void {
		if ( ! $post_before || $post->post_name === $post_before->post_name ) {
			return;
		}

		if ( 'publish' !== $post->post_status ) {
			return;
		}

		$old_url = get_permalink( $post_before );
		$new_url = get_permalink( $post );

		if ( ! $old_url || ! $new_url || $old_url === $new_url ) {
			return;
		}

		$old_path = wp_parse_url( $old_url, PHP_URL_PATH );
		$new_path = wp_parse_url( $new_url, PHP_URL_PATH );

		if ( ! $old_path || ! $new_path ) {
			return;
		}

		$this->save_redirect( [
			'source_url'  => $old_path,
			'target_url'  => $new_path,
			'status_code' => 301,
			'match_type'  => 'exact',
			'enabled'     => 1,
			'note'        => sprintf( 'Auto: slug change on post #%d', $post_id ),
		] );
	}

	/**
	 * Register admin menu page.
	 */
	public function register_admin_page(): void {
		add_submenu_page(
			'search-intel',
			__( 'Redirects', 'search-intel' ),
			__( 'Redirects', 'search-intel' ),
			'manage_options',
			'search-intel-redirects',
			[ $this, 'render_admin_page' ]
		);
	}

	/**
	 * Render the redirects admin page.
	 */
	public function render_admin_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$tab = sanitize_text_field( $_GET['tab'] ?? 'redirects' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Search Intel — Redirects', 'search-intel' ); ?></h1>

			<nav class="nav-tab-wrapper">
				<a href="?page=search-intel-redirects&tab=redirects"
				   class="nav-tab <?php echo 'redirects' === $tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Redirect Rules', 'search-intel' ); ?>
				</a>
				<a href="?page=search-intel-redirects&tab=404log"
				   class="nav-tab <?php echo '404log' === $tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( '404 Log', 'search-intel' ); ?>
				</a>
			</nav>

			<?php
			if ( '404log' === $tab ) {
				$this->render_404_log_tab();
			} else {
				$this->render_redirects_tab();
			}
			?>
		</div>
		<?php
	}

	/**
	 * Render the Redirect Rules tab.
	 */
	private function render_redirects_tab(): void {
		$page     = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
		$search   = sanitize_text_field( $_GET['s'] ?? '' );
		$orderby  = sanitize_key( $_GET['orderby'] ?? 'id' );
		$order    = sanitize_key( $_GET['order'] ?? 'DESC' );
		$result   = $this->get_redirects( $page, 25, $search, $orderby, $order );
		$total    = $result['total'];
		$items    = $result['items'];
		$pages    = (int) ceil( $total / 25 );

		?>
		<div class="si-redirects-wrap" style="margin-top: 20px;">
			<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
				<form method="get" style="display:flex; gap:8px; align-items:center;">
					<input type="hidden" name="page" value="search-intel-redirects" />
					<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>"
						   placeholder="<?php esc_attr_e( 'Search redirects…', 'search-intel' ); ?>" class="regular-text" />
					<button type="submit" class="button"><?php esc_html_e( 'Search', 'search-intel' ); ?></button>
				</form>
				<div style="display:flex; gap:8px;">
					<button type="button" class="button" id="si-import-csv"><?php esc_html_e( 'Import CSV', 'search-intel' ); ?></button>
					<button type="button" class="button" id="si-export-csv"><?php esc_html_e( 'Export CSV', 'search-intel' ); ?></button>
					<button type="button" class="button button-primary" id="si-add-redirect"><?php esc_html_e( 'Add Redirect', 'search-intel' ); ?></button>
				</div>
			</div>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th style="width:30%"><?php esc_html_e( 'Source URL', 'search-intel' ); ?></th>
						<th style="width:25%"><?php esc_html_e( 'Target URL', 'search-intel' ); ?></th>
						<th style="width:8%"><?php esc_html_e( 'Code', 'search-intel' ); ?></th>
						<th style="width:8%"><?php esc_html_e( 'Type', 'search-intel' ); ?></th>
						<th style="width:7%"><?php esc_html_e( 'Hits', 'search-intel' ); ?></th>
						<th style="width:10%"><?php esc_html_e( 'Last Hit', 'search-intel' ); ?></th>
						<th style="width:12%"><?php esc_html_e( 'Actions', 'search-intel' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( empty( $items ) ) : ?>
					<tr><td colspan="7"><?php esc_html_e( 'No redirect rules found.', 'search-intel' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $items as $item ) : ?>
						<tr data-id="<?php echo esc_attr( $item->id ); ?>">
							<td><code><?php echo esc_html( $item->source_url ); ?></code></td>
							<td><?php echo $item->target_url ? esc_html( $item->target_url ) : '<em>—</em>'; ?></td>
							<td><span class="si-badge si-badge-<?php echo esc_attr( $item->status_code ); ?>"><?php echo esc_html( $item->status_code ); ?></span></td>
							<td><?php echo esc_html( $item->match_type ); ?></td>
							<td><?php echo esc_html( number_format_i18n( $item->hit_count ) ); ?></td>
							<td><?php echo $item->last_hit ? esc_html( human_time_diff( strtotime( $item->last_hit ) ) . ' ago' ) : '—'; ?></td>
							<td>
								<button class="button button-small si-edit-redirect" data-id="<?php echo esc_attr( $item->id ); ?>"><?php esc_html_e( 'Edit', 'search-intel' ); ?></button>
								<button class="button button-small button-link-delete si-delete-redirect" data-id="<?php echo esc_attr( $item->id ); ?>"><?php esc_html_e( 'Delete', 'search-intel' ); ?></button>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>

			<?php if ( $pages > 1 ) : ?>
				<div class="tablenav bottom">
					<div class="tablenav-pages">
						<?php
						echo paginate_links( [
							'base'    => add_query_arg( 'paged', '%#%' ),
							'format'  => '',
							'current' => $page,
							'total'   => $pages,
						] );
						?>
					</div>
				</div>
			<?php endif; ?>
		</div>

		<!-- Add/Edit Modal -->
		<div id="si-redirect-modal" style="display:none;">
			<div class="si-modal-backdrop"></div>
			<div class="si-modal-content" style="max-width:550px;margin:50px auto;background:#fff;padding:25px;border-radius:6px;position:relative;z-index:100001;">
				<h2 id="si-modal-title"><?php esc_html_e( 'Add Redirect', 'search-intel' ); ?></h2>
				<input type="hidden" id="si-redirect-id" value="0" />
				<table class="form-table">
					<tr>
						<th><label for="si-source-url"><?php esc_html_e( 'Source URL', 'search-intel' ); ?></label></th>
						<td><input type="text" id="si-source-url" class="large-text" placeholder="/old-page" /></td>
					</tr>
					<tr>
						<th><label for="si-target-url"><?php esc_html_e( 'Target URL', 'search-intel' ); ?></label></th>
						<td><input type="text" id="si-target-url" class="large-text" placeholder="/new-page" /></td>
					</tr>
					<tr>
						<th><label for="si-status-code"><?php esc_html_e( 'Status Code', 'search-intel' ); ?></label></th>
						<td>
							<select id="si-status-code">
								<?php foreach ( self::STATUS_CODES as $code => $label ) : ?>
									<option value="<?php echo esc_attr( $code ); ?>"><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="si-match-type"><?php esc_html_e( 'Match Type', 'search-intel' ); ?></label></th>
						<td>
							<select id="si-match-type">
								<?php foreach ( self::MATCH_TYPES as $type => $label ) : ?>
									<option value="<?php echo esc_attr( $type ); ?>"><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="si-redirect-note"><?php esc_html_e( 'Note', 'search-intel' ); ?></label></th>
						<td><input type="text" id="si-redirect-note" class="large-text" /></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Enabled', 'search-intel' ); ?></th>
						<td><label><input type="checkbox" id="si-redirect-enabled" checked /> <?php esc_html_e( 'Active', 'search-intel' ); ?></label></td>
					</tr>
				</table>
				<p>
					<button class="button button-primary" id="si-save-redirect"><?php esc_html_e( 'Save Redirect', 'search-intel' ); ?></button>
					<button class="button si-close-modal"><?php esc_html_e( 'Cancel', 'search-intel' ); ?></button>
				</p>
			</div>
		</div>

		<input type="hidden" id="si-import-file" style="display:none" />
		<?php
		wp_nonce_field( 'si_redirect_nonce', 'si_redirect_nonce_field' );
	}

	/**
	 * Render the 404 Log tab.
	 */
	private function render_404_log_tab(): void {
		$page   = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
		$result = $this->get_404_log( $page, 50 );
		$items  = $result['items'];
		$total  = $result['total'];
		$pages  = (int) ceil( $total / 50 );
		?>
		<div style="margin-top: 20px;">
			<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
				<p><?php printf( esc_html__( '%s unique 404 URLs logged.', 'search-intel' ), '<strong>' . number_format_i18n( $total ) . '</strong>' ); ?></p>
				<button type="button" class="button" id="si-clear-404-log"><?php esc_html_e( 'Clear Log', 'search-intel' ); ?></button>
			</div>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th style="width:40%"><?php esc_html_e( 'URL', 'search-intel' ); ?></th>
						<th style="width:10%"><?php esc_html_e( 'Hits', 'search-intel' ); ?></th>
						<th style="width:15%"><?php esc_html_e( 'Last Hit', 'search-intel' ); ?></th>
						<th style="width:25%"><?php esc_html_e( 'Referrer', 'search-intel' ); ?></th>
						<th style="width:10%"><?php esc_html_e( 'Action', 'search-intel' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( empty( $items ) ) : ?>
					<tr><td colspan="5"><?php esc_html_e( 'No 404 errors logged yet.', 'search-intel' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $items as $item ) : ?>
						<tr>
							<td><code><?php echo esc_html( $item->url ); ?></code></td>
							<td><?php echo esc_html( number_format_i18n( $item->hit_count ) ); ?></td>
							<td><?php echo esc_html( human_time_diff( strtotime( $item->last_hit ) ) . ' ago' ); ?></td>
							<td><?php echo $item->referrer ? esc_html( $item->referrer ) : '—'; ?></td>
							<td>
								<button class="button button-small si-create-redirect-from-404"
										data-source="<?php echo esc_attr( $item->url ); ?>">
									<?php esc_html_e( 'Redirect', 'search-intel' ); ?>
								</button>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * AJAX: Save redirect.
	 */
	public function ajax_save_redirect(): void {
		check_ajax_referer( 'si_redirect_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$data = [
			'id'          => (int) ( $_POST['id'] ?? 0 ),
			'source_url'  => sanitize_text_field( wp_unslash( $_POST['source_url'] ?? '' ) ),
			'target_url'  => sanitize_text_field( wp_unslash( $_POST['target_url'] ?? '' ) ),
			'status_code' => (int) ( $_POST['status_code'] ?? 301 ),
			'match_type'  => sanitize_key( $_POST['match_type'] ?? 'exact' ),
			'enabled'     => ! empty( $_POST['enabled'] ),
			'note'        => sanitize_text_field( wp_unslash( $_POST['note'] ?? '' ) ),
		];

		$result = $this->save_redirect( $data );
		if ( $result ) {
			wp_send_json_success( [ 'id' => $result ] );
		} else {
			wp_send_json_error( 'Failed to save redirect.' );
		}
	}

	/**
	 * AJAX: Delete redirect.
	 */
	public function ajax_delete_redirect(): void {
		check_ajax_referer( 'si_redirect_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$id = (int) ( $_POST['id'] ?? 0 );
		if ( $this->delete_redirect( $id ) ) {
			wp_send_json_success();
		} else {
			wp_send_json_error( 'Failed to delete redirect.' );
		}
	}

	/**
	 * AJAX: Export redirects as CSV download.
	 */
	public function ajax_export_csv(): void {
		check_ajax_referer( 'si_redirect_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		$csv = $this->export_csv();

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=search-intel-redirects-' . gmdate( 'Y-m-d' ) . '.csv' );
		echo $csv;
		exit;
	}

	/**
	 * AJAX: Import redirects from uploaded CSV.
	 */
	public function ajax_import_csv(): void {
		check_ajax_referer( 'si_redirect_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		if ( empty( $_FILES['csv_file'] ) ) {
			wp_send_json_error( 'No file uploaded.' );
		}

		$file = $_FILES['csv_file'];
		$csv  = file_get_contents( $file['tmp_name'] );

		if ( false === $csv ) {
			wp_send_json_error( 'Failed to read file.' );
		}

		$result = $this->import_csv( $csv );
		wp_send_json_success( $result );
	}

	/**
	 * AJAX: Clear 404 log.
	 */
	public function ajax_clear_404_log(): void {
		check_ajax_referer( 'si_redirect_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$this->clear_404_log();
		wp_send_json_success();
	}

	/**
	 * Increment the hit counter for a redirect rule.
	 *
	 * @param int $id Redirect ID.
	 */
	private function increment_hit_count( int $id ): void {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_REDIRECTS;
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET hit_count = hit_count + 1, last_hit = NOW() WHERE id = %d",
				$id
			)
		);
	}

	/**
	 * Get the current request path.
	 *
	 * @return string
	 */
	private function get_request_path(): string {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		$path = wp_parse_url( $uri, PHP_URL_PATH );
		return $path ?: '/';
	}

	/**
	 * Get the client IP address (for hashing only).
	 *
	 * @return string
	 */
	private function get_client_ip(): string {
		$ip = '';
		if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ips = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
			$ip  = trim( $ips[0] );
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}
		return $ip;
	}
}
