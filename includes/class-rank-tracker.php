<?php
/**
 * Keyword rank tracking built on the site's own Google Search Console data.
 *
 * Daily, it pulls average position, clicks and impressions for tracked
 * keywords (or the site's top queries when none are set) and keeps a
 * history table, so you can see movement without a paid rank-tracking API.
 *
 * @package JAT_Search_Intel
 */

namespace JAT_Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rank tracker.
 */
class Rank_Tracker {

	public const TABLE      = 'jat_search_intel_rankings';
	public const CRON_HOOK  = 'jat_search_intel_rank_tracking';
	public const AUTO_TRACK = 25;

	private const LAST_RUN_OPTION = 'jat_search_intel_rank_last_run';
	private const REGEX_MAX       = 3500;

	/** @var array<string,mixed> */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param array<string,mixed> $settings Plugin settings.
	 */
	public function __construct( array $settings ) {
		$this->settings = $settings;

		if ( ! empty( $settings['enable_rank_tracking'] ) ) {
			add_action( self::CRON_HOOK, [ $this, 'run' ] );
			if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
				wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
			}
		}

		if ( is_admin() ) {
			add_action( 'admin_menu', [ $this, 'register_admin_page' ], 31 );
			add_action( 'admin_post_jat_search_intel_rank_key', [ $this, 'handle_key' ] );
			add_action( 'admin_post_jat_search_intel_rank_fetch', [ $this, 'handle_fetch' ] );
		}
	}

	/**
	 * Create the history table.
	 */
	public static function create_table(): void {
		global $wpdb;
		$table   = $wpdb->prefix . self::TABLE;
		$charset = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta(
			"CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			day date NOT NULL,
			keyword varchar(190) NOT NULL,
			position decimal(6,2) NOT NULL DEFAULT 0,
			clicks int(10) unsigned NOT NULL DEFAULT 0,
			impressions int(10) unsigned NOT NULL DEFAULT 0,
			url varchar(2048) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			UNIQUE KEY day_keyword (day,keyword),
			KEY keyword (keyword)
		) {$charset};"
		);
	}

	/**
	 * Keywords to track: the configured list, else the site's top queries.
	 *
	 * @param string $token  Access token (for auto mode).
	 * @param string $site   Property.
	 * @return string[]
	 */
	public function get_keywords( string $token = '', string $site = '' ): array {
		$list = array_values( array_filter( array_map( 'strval', (array) ( $this->settings['tracked_keywords'] ?? [] ) ) ) );
		if ( $list || '' === $token ) {
			return $list;
		}

		$rows = $this->query(
			$token,
			$site,
			[
				'startDate'  => gmdate( 'Y-m-d', time() - 31 * DAY_IN_SECONDS ),
				'endDate'    => gmdate( 'Y-m-d', time() - 3 * DAY_IN_SECONDS ),
				'dimensions' => [ 'query' ],
				'rowLimit'   => self::AUTO_TRACK,
			]
		);

		return is_wp_error( $rows ) ? [] : array_values( array_filter( array_map( static function ( $row ) {
			return mb_strtolower( (string) ( $row['keys'][0] ?? '' ) );
		}, $rows ) ) );
	}

	/**
	 * Build anchored RE2 alternation patterns for the GSC includingRegex filter.
	 *
	 * @param string[] $keywords Keywords.
	 * @return string[] One or more patterns, each under the API length limit.
	 */
	public static function build_regex_chunks( array $keywords ): array {
		$chunks  = [];
		$current = [];
		$length  = 0;

		foreach ( $keywords as $keyword ) {
			$escaped = preg_quote( (string) $keyword, '/' );
			$escaped = str_replace( '\/', '/', $escaped );
			if ( $current && $length + strlen( $escaped ) + 1 > self::REGEX_MAX ) {
				$chunks[] = '^(?:' . implode( '|', $current ) . ')$';
				$current  = [];
				$length   = 0;
			}
			$current[] = $escaped;
			$length   += strlen( $escaped ) + 1;
		}
		if ( $current ) {
			$chunks[] = '^(?:' . implode( '|', $current ) . ')$';
		}
		return $chunks;
	}

	/**
	 * Collapse per-page rows into one row per day and keyword.
	 *
	 * Position is the impression-weighted average across pages; the best
	 * page is the one with the most clicks (then impressions).
	 *
	 * @param array $rows API rows with keys [date, query, page].
	 * @return array<string,array{day:string,keyword:string,position:float,clicks:int,impressions:int,url:string}>
	 */
	public static function aggregate( array $rows ): array {
		$out      = [];
		$best     = [];
		$weighted = [];

		foreach ( $rows as $row ) {
			$day     = (string) ( $row['keys'][0] ?? '' );
			$keyword = mb_strtolower( (string) ( $row['keys'][1] ?? '' ) );
			$url     = (string) ( $row['keys'][2] ?? '' );
			if ( '' === $day || '' === $keyword ) {
				continue;
			}
			$id          = $day . '|' . $keyword;
			$impressions = (int) ( $row['impressions'] ?? 0 );
			$clicks      = (int) ( $row['clicks'] ?? 0 );
			$position    = (float) ( $row['position'] ?? 0 );

			if ( ! isset( $out[ $id ] ) ) {
				$out[ $id ] = [
					'day'         => $day,
					'keyword'     => $keyword,
					'position'    => 0.0,
					'clicks'      => 0,
					'impressions' => 0,
					'url'         => $url,
				];
				$best[ $id ]     = [ $clicks, $impressions ];
				$weighted[ $id ] = 0.0;
			}

			$out[ $id ]['clicks']      += $clicks;
			$out[ $id ]['impressions'] += $impressions;
			$weighted[ $id ]           += $position * max( 1, $impressions );

			if ( [ $clicks, $impressions ] > $best[ $id ] ) {
				$best[ $id ]       = [ $clicks, $impressions ];
				$out[ $id ]['url'] = $url;
			}
		}

		foreach ( $out as $id => $row ) {
			$out[ $id ]['position'] = round( $weighted[ $id ] / max( 1, $row['impressions'] ), 2 );
		}

		return $out;
	}

	/**
	 * Search Console searchAnalytics.query.
	 *
	 * @param string $token Access token.
	 * @param string $site  Property (https://example.com/ or sc-domain:example.com).
	 * @param array  $body  Request body.
	 * @return array|\WP_Error Rows.
	 */
	private function query( string $token, string $site, array $body ) {
		$response = wp_remote_post(
			'https://searchconsole.googleapis.com/webmasters/v3/sites/' . rawurlencode( $site ) . '/searchAnalytics/query',
			[
				'timeout' => 30,
				'headers' => [
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				],
				'body'    => (string) wp_json_encode( $body ),
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( 200 !== $code ) {
			$message = is_array( $data ) ? (string) ( $data['error']['message'] ?? '' ) : '';
			/* translators: %d: HTTP status code. */
			return new \WP_Error( 'jsi_gsc', $message ?: sprintf( __( 'Search Console returned HTTP %d.', 'jat-search-intel' ), $code ) );
		}

		return (array) ( $data['rows'] ?? [] );
	}

	/**
	 * Fetch and store positions. Backfills 28 days on the first run.
	 *
	 * @return int|\WP_Error Rows stored.
	 */
	public function run() {
		$site = trim( (string) ( $this->settings['gsc_site_url'] ?? '' ) );
		if ( '' === $site ) {
			return new \WP_Error( 'jsi_site', __( 'Set your Search Console property first.', 'jat-search-intel' ) );
		}

		$token = Google_Auth::get_token( Google_Auth::SCOPE_SEARCH_CONSOLE );
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$keywords = $this->get_keywords( $token, $site );
		if ( ! $keywords ) {
			return 0;
		}

		$last  = (string) get_option( self::LAST_RUN_OPTION, '' );
		$end   = gmdate( 'Y-m-d', time() - 3 * DAY_IN_SECONDS );
		$start = '' === $last ? gmdate( 'Y-m-d', time() - 31 * DAY_IN_SECONDS ) : gmdate( 'Y-m-d', time() - 6 * DAY_IN_SECONDS );

		$rows = [];
		foreach ( self::build_regex_chunks( $keywords ) as $pattern ) {
			$result = $this->query(
				$token,
				$site,
				[
					'startDate'             => $start,
					'endDate'               => $end,
					'dimensions'            => [ 'date', 'query', 'page' ],
					'dimensionFilterGroups' => [
						[
							'filters' => [
								[
									'dimension'  => 'query',
									'operator'   => 'includingRegex',
									'expression' => $pattern,
								],
							],
						],
					],
					'rowLimit'              => 25000,
				]
			);
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$rows = array_merge( $rows, $result );
		}

		$stored = $this->store( self::aggregate( $rows ) );
		update_option( self::LAST_RUN_OPTION, gmdate( 'Y-m-d H:i:s' ), false );
		return $stored;
	}

	/**
	 * Upsert aggregated rows.
	 *
	 * @param array $rows Aggregated rows.
	 * @return int
	 */
	private function store( array $rows ): int {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE;
		$count = 0;

		foreach ( $rows as $row ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table write.
			$ok = $wpdb->replace(
				$table,
				[
					'day'         => $row['day'],
					'keyword'     => mb_substr( $row['keyword'], 0, 190 ),
					'position'    => $row['position'],
					'clicks'      => $row['clicks'],
					'impressions' => $row['impressions'],
					'url'         => esc_url_raw( $row['url'] ),
				],
				[ '%s', '%s', '%f', '%d', '%d', '%s' ]
			);
			$count += $ok ? 1 : 0;
		}

		// Keep 16 months, like Search Console itself.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Housekeeping on a custom table.
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE day < %s', $table, gmdate( 'Y-m-d', time() - 487 * DAY_IN_SECONDS ) ) );

		return $count;
	}

	/**
	 * Report rows: latest position and movement for each keyword.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function get_report(): array {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE;
		$since = gmdate( 'Y-m-d', time() - 45 * DAY_IN_SECONDS );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin report on a custom table.
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT day, keyword, position, clicks, impressions, url FROM %i WHERE day >= %s ORDER BY keyword ASC, day ASC', $table, $since ), ARRAY_A );

		$by_keyword = [];
		foreach ( (array) $rows as $row ) {
			$by_keyword[ $row['keyword'] ][] = $row;
		}

		$report = [];
		foreach ( $by_keyword as $keyword => $series ) {
			$latest = end( $series );
			$report[] = [
				'keyword'     => $keyword,
				'position'    => (float) $latest['position'],
				'day'         => $latest['day'],
				'url'         => $latest['url'],
				'change_7'    => self::change( $series, 7 ),
				'change_28'   => self::change( $series, 28 ),
				'clicks'      => array_sum( array_map( 'intval', array_column( $series, 'clicks' ) ) ),
				'impressions' => array_sum( array_map( 'intval', array_column( $series, 'impressions' ) ) ),
				'series'      => array_map( 'floatval', array_column( array_slice( $series, -30 ), 'position' ) ),
			];
		}

		usort( $report, static function ( $a, $b ) {
			return $a['position'] <=> $b['position'];
		} );

		return $report;
	}

	/**
	 * Position change versus N days before the latest data point
	 * (positive = moved up).
	 *
	 * @param array $series Rows ordered by day.
	 * @param int   $days   Days back.
	 * @return float|null
	 */
	public static function change( array $series, int $days ): ?float {
		if ( ! $series ) {
			return null;
		}
		$latest = end( $series );
		$target = gmdate( 'Y-m-d', strtotime( $latest['day'] . " -{$days} days" ) );
		$then   = null;
		foreach ( $series as $row ) {
			if ( $row['day'] <= $target ) {
				$then = $row;
			}
		}
		return $then ? round( (float) $then['position'] - (float) $latest['position'], 1 ) : null;
	}

	/**
	 * Inline SVG sparkline (lower position = higher line).
	 *
	 * @param float[] $series Positions.
	 * @return string
	 */
	public static function sparkline( array $series ): string {
		if ( count( $series ) < 2 ) {
			return '';
		}
		$w   = 120;
		$h   = 28;
		$min = min( $series );
		$max = max( $series );
		$rng = max( 0.5, $max - $min );
		$pts = [];
		$n   = count( $series ) - 1;
		foreach ( array_values( $series ) as $i => $pos ) {
			$pts[] = round( $i * $w / $n, 1 ) . ',' . round( 2 + ( $pos - $min ) / $rng * ( $h - 4 ), 1 );
		}
		return '<svg width="' . $w . '" height="' . $h . '" viewBox="0 0 ' . $w . ' ' . $h . '" role="img" aria-hidden="true"><polyline fill="none" stroke="currentColor" stroke-width="1.5" points="' . esc_attr( implode( ' ', $pts ) ) . '"/></svg>';
	}

	// ── Admin ───────────────────────────────────────────────────────────

	/**
	 * Register the page.
	 */
	public function register_admin_page(): void {
		add_submenu_page(
			'jat-search-intel',
			__( 'Rank Tracker', 'jat-search-intel' ),
			__( 'Rank Tracker', 'jat-search-intel' ),
			'manage_options',
			'jat-search-intel-rank-tracker',
			[ $this, 'render_admin_page' ]
		);
	}

	/**
	 * Save or remove the service account key.
	 */
	public function handle_key(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'jat-search-intel' ), 403 );
		}
		check_admin_referer( 'jat_search_intel_rank_key' );

		$status = 'key-saved';
		if ( ! empty( $_POST['remove_key'] ) ) {
			Google_Auth::delete_key();
			$status = 'key-removed';
		} else {
			$json   = isset( $_POST['service_account'] ) ? trim( (string) wp_unslash( $_POST['service_account'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated as a service account key and encrypted.
			$result = Google_Auth::save_key( $json );
			if ( is_wp_error( $result ) ) {
				$status = 'key-invalid';
			}
		}

		wp_safe_redirect( add_query_arg( 'jsi_status', $status, admin_url( 'admin.php?page=jat-search-intel-rank-tracker' ) ) );
		exit;
	}

	/**
	 * Fetch now.
	 */
	public function handle_fetch(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'jat-search-intel' ), 403 );
		}
		check_admin_referer( 'jat_search_intel_rank_fetch' );

		$result = $this->run();
		if ( is_wp_error( $result ) ) {
			set_transient( 'jat_search_intel_rank_error_' . get_current_user_id(), $result->get_error_message(), 120 );
			$args = [ 'jsi_status' => 'fetch-error' ];
		} else {
			$args = [ 'jsi_status' => 'fetched', 'jsi_count' => (int) $result ];
		}

		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php?page=jat-search-intel-rank-tracker' ) ) );
		exit;
	}

	/**
	 * Render the page.
	 */
	public function render_admin_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only status flags after a redirect.
		$status  = isset( $_GET['jsi_status'] ) ? sanitize_key( wp_unslash( $_GET['jsi_status'] ) ) : '';
		$count   = isset( $_GET['jsi_count'] ) ? absint( $_GET['jsi_count'] ) : 0;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$message = (string) get_transient( 'jat_search_intel_rank_error_' . get_current_user_id() );
		if ( '' !== $message ) {
			delete_transient( 'jat_search_intel_rank_error_' . get_current_user_id() );
		}

		$email    = Google_Auth::client_email();
		$keywords = implode( "\n", (array) ( $this->settings['tracked_keywords'] ?? [] ) );

		echo '<div class="wrap"><h1>' . esc_html__( 'Rank Tracker', 'jat-search-intel' ) . '</h1>';
		echo '<p>' . esc_html__( 'Daily average Google position for your keywords, from your own Search Console data. No third-party rank API.', 'jat-search-intel' ) . '</p>';

		$notices = [
			'key-saved'   => [ 'success', __( 'Service account connected.', 'jat-search-intel' ) ],
			'key-removed' => [ 'success', __( 'Service account removed.', 'jat-search-intel' ) ],
			'key-invalid' => [ 'error', __( 'That is not a Google service account JSON key.', 'jat-search-intel' ) ],
			/* translators: %d: number of rows. */
			'fetched'     => [ 'success', sprintf( __( 'Fetched %d keyword-days from Search Console.', 'jat-search-intel' ), $count ) ],
			'fetch-error' => [ 'error', $message ],
		];
		if ( isset( $notices[ $status ] ) ) {
			printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $notices[ $status ][0] ), esc_html( $notices[ $status ][1] ) );
		}

		// Report.
		$report = $this->get_report();
		if ( $report ) {
			echo '<table class="widefat striped" style="max-width:1100px"><thead><tr>'
				. '<th>' . esc_html__( 'Keyword', 'jat-search-intel' ) . '</th>'
				. '<th>' . esc_html__( 'Position', 'jat-search-intel' ) . '</th>'
				. '<th>' . esc_html__( '7 days', 'jat-search-intel' ) . '</th>'
				. '<th>' . esc_html__( '28 days', 'jat-search-intel' ) . '</th>'
				. '<th>' . esc_html__( 'Trend', 'jat-search-intel' ) . '</th>'
				. '<th>' . esc_html__( 'Clicks', 'jat-search-intel' ) . '</th>'
				. '<th>' . esc_html__( 'Impressions', 'jat-search-intel' ) . '</th>'
				. '<th>' . esc_html__( 'Ranking page', 'jat-search-intel' ) . '</th>'
				. '</tr></thead><tbody>';
			foreach ( $report as $row ) {
				echo '<tr><td>' . esc_html( $row['keyword'] ) . '</td>'
					. '<td><strong>' . esc_html( number_format_i18n( $row['position'], 1 ) ) . '</strong></td>'
					. '<td>' . wp_kses_post( self::format_change( $row['change_7'] ) ) . '</td>'
					. '<td>' . wp_kses_post( self::format_change( $row['change_28'] ) ) . '</td>'
					. '<td>' . self::sparkline( $row['series'] ) . '</td>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG built from numbers; attribute escaped.
					. '<td>' . esc_html( number_format_i18n( $row['clicks'] ) ) . '</td>'
					. '<td>' . esc_html( number_format_i18n( $row['impressions'] ) ) . '</td>'
					. '<td><a href="' . esc_url( $row['url'] ) . '">' . esc_html( (string) wp_parse_url( $row['url'], PHP_URL_PATH ) ) . '</a></td></tr>';
			}
			echo '</tbody></table>';
			echo '<p class="description">' . esc_html__( 'Search Console data arrives with a 2–3 day delay. Positions are impression-weighted averages across your pages.', 'jat-search-intel' ) . '</p>';
		} else {
			echo '<p><em>' . esc_html__( 'No ranking data yet. Connect Search Console below and click "Fetch now".', 'jat-search-intel' ) . '</em></p>';
		}

		// Settings (saved through the main settings group, page-scoped).
		echo '<h2>' . esc_html__( 'Keywords & property', 'jat-search-intel' ) . '</h2>';
		echo '<form method="post" action="options.php">';
		settings_fields( 'jat_search_intel_settings_group' );
		echo '<input type="hidden" name="jat_search_intel_settings[_si_page]" value="rank" />';
		echo '<table class="form-table"><tr><th>' . esc_html__( 'Enable', 'jat-search-intel' ) . '</th><td><label><input type="checkbox" name="jat_search_intel_settings[enable_rank_tracking]" value="1" ' . checked( ! empty( $this->settings['enable_rank_tracking'] ), true, false ) . ' /> ' . esc_html__( 'Fetch positions daily', 'jat-search-intel' ) . '</label></td></tr>';
		echo '<tr><th><label for="jsi-gsc-site">' . esc_html__( 'Search Console property', 'jat-search-intel' ) . '</label></th><td><input id="jsi-gsc-site" type="text" class="regular-text" name="jat_search_intel_settings[gsc_site_url]" value="' . esc_attr( (string) ( $this->settings['gsc_site_url'] ?? '' ) ) . '" placeholder="sc-domain:example.com" />'
			. '<p class="description">' . esc_html__( 'Exactly as it appears in Search Console: sc-domain:example.com for a domain property, or https://www.example.com/ for a URL-prefix property.', 'jat-search-intel' ) . '</p></td></tr>';
		echo '<tr><th><label for="jsi-keywords">' . esc_html__( 'Tracked keywords', 'jat-search-intel' ) . '</label></th><td><textarea id="jsi-keywords" name="jat_search_intel_settings[tracked_keywords]" rows="8" class="large-text">' . esc_textarea( $keywords ) . '</textarea>'
			/* translators: %d: number of top queries tracked automatically. */
			. '<p class="description">' . esc_html( sprintf( __( 'One per line, up to 500. Leave empty to track your top %d queries automatically.', 'jat-search-intel' ), self::AUTO_TRACK ) ) . '</p></td></tr></table>';
		submit_button( __( 'Save', 'jat-search-intel' ) );
		echo '</form>';

		// Connection.
		echo '<h2>' . esc_html__( 'Google connection', 'jat-search-intel' ) . '</h2>';
		if ( $email ) {
			/* translators: %s: service account email. */
			echo '<p>' . wp_kses_post( sprintf( __( 'Connected as %s. Make sure this address is added as a user (Restricted is enough) on your Search Console property.', 'jat-search-intel' ), '<code>' . esc_html( $email ) . '</code>' ) ) . '</p>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;margin-right:8px">';
			wp_nonce_field( 'jat_search_intel_rank_fetch' );
			echo '<input type="hidden" name="action" value="jat_search_intel_rank_fetch" />';
			submit_button( __( 'Fetch now', 'jat-search-intel' ), 'primary', 'submit', false );
			echo '</form>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block">';
			wp_nonce_field( 'jat_search_intel_rank_key' );
			echo '<input type="hidden" name="action" value="jat_search_intel_rank_key" /><input type="hidden" name="remove_key" value="1" />';
			submit_button( __( 'Disconnect', 'jat-search-intel' ), 'secondary', 'submit', false );
			echo '</form>';
		} else {
			echo '<ol><li>' . esc_html__( 'In Google Cloud Console, create a service account and download a JSON key.', 'jat-search-intel' ) . '</li>'
				. '<li>' . esc_html__( 'Enable the "Google Search Console API" for that project.', 'jat-search-intel' ) . '</li>'
				. '<li>' . esc_html__( 'In Search Console > Settings > Users and permissions, add the service account email as a Restricted user.', 'jat-search-intel' ) . '</li>'
				. '<li>' . esc_html__( 'Paste the JSON key below. It is encrypted before it is stored.', 'jat-search-intel' ) . '</li></ol>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( 'jat_search_intel_rank_key' );
			echo '<input type="hidden" name="action" value="jat_search_intel_rank_key" />';
			echo '<textarea name="service_account" rows="6" class="large-text code" autocomplete="off" spellcheck="false" placeholder="{&quot;type&quot;: &quot;service_account&quot;, ...}"></textarea>';
			submit_button( __( 'Connect', 'jat-search-intel' ) );
			echo '</form>';
		}

		echo '</div>';
	}

	/**
	 * Movement badge.
	 *
	 * @param float|null $change Positive = improved.
	 * @return string
	 */
	public static function format_change( ?float $change ): string {
		if ( null === $change ) {
			return '—';
		}
		if ( abs( $change ) < 0.05 ) {
			return '0';
		}
		$color = $change > 0 ? '#00a32a' : '#d63638';
		$arrow = $change > 0 ? '▲' : '▼';
		return '<span style="color:' . esc_attr( $color ) . '">' . esc_html( $arrow . ' ' . number_format_i18n( abs( $change ), 1 ) ) . '</span>';
	}
}
