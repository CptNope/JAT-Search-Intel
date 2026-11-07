<?php
/**
 * Index status from Google Search Console's URL Inspection API.
 *
 * Shows, for each page, whether Google has indexed it, why not, when it was last
 * crawled and which canonical Google chose. Uses the Search Console connection
 * set up under Reports → Rank Tracker. Pages are checked a batch at a time (by
 * hand or daily), oldest check first, within Google's 2,000 inspections per day.
 *
 * @see https://developers.google.com/webmaster-tools/v1/urlInspection.index/inspect
 *
 * @package JAT_Search_Intel
 */

namespace JAT_Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * URL Inspection results per post.
 */
class Index_Status {

	public const META_KEY  = '_jat_search_intel_index_status';
	public const CRON_HOOK = 'jat_search_intel_index_status';
	public const ENDPOINT  = 'https://searchconsole.googleapis.com/v1/urlInspection/index:inspect';
	public const PAGE_SLUG = 'jat-search-intel-index-status';

	/** Google allows 2,000 inspections per property per day; leave room for manual checks. */
	public const DAILY_LIMIT = 200;

	/** Pages checked per click of "Check now". */
	public const MANUAL_BATCH = 25;

	/** @var array<string,mixed> */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param array<string,mixed> $settings Plugin settings.
	 */
	public function __construct( array $settings ) {
		$this->settings = $settings;

		if ( ! empty( $settings['enable_index_status'] ) ) {
			add_action( self::CRON_HOOK, [ $this, 'run_daily' ] );
			if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
				wp_schedule_event( time() + 2 * HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
			}
		}

		if ( is_admin() ) {
			add_action( 'admin_menu', [ $this, 'register_admin_page' ], 32 );
			add_action( 'admin_post_jat_search_intel_index_check', [ $this, 'handle_check' ] );
		}
	}

	/**
	 * Whether Search Console is connected (service account key and property).
	 *
	 * @param array<string,mixed> $settings Plugin settings.
	 * @return bool
	 */
	public static function is_connected( array $settings ): bool {
		return '' !== Google_Auth::client_email() && '' !== trim( (string) ( $settings['gsc_site_url'] ?? '' ) );
	}

