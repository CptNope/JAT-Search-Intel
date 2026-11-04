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
	 * Option holding the compiled lookup index of enabled rules.
	 */
	private const INDEX_OPTION = 'search_intel_redirect_index';

	/**
	 * Above this many exact rules the index stops holding them and exact
	 * lookups go to the (indexed) database column instead.
	 */
	private const INDEX_EXACT_LIMIT = 2000;

	/**
	 * Autoload the index only while it is small.
	 */
	private const INDEX_AUTOLOAD_LIMIT = 200;

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
			add_action( 'wp_ajax_search_intel_save_redirect', [ $this, 'ajax_save_redirect' ] );
			add_action( 'wp_ajax_search_intel_delete_redirect', [ $this, 'ajax_delete_redirect' ] );
			add_action( 'wp_ajax_search_intel_export_redirects', [ $this, 'ajax_export_csv' ] );
			add_action( 'wp_ajax_search_intel_import_redirects', [ $this, 'ajax_import_csv' ] );
			add_action( 'wp_ajax_search_intel_clear_404_log', [ $this, 'ajax_clear_404_log' ] );
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

		// Admin-defined rules may legitimately point at external hosts, so wp_safe_redirect() is not usable here.
		wp_redirect( $target, (int) $redirect->status_code ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
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
	 * Uses a compiled index of enabled rules so that requests which match
	 * nothing (the vast majority) cost no database queries at all. The full
	 * row is fetched by primary key only when a rule matches.
	 *
	 * @param string $request_path The incoming request path.
	 * @return object|null The matching redirect row, or null.
	 */
	public function find_matching_redirect( string $request_path ) {
		$index  = $this->get_index();
		$needle = $this->normalize_for_match( $request_path );
		$id     = 0;

		// 1. Exact match.
		if ( ! empty( $index['exact_in_db'] ) ) {
			$id = $this->find_exact_in_db( $request_path );
		} elseif ( isset( $index['exact'][ $needle ] ) ) {
			$id = (int) $index['exact'][ $needle ];
		}

		// 2. Prefix match — longest prefix wins (index is pre-sorted).
		if ( ! $id ) {
			foreach ( $index['prefix'] as $rule ) {
				if ( '' !== $rule[0] && 0 === strpos( $needle, $rule[0] ) ) {
					$id = (int) $rule[1];
					break;
				}
			}
		}

		// 3. Regex match — first matching pattern in rule order.
		if ( ! $id ) {
			foreach ( $index['regex'] as $rule ) {
				if ( @preg_match( '#' . $rule[0] . '#', $request_path ) ) {
					$id = (int) $rule[1];
					break;
				}
			}
		}

		if ( ! $id ) {
			return null;
		}

		$row = $this->get_redirect( $id );
		if ( ! $row || empty( $row->enabled ) ) {
			// Index is stale (row edited outside the plugin) — rebuild next time.
			$this->flush_index();
			return null;
		}

		return $row;
	}

	/**
	 * Case-fold a path the same way the default MySQL collations compare.
	 *
	 * @param string $path Path.
	 * @return string
	 */
	private function normalize_for_match( string $path ): string {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $path ) : strtolower( $path );
	}

	/**
	 * Exact lookup against the database (used when there are too many exact
	 * rules to keep in the index).
	 *
	 * @param string $request_path Request path.
	 * @return int Matching rule ID or 0.
	 */
	private function find_exact_in_db( string $request_path ): int {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_REDIRECTS;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE match_type = 'exact' AND source_url = %s AND enabled = 1 ORDER BY id ASC LIMIT 1",
				$request_path
			)
		);
	}

	/**
	 * Get the compiled rule index, building it if needed.
	 *
	 * @return array{exact: array<string,int>, exact_in_db: bool, prefix: array<int,array{0:string,1:int}>, regex: array<int,array{0:string,1:int}>}
	 */
	public function get_index(): array {
		$index = get_option( self::INDEX_OPTION, null );
		if ( is_array( $index ) && isset( $index['exact'], $index['prefix'], $index['regex'] ) ) {
			return $index;
		}
		return $this->rebuild_index();
	}

	/**
	 * Rebuild and store the compiled rule index from the redirects table.
	 *
	 * @return array{exact: array<string,int>, exact_in_db: bool, prefix: array<int,array{0:string,1:int}>, regex: array<int,array{0:string,1:int}>}
	 */
	public function rebuild_index(): array {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_REDIRECTS;

		$index = [
			'exact'       => [],
			'exact_in_db' => false,
			'prefix'      => [],
			'regex'       => [],
		];

		$exact_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE match_type = 'exact' AND enabled = 1" );
		$index['exact_in_db'] = $exact_count > self::INDEX_EXACT_LIMIT;

		$types = $index['exact_in_db'] ? "'prefix','regex'" : "'exact','prefix','regex'";
		$rows  = $wpdb->get_results( "SELECT id, source_url, match_type FROM {$table} WHERE enabled = 1 AND match_type IN ({$types}) ORDER BY id ASC" );

		foreach ( (array) $rows as $row ) {
			$source = (string) $row->source_url;
			$id     = (int) $row->id;

			if ( 'exact' === $row->match_type ) {
				$key = $this->normalize_for_match( $source );
				if ( ! isset( $index['exact'][ $key ] ) ) {
					$index['exact'][ $key ] = $id;
				}
			} elseif ( 'prefix' === $row->match_type ) {
				$index['prefix'][] = [ $this->normalize_for_match( $source ), $id ];
			} elseif ( 'regex' === $row->match_type ) {
				$index['regex'][] = [ $source, $id ];
			}
		}

		// Longest prefix first, ties broken by rule ID.
		usort(
			$index['prefix'],
			static function ( $a, $b ) {
				return ( strlen( $b[0] ) <=> strlen( $a[0] ) ) ?: ( $a[1] <=> $b[1] );
			}
		);

		$size     = count( $index['exact'] ) + count( $index['prefix'] ) + count( $index['regex'] );
		$autoload = $size <= self::INDEX_AUTOLOAD_LIMIT;

		delete_option( self::INDEX_OPTION );
		add_option( self::INDEX_OPTION, $index, '', $autoload );

		return $index;
	}

	/**
	 * Drop the compiled index so it is rebuilt on the next lookup.
	 */
	public function flush_index(): void {
		delete_option( self::INDEX_OPTION );
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
			$where = $wpdb->prepare( ' WHERE source_url LIKE %s OR target_url LIKE %s OR note LIKE %s', $like, $like, $like );
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
			$this->flush_index();
			return $id;
		}

		$inserted = $wpdb->insert( $table, $row, $format );
		$this->flush_index();
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
		$deleted = (bool) $wpdb->delete( $table, [ 'id' => $id ], [ '%d' ] );
		$this->flush_index();
		return $deleted;
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

		return $csv !== false ? $csv : '';
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
	public function detect_slug_change( int $post_id, $post, $post_before = null ): void {
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
			__( 'Redirects', 'jat-search-intel' ),
			__( 'Redirects', 'jat-search-intel' ),
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

		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'redirects'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view/pagination parameter.
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Search Intel — Redirects', 'jat-search-intel' ); ?></h1>

			<nav class="nav-tab-wrapper">
				<a href="?page=search-intel-redirects&tab=redirects"
				   class="nav-tab <?php echo 'redirects' === $tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Redirect Rules', 'jat-search-intel' ); ?>
				</a>
				<a href="?page=search-intel-redirects&tab=404log"
				   class="nav-tab <?php echo '404log' === $tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( '404 Log', 'jat-search-intel' ); ?>
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
		$page     = max( 1, (int) ( $_GET['paged'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view/pagination parameter.
		$search   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view/pagination parameter.
		$orderby  = sanitize_key( $_GET['orderby'] ?? 'id' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view/pagination parameter.
		$order    = sanitize_key( $_GET['order'] ?? 'DESC' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view/pagination parameter.
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
						   placeholder="<?php esc_attr_e( 'Search redirects…', 'jat-search-intel' ); ?>" class="regular-text" />
					<button type="submit" class="button"><?php esc_html_e( 'Search', 'jat-search-intel' ); ?></button>
				</form>
				<div style="display:flex; gap:8px;">
					<button type="button" class="button" id="si-import-csv"><?php esc_html_e( 'Import CSV', 'jat-search-intel' ); ?></button>
					<button type="button" class="button" id="si-export-csv"><?php esc_html_e( 'Export CSV', 'jat-search-intel' ); ?></button>
					<button type="button" class="button button-primary" id="si-add-redirect"><?php esc_html_e( 'Add Redirect', 'jat-search-intel' ); ?></button>
				</div>
			</div>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th style="width:30%"><?php esc_html_e( 'Source URL', 'jat-search-intel' ); ?></th>
						<th style="width:25%"><?php esc_html_e( 'Target URL', 'jat-search-intel' ); ?></th>
						<th style="width:8%"><?php esc_html_e( 'Code', 'jat-search-intel' ); ?></th>
						<th style="width:8%"><?php esc_html_e( 'Type', 'jat-search-intel' ); ?></th>
						<th style="width:7%"><?php esc_html_e( 'Hits', 'jat-search-intel' ); ?></th>
						<th style="width:10%"><?php esc_html_e( 'Last Hit', 'jat-search-intel' ); ?></th>
						<th style="width:12%"><?php esc_html_e( 'Actions', 'jat-search-intel' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( empty( $items ) ) : ?>
					<tr><td colspan="7"><?php esc_html_e( 'No redirect rules found.', 'jat-search-intel' ); ?></td></tr>
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
								<button class="button button-small si-edit-redirect" data-id="<?php echo esc_attr( $item->id ); ?>"><?php esc_html_e( 'Edit', 'jat-search-intel' ); ?></button>
								<button class="button button-small button-link-delete si-delete-redirect" data-id="<?php echo esc_attr( $item->id ); ?>"><?php esc_html_e( 'Delete', 'jat-search-intel' ); ?></button>
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
						echo wp_kses_post( (string) paginate_links( [
							'base'    => add_query_arg( 'paged', '%#%' ),
							'format'  => '',
							'current' => $page,
							'total'   => $pages,
						] ) );
						?>
					</div>
				</div>
			<?php endif; ?>
		</div>

		<!-- Add/Edit Modal -->
		<div id="si-redirect-modal" style="display:none;">
			<div class="si-modal-backdrop"></div>
			<div class="si-modal-content" style="max-width:550px;margin:50px auto;background:#fff;padding:25px;border-radius:6px;position:relative;z-index:100001;">
				<h2 id="si-modal-title"><?php esc_html_e( 'Add Redirect', 'jat-search-intel' ); ?></h2>
				<input type="hidden" id="si-redirect-id" value="0" />
				<table class="form-table">
					<tr>
						<th><label for="si-source-url"><?php esc_html_e( 'Source URL', 'jat-search-intel' ); ?></label></th>
						<td><input type="text" id="si-source-url" class="large-text" placeholder="/old-page" /></td>
					</tr>
					<tr>
						<th><label for="si-target-url"><?php esc_html_e( 'Target URL', 'jat-search-intel' ); ?></label></th>
						<td><input type="text" id="si-target-url" class="large-text" placeholder="/new-page" /></td>
					</tr>
					<tr>
						<th><label for="si-status-code"><?php esc_html_e( 'Status Code', 'jat-search-intel' ); ?></label></th>
						<td>
							<select id="si-status-code">
								<?php foreach ( self::STATUS_CODES as $code => $label ) : ?>
									<option value="<?php echo esc_attr( (string) $code ); ?>"><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="si-match-type"><?php esc_html_e( 'Match Type', 'jat-search-intel' ); ?></label></th>
						<td>
							<select id="si-match-type">
								<?php foreach ( self::MATCH_TYPES as $type => $label ) : ?>
									<option value="<?php echo esc_attr( $type ); ?>"><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="si-redirect-note"><?php esc_html_e( 'Note', 'jat-search-intel' ); ?></label></th>
						<td><input type="text" id="si-redirect-note" class="large-text" /></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Enabled', 'jat-search-intel' ); ?></th>
						<td><label><input type="checkbox" id="si-redirect-enabled" checked /> <?php esc_html_e( 'Active', 'jat-search-intel' ); ?></label></td>
					</tr>
				</table>
				<p>
					<button class="button button-primary" id="si-save-redirect"><?php esc_html_e( 'Save Redirect', 'jat-search-intel' ); ?></button>
					<button class="button si-close-modal"><?php esc_html_e( 'Cancel', 'jat-search-intel' ); ?></button>
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
		$page   = max( 1, (int) ( $_GET['paged'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view/pagination parameter.
		$result = $this->get_404_log( $page, 50 );
		$items  = $result['items'];
		$total  = $result['total'];
		$total_pages = (int) ceil( $total / 50 );
		?>
		<div style="margin-top: 20px;">
			<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
				<p><?php printf( esc_html__( '%s unique 404 URLs logged.', 'jat-search-intel' ), '<strong>' . esc_html( number_format_i18n( $total ) ) . '</strong>' ); ?></p>
				<button type="button" class="button" id="si-clear-404-log"><?php esc_html_e( 'Clear Log', 'jat-search-intel' ); ?></button>
			</div>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th style="width:40%"><?php esc_html_e( 'URL', 'jat-search-intel' ); ?></th>
						<th style="width:10%"><?php esc_html_e( 'Hits', 'jat-search-intel' ); ?></th>
						<th style="width:15%"><?php esc_html_e( 'Last Hit', 'jat-search-intel' ); ?></th>
						<th style="width:25%"><?php esc_html_e( 'Referrer', 'jat-search-intel' ); ?></th>
						<th style="width:10%"><?php esc_html_e( 'Action', 'jat-search-intel' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( empty( $items ) ) : ?>
					<tr><td colspan="5"><?php esc_html_e( 'No 404 errors logged yet.', 'jat-search-intel' ); ?></td></tr>
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
									<?php esc_html_e( 'Redirect', 'jat-search-intel' ); ?>
								</button>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
			<?php if ( $total_pages > 1 ) : ?>
			<div class="tablenav bottom">
				<div class="tablenav-pages">
					<?php
					echo wp_kses_post( (string) paginate_links( [
						'base'    => add_query_arg( 'paged', '%#%' ),
						'format'  => '',
						'current' => $page,
						'total'   => $total_pages,
					] ) );
					?>
				</div>
			</div>
			<?php endif; ?>
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
		echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Raw CSV file download, not HTML.
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
