<?php
namespace JAT_Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Broken Link Checker — background HTTP HEAD scan for dead links.
 *
 * Extracts all links from published posts and checks them via HTTP HEAD
 * on a WP-Cron schedule. Broken links are stored for admin review.
 *
 * @since 2.0.1
 */
class Link_Checker {

	/**
	 * Option key for storing broken-link results.
	 */
	private const OPTION_KEY = 'jat_search_intel_broken_links';

	/**
	 * Option key for last full scan timestamp.
	 */
	private const LAST_SCAN_KEY = 'jat_search_intel_link_checker_last_scan';

	/**
	 * WP-Cron hook name.
	 */
	private const CRON_HOOK = 'jat_search_intel_check_links';

	/**
	 * Max URLs to check per cron run (to avoid timeouts).
	 */
	private const BATCH_SIZE = 25;

	/**
	 * HTTP status codes considered broken.
	 */
	private const BROKEN_CODES = [ 0, 400, 404, 405, 408, 410, 500, 502, 503, 504 ];

	/**
	 * Codes a site sends when it refuses automated requests. The page usually
	 * works in a browser, so these are reported as blocked, never broken.
	 */
	private const BLOCKED_CODES = [ 401, 403, 429, 999 ];

	/**
	 * HEAD answers that are worth a second try with GET, because many servers
	 * and firewalls refuse HEAD but serve GET.
	 */
	private const RETRY_WITH_GET = [ 0, 400, 403, 405, 429, 501 ];

	/** @var array */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param array $settings Plugin settings.
	 */
	public function __construct( array $settings ) {
		$this->settings = $settings;

		if ( ! empty( $settings['enable_link_checker'] ) ) {
			add_action( self::CRON_HOOK, [ $this, 'run_batch_check' ] );
			// Nothing scheduled the hourly event before, so turning the checker on never started a scan.
			add_action( 'init', [ $this, 'schedule' ] );

			if ( is_admin() ) {
				add_action( 'admin_menu', [ $this, 'register_admin_page' ] );
				add_action( 'admin_post_jat_search_intel_link_check_now', [ $this, 'handle_check_now' ] );
			}
		}
	}