	/**
	 * Inspect one URL.
	 *
	 * @param string $url URL on this site.
	 * @return array<string,string>|\WP_Error Normalised result.
	 */
	public function inspect( string $url ) {
		$site = trim( (string) ( $this->settings['gsc_site_url'] ?? '' ) );
		if ( '' === $site ) {
			return new \WP_Error( 'jsi_site', __( 'Set your Search Console property first.', 'jat-search-intel' ) );
		}
		$token = Google_Auth::get_token( Google_Auth::SCOPE_SEARCH_CONSOLE );
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$response = wp_remote_post(
			self::ENDPOINT,
			[
				'timeout' => 20,
				'headers' => [
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				],
				'body'    => (string) wp_json_encode(
					[
						'inspectionUrl' => $url,
						'siteUrl'       => $site,
						'languageCode'  => str_replace( '_', '-', get_locale() ),
					]
				),
			]
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( 200 !== $code || ! is_array( $body ) ) {
			$message = is_array( $body ) && isset( $body['error']['message'] ) ? (string) $body['error']['message'] : sprintf( 'HTTP %d', $code );
			return new \WP_Error( 'jsi_inspect', $message, [ 'status' => $code ] );
		}

		return self::normalise( $body );
	}

	/**
	 * Keep the fields the report shows from an inspection response.
	 *
	 * @param array<string,mixed> $body Decoded API response.
	 * @return array<string,string>
	 */
	public static function normalise( array $body ): array {
		$result = (array) ( $body['inspectionResult'] ?? [] );
		$index  = (array) ( $result['indexStatusResult'] ?? [] );
		$text   = static function ( $value ): string {
			return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
		};
		return [
			'verdict'          => $text( $index['verdict'] ?? '' ),
			'coverage'         => $text( $index['coverageState'] ?? '' ),
			'indexing'         => $text( $index['indexingState'] ?? '' ),
			'robots'           => $text( $index['robotsTxtState'] ?? '' ),
			'fetch'            => $text( $index['pageFetchState'] ?? '' ),
			'last_crawl'       => $text( $index['lastCrawlTime'] ?? '' ),
			'crawled_as'       => $text( $index['crawledAs'] ?? '' ),
			'google_canonical' => esc_url_raw( (string) ( $index['googleCanonical'] ?? '' ) ),
			'user_canonical'   => esc_url_raw( (string) ( $index['userCanonical'] ?? '' ) ),
			'link'             => esc_url_raw( (string) ( $result['inspectionResultLink'] ?? '' ) ),
			'checked'          => gmdate( 'c' ),
		];
	}

	/**
	 * Inspect a post and store the result.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string,string>|\WP_Error
	 */
	public function check_post( int $post_id ) {
		$url = get_permalink( $post_id );
		if ( ! $url ) {
			return new \WP_Error( 'jsi_url', __( 'This post has no public URL.', 'jat-search-intel' ) );
		}
		$result = $this->inspect( $url );
		if ( ! is_wp_error( $result ) ) {
			update_post_meta( $post_id, self::META_KEY, $result );
		}
		return $result;
	}

	/**
	 * Stored result for a post, or [] if it hasn't been checked.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string,string>
	 */
	public static function get( int $post_id ): array {
		$value = get_post_meta( $post_id, self::META_KEY, true );
		return is_array( $value ) ? $value : [];
	}

	/**
	 * A short, plain-language status for a result.
	 *
	 * @param array<string,string> $result Stored result.
	 * @return array{0:string,1:string} [ state (indexed|excluded|error|unknown), label ]
	 */
	public static function describe( array $result ): array {
		if ( ! $result ) {
			return [ 'unknown', __( 'Not checked yet', 'jat-search-intel' ) ];
		}
		$coverage = '' !== $result['coverage'] ? $result['coverage'] : __( 'No details', 'jat-search-intel' );
		switch ( $result['verdict'] ?? '' ) {
			case 'PASS':
				return [ 'indexed', __( 'Indexed', 'jat-search-intel' ) ];
			case 'FAIL':
				return [ 'error', $coverage ];
			case 'PARTIAL':
			case 'NEUTRAL':
				return [ 'excluded', $coverage ];
			default:
				return [ 'unknown', $coverage ];
		}
	}

	/**
	 * Posts to check next: indexable, published, never checked first, then the oldest check.
	 *
	 * @param int $limit How many.
	 * @return int[]
	 */
	public function next_batch( int $limit ): array {
		$types = array_values( array_filter( (array) ( $this->settings['enabled_post_types'] ?? [ 'post', 'page' ] ), 'post_type_exists' ) );
		if ( ! $types || $limit < 1 ) {
			return [];
		}
		$not_noindex = [
			'relation' => 'OR',
			[ 'key' => '_jat_search_intel_noindex', 'compare' => 'NOT EXISTS' ],
			[ 'key' => '_jat_search_intel_noindex', 'value' => '1', 'compare' => '!=' ],
		];
		$base = [
			'post_type'              => $types,
			'post_status'            => 'publish',
			'has_password'           => false,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
		];

		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Small, bounded batches.
		$never = get_posts( $base + [ 'posts_per_page' => $limit, 'orderby' => 'modified', 'order' => 'DESC', 'meta_query' => [ 'relation' => 'AND', $not_noindex, [ 'key' => self::META_KEY, 'compare' => 'NOT EXISTS' ] ] ] );
		$never = array_map( 'intval', $never );
		if ( count( $never ) >= $limit ) {
			return $never;
		}

		// Re-check the stalest results.
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Small, bounded batches.
		$checked = get_posts( $base + [ 'posts_per_page' => 500, 'meta_query' => [ 'relation' => 'AND', $not_noindex, [ 'key' => self::META_KEY, 'compare' => 'EXISTS' ] ] ] );
		$ages    = [];
		foreach ( array_map( 'intval', $checked ) as $id ) {
			$ages[ $id ] = (string) ( self::get( $id )['checked'] ?? '' );
		}
		asort( $ages );
		$stale = array_diff( array_keys( $ages ), $never );
		return array_merge( $never, array_slice( array_values( $stale ), 0, $limit - count( $never ) ) );
	}

	/**
	 * Check a batch of posts.
	 *
	 * @param int $limit How many.
	 * @return array{checked:int,errors:int,message:string}
	 */
	public function run( int $limit ): array {
		$checked = 0;
		$errors  = 0;
		$message = '';
		foreach ( $this->next_batch( $limit ) as $post_id ) {
			$result = $this->check_post( $post_id );
			if ( is_wp_error( $result ) ) {
				$errors++;
				$message = $result->get_error_message();
				// Quota, permission or auth problems affect every request; stop early.
				$data   = (array) $result->get_error_data();
				$status = (int) ( $data['status'] ?? 0 );
				if ( in_array( $status, [ 401, 403, 429 ], true ) || $errors >= 3 ) {
					break;
				}
				continue;
			}
			$checked++;
		}
		return [ 'checked' => $checked, 'errors' => $errors, 'message' => $message ];
	}

	/**
	 * Daily cron run.
	 */
	public function run_daily(): void {
		if ( self::is_connected( $this->settings ) ) {
			$this->run( self::DAILY_LIMIT );
		}
	}

	// ── Admin ───────────────────────────────────────────────────────────

	/**
	 * Register the report page.
	 */
	public function register_admin_page(): void {
		add_submenu_page(
			'jat-search-intel',
			__( 'Index Status', 'jat-search-intel' ),
			__( 'Index Status', 'jat-search-intel' ),
			'manage_options',
			self::PAGE_SLUG,
			[ $this, 'render_admin_page' ]
		);
	}

	/**
	 * "Check now" (a batch) or "Check" (one post).
	 */
	public function handle_check(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'jat-search-intel' ), 403 );
		}
		check_admin_referer( 'jat_search_intel_index_check' );

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( $post_id ) {
			$result = $this->check_post( $post_id );
			$notice = is_wp_error( $result ) ? 'error:' . $result->get_error_message() : 'one';
		} else {
			$run    = $this->run( self::MANUAL_BATCH );
			$notice = $run['errors'] && ! $run['checked'] ? 'error:' . $run['message'] : 'batch:' . $run['checked'];
		}

		wp_safe_redirect( add_query_arg( [ 'page' => self::PAGE_SLUG, 'jsi_notice' => rawurlencode( $notice ) ], admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * The report page.
	 */
	public function render_admin_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		echo '<div class="wrap si-settings"><h1>' . esc_html__( 'Index Status', 'jat-search-intel' ) . '</h1>';
		echo '<p>' . esc_html__( 'Whether Google has indexed each page, straight from Search Console\'s URL Inspection tool.', 'jat-search-intel' ) . '</p>';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only notice after a nonce-checked redirect.
		$notice = isset( $_GET['jsi_notice'] ) ? sanitize_text_field( wp_unslash( $_GET['jsi_notice'] ) ) : '';
		if ( 0 === strpos( $notice, 'error:' ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html( substr( $notice, 6 ) ) . '</p></div>';
		} elseif ( 0 === strpos( $notice, 'batch:' ) ) {
			/* translators: %d: number of pages. */
			echo '<div class="notice notice-success"><p>' . esc_html( sprintf( _n( 'Checked %d page.', 'Checked %d pages.', (int) substr( $notice, 6 ), 'jat-search-intel' ), (int) substr( $notice, 6 ) ) ) . '</p></div>';
		} elseif ( 'one' === $notice ) {
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Page checked.', 'jat-search-intel' ) . '</p></div>';
		}

		if ( ! self::is_connected( $this->settings ) ) {
			$rank_url = admin_url( 'admin.php?page=jat-search-intel-rank-tracker' );
			echo '<div class="si-settings-card"><p>' . wp_kses_post(
				sprintf(
					/* translators: %s: link to the Rank Tracker page. */
					__( 'Connect Google Search Console first, on the <a href="%s">Rank Tracker</a> page. Index Status uses the same connection.', 'jat-search-intel' ),
					esc_url( $rank_url )
				)
			) . '</p></div></div>';
			return;
		}

		$rows   = $this->report_rows();
		$counts = [ 'indexed' => 0, 'excluded' => 0, 'error' => 0, 'unknown' => 0 ];
		foreach ( $rows as $row ) {
			$counts[ $row['state'] ]++;
		}

		echo '<div class="si-settings-card" style="display:flex;gap:24px;flex-wrap:wrap;align-items:center">';
		$labels = [
			'indexed'  => __( 'Indexed', 'jat-search-intel' ),
			'excluded' => __( 'Not indexed', 'jat-search-intel' ),
			'error'    => __( 'Errors', 'jat-search-intel' ),
			'unknown'  => __( 'Not checked', 'jat-search-intel' ),
		];
		foreach ( $labels as $state => $label ) {
			echo '<div><strong style="font-size:22px;display:block">' . esc_html( number_format_i18n( $counts[ $state ] ) ) . '</strong>' . esc_html( $label ) . '</div>';
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-left:auto">';
		wp_nonce_field( 'jat_search_intel_index_check' );
		echo '<input type="hidden" name="action" value="jat_search_intel_index_check" />';
		/* translators: %d: number of pages. */
		submit_button( sprintf( __( 'Check the next %d pages', 'jat-search-intel' ), self::MANUAL_BATCH ), 'primary', 'submit', false );
		echo '</form></div>';

		if ( empty( $this->settings['enable_index_status'] ) ) {
			echo '<p class="description">' . esc_html__( 'Tip: switch on daily checks under Settings → General → Module Toggles to keep this report up to date automatically.', 'jat-search-intel' ) . '</p>';
		}

		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Page', 'jat-search-intel' ) . '</th><th>' . esc_html__( 'Status', 'jat-search-intel' ) . '</th><th>' . esc_html__( 'Last crawled', 'jat-search-intel' ) . '</th><th>' . esc_html__( 'Canonical', 'jat-search-intel' ) . '</th><th>' . esc_html__( 'Checked', 'jat-search-intel' ) . '</th><th></th></tr></thead><tbody>';
		if ( ! $rows ) {
			echo '<tr><td colspan="6">' . esc_html__( 'No pages to show yet.', 'jat-search-intel' ) . '</td></tr>';
		}
		$colors = [ 'indexed' => '#00a32a', 'excluded' => '#dba617', 'error' => '#d63638', 'unknown' => '#8c8f94' ];
		foreach ( $rows as $row ) {
			$result    = $row['result'];
			$mismatch  = $result && '' !== $result['google_canonical'] && '' !== $result['user_canonical'] && untrailingslashit( $result['google_canonical'] ) !== untrailingslashit( $result['user_canonical'] );
			$canonical = $mismatch
				? '<span style="color:#d63638">' . esc_html__( 'Google chose a different URL:', 'jat-search-intel' ) . '</span><br /><a href="' . esc_url( $result['google_canonical'] ) . '">' . esc_html( $result['google_canonical'] ) . '</a>'
				: ( $result && '' !== $result['google_canonical'] ? esc_html__( 'Matches', 'jat-search-intel' ) : '—' );
			echo '<tr><td><a href="' . esc_url( (string) get_edit_post_link( $row['id'] ) ) . '"><strong>' . esc_html( get_the_title( $row['id'] ) ?: __( '(no title)', 'jat-search-intel' ) ) . '</strong></a><br /><small>' . esc_html( (string) wp_make_link_relative( (string) get_permalink( $row['id'] ) ) ) . '</small></td>';
			echo '<td><span style="display:inline-block;width:9px;height:9px;border-radius:50%;background:' . esc_attr( $colors[ $row['state'] ] ) . ';margin-right:6px"></span>' . esc_html( $row['label'] ) . '</td>';
			echo '<td>' . esc_html( $result && '' !== $result['last_crawl'] ? mysql2date( get_option( 'date_format' ), $result['last_crawl'] ) : '—' ) . '</td>';
			echo '<td>' . $canonical . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts above.
			echo '<td>' . esc_html( $result ? human_time_diff( (int) strtotime( $result['checked'] ) ) : '—' ) . '</td><td>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline">';
			wp_nonce_field( 'jat_search_intel_index_check' );
			echo '<input type="hidden" name="action" value="jat_search_intel_index_check" /><input type="hidden" name="post_id" value="' . esc_attr( (string) $row['id'] ) . '" />';
			echo '<button type="submit" class="button-link">' . esc_html__( 'Check', 'jat-search-intel' ) . '</button></form>';
			if ( $result && '' !== $result['link'] ) {
				echo ' · <a href="' . esc_url( $result['link'] ) . '" target="_blank" rel="noopener">' . esc_html__( 'Open in Search Console', 'jat-search-intel' ) . '</a>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table></div>';
	}

	/**
	 * Report rows: problems first, then never checked, then indexed.
	 *
	 * @return array<int,array{id:int,result:array<string,string>,state:string,label:string}>
	 */
	private function report_rows(): array {
		$types = array_values( array_filter( (array) ( $this->settings['enabled_post_types'] ?? [ 'post', 'page' ] ), 'post_type_exists' ) );
		if ( ! $types ) {
			return [];
		}
		$ids  = get_posts(
			[
				'post_type'      => $types,
				'post_status'    => 'publish',
				'posts_per_page' => 300,
				'fields'         => 'ids',
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			]
		);
		$rows  = [];
		$order = [ 'error' => 0, 'excluded' => 1, 'unknown' => 2, 'indexed' => 3 ];
		foreach ( array_map( 'intval', $ids ) as $id ) {
			$result               = self::get( $id );
			list( $state, $label ) = self::describe( $result );
			$rows[]               = [ 'id' => $id, 'result' => $result, 'state' => $state, 'label' => $label ];
		}
		usort(
			$rows,
			static function ( array $a, array $b ) use ( $order ): int {
				return $order[ $a['state'] ] <=> $order[ $b['state'] ];
			}
		);
		return $rows;
	}
}