	/**
	 * Schedule the cron event if not already scheduled.
	 */
	public function schedule(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'hourly', self::CRON_HOOK );
		}
	}

	/**
	 * Unschedule the cron event.
	 */
	public function unschedule(): void {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
	}

	/**
	 * Extract all URLs from a post's content.
	 *
	 * @param int $post_id Post ID.
	 * @return array List of URLs found.
	 */
	public function extract_links( int $post_id ): array {
		$post = get_post( $post_id );
		if ( ! $post || empty( $post->post_content ) ) {
			return [];
		}

		$urls = [];
		if ( preg_match_all( '/<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>/i', $post->post_content, $matches ) ) {
			foreach ( $matches[1] as $url ) {
				$url = trim( $url );
				// Skip anchors, mailto, tel, javascript.
				if ( '' === $url || '#' === $url[0] || 0 === strpos( $url, 'mailto:' ) || 0 === strpos( $url, 'tel:' ) || 0 === strpos( $url, 'javascript:' ) ) {
					continue;
				}
				// Resolve relative URLs.
				if ( 0 !== strpos( $url, 'http' ) ) {
					$url = home_url( $url );
				}
				$urls[] = $url;
			}
		}

		return array_unique( $urls );
	}

	/**
	 * Check a single URL.
	 *
	 * Sends HEAD first. When the answer is one that servers often give to HEAD
	 * or to automated clients, tries once more with GET. A site that refuses
	 * automated requests (401, 403, 429) is reported as blocked, not broken.
	 *
	 * @param string $url URL to check.
	 * @return array{url: string, status: int, broken: bool, blocked: bool, error: string}
	 */
	public function check_url( string $url ): array {
		$args = [
			'timeout'     => 10,
			'redirection' => 3,
			'user-agent'  => 'Mozilla/5.0 (compatible; JAT-Search-Intel-Link-Checker/' . ( defined( 'JAT_SEARCH_INTEL_VERSION' ) ? JAT_SEARCH_INTEL_VERSION : '1' ) . '; +' . home_url( '/' ) . ')',
		];

		$response = wp_remote_head( $url, $args );
		$status   = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );

		if ( in_array( $status, self::RETRY_WITH_GET, true ) ) {
			$response = wp_remote_get( $url, $args + [ 'limit_response_size' => 65536 ] );
			$status   = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
		}

		return [
			'url'     => $url,
			'status'  => $status,
			'broken'  => in_array( $status, self::BROKEN_CODES, true ),
			'blocked' => in_array( $status, self::BLOCKED_CODES, true ),
			'error'   => is_wp_error( $response ) ? $response->get_error_message() : '',
		];
	}

	/**
	 * Scan a specific post and return broken links.
	 *
	 * @param int $post_id Post ID.
	 * @return array List of broken link results.
	 */
	public function scan_post( int $post_id ): array {
		$urls    = $this->extract_links( $post_id );
		$broken  = [];

		foreach ( $urls as $url ) {
			$result = $this->check_url( $url );
			if ( $result['broken'] ) {
				$result['post_id'] = $post_id;
				$broken[] = $result;
			}
		}

		return $broken;
	}

	/**
	 * Run a batch check via WP-Cron.
	 *
	 * Picks the next batch of posts to scan and stores results.
	 */
	public function run_batch_check(): void {
		$offset = (int) get_option( 'jat_search_intel_link_checker_offset', 0 );
		$posts  = get_posts( [
			'post_type'      => $this->settings['enabled_post_types'] ?? [ 'post', 'page' ],
			'post_status'    => 'publish',
			'posts_per_page' => self::BATCH_SIZE,
			'offset'         => $offset,
			'fields'         => 'ids',
			'orderby'        => 'ID',
			'order'          => 'ASC',
		] );

		if ( empty( $posts ) ) {
			// Reset offset — full cycle complete.
			update_option( 'jat_search_intel_link_checker_offset', 0, false );
			update_option( self::LAST_SCAN_KEY, current_time( 'mysql' ), false );
			return;
		}

		$existing = $this->get_broken_links();

		foreach ( $posts as $post_id ) {
			// Remove old results for this post.
			$existing = array_filter( $existing, function ( $item ) use ( $post_id ) {
				return ( $item['post_id'] ?? 0 ) !== $post_id;
			} );

			$broken = $this->scan_post( $post_id );
			foreach ( $broken as $item ) {
				$item['checked_at'] = current_time( 'mysql' );
				$existing[] = $item;
			}
		}

		update_option( self::OPTION_KEY, array_values( $existing ), false );
		update_option( 'jat_search_intel_link_checker_offset', $offset + self::BATCH_SIZE, false );
	}

	/**
	 * Get all stored broken links.
	 *
	 * @return array
	 */
	public function get_broken_links(): array {
		return get_option( self::OPTION_KEY, [] );
	}

	/**
	 * Clear all stored broken links.
	 */
	public function clear_results(): void {
		delete_option( self::OPTION_KEY );
		delete_option( 'jat_search_intel_link_checker_offset' );
	}

	/**
	 * Get the last full scan timestamp.
	 *
	 * @return string
	 */
	public function get_last_scan_time(): string {
		return get_option( self::LAST_SCAN_KEY, '' );
	}

	/**
	 * Register admin submenu page.
	 */
	public function register_admin_page(): void {
		add_submenu_page(
			'jat-search-intel',
			__( 'Broken Links', 'jat-search-intel' ),
			__( 'Broken Links', 'jat-search-intel' ),
			'manage_options',
			'jat-search-intel-broken-links',
			[ $this, 'render_admin_page' ]
		);
	}

	/**
	 * "Check the next batch now" button.
	 */
	public function handle_check_now(): void {
		check_admin_referer( 'jat_search_intel_link_check_now' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to run the link checker.', 'jat-search-intel' ), '', [ 'response' => 403 ] );
		}
		$this->run_batch_check();
		wp_safe_redirect( admin_url( 'admin.php?page=jat-search-intel-broken-links&jsi_checked=1' ) );
		exit;
	}

	/**
	 * Render the admin page.
	 */
	public function render_admin_page(): void {
		$links     = $this->get_broken_links();
		$last_scan = $this->get_last_scan_time();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Broken Link Checker', 'jat-search-intel' ); ?></h1>
			<p>
				<?php
				if ( $last_scan ) {
					printf(
						/* translators: %1$s: value, %2$d: number. */
						esc_html__( 'Last full scan: %1$s. Found %2$d broken links.', 'jat-search-intel' ),
						'<strong>' . esc_html( $last_scan ) . '</strong>',
						count( $links )
					);
				} else {
					esc_html_e( 'No scan completed yet. The checker runs hourly via WP-Cron.', 'jat-search-intel' );
				}
				?>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="jat_search_intel_link_check_now" />
				<?php wp_nonce_field( 'jat_search_intel_link_check_now' ); ?>
				<?php
				/* translators: %d: number of posts checked per batch. */
				submit_button( sprintf( __( 'Check the next %d posts now', 'jat-search-intel' ), self::BATCH_SIZE ), 'secondary', 'submit', false );
				?>
			</form>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th style="width:10%"><?php esc_html_e( 'Status', 'jat-search-intel' ); ?></th>
						<th style="width:40%"><?php esc_html_e( 'URL', 'jat-search-intel' ); ?></th>
						<th style="width:25%"><?php esc_html_e( 'Found In', 'jat-search-intel' ); ?></th>
						<th style="width:15%"><?php esc_html_e( 'Error', 'jat-search-intel' ); ?></th>
						<th style="width:10%"><?php esc_html_e( 'Checked', 'jat-search-intel' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( empty( $links ) ) : ?>
					<tr><td colspan="5"><?php esc_html_e( 'No broken links found.', 'jat-search-intel' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $links as $link ) : ?>
						<tr>
							<td><code><?php echo esc_html( (string) $link['status'] ); ?></code></td>
							<td><a href="<?php echo esc_url( $link['url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $link['url'] ); ?></a></td>
							<td>
								<?php
								$post_id = $link['post_id'] ?? 0;
								if ( $post_id ) {
									echo '<a href="' . esc_url( get_permalink( $post_id ) ) . '">' . esc_html( get_the_title( $post_id ) ) . '</a>';
								}
								?>
							</td>
							<td><?php echo esc_html( $link['error'] ?? '' ); ?></td>
							<td><?php echo esc_html( $link['checked_at'] ?? '' ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
