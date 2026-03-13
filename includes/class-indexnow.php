<?php
namespace Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * IndexNow Integration — ping search engines on publish/update.
 *
 * @since 2.0.0
 */
class IndexNow {

	/**
	 * IndexNow API endpoints by provider.
	 *
	 * @var array<string,string>
	 */
	public const ENDPOINTS = [
		'bing'   => 'https://www.bing.com/indexnow',
		'yandex' => 'https://yandex.com/indexnow',
		'naver'  => 'https://searchadvisor.naver.com/indexnow',
	];

	/**
	 * Option key for the ping log.
	 */
	private const LOG_OPTION = 'search_intel_indexnow_log';

	/**
	 * Max log entries to keep.
	 */
	private const MAX_LOG_ENTRIES = 100;

	/** @var array */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param array $settings Plugin settings.
	 */
	public function __construct( array $settings ) {
		$this->settings = $settings;

		if ( ! empty( $settings['enable_indexnow'] ) && ! empty( $settings['indexnow_api_key'] ) ) {
			add_action( 'publish_post', [ $this, 'ping_on_publish' ] );
			add_action( 'publish_page', [ $this, 'ping_on_publish' ] );
			add_action( 'post_updated', [ $this, 'ping_on_update' ], 10, 3 );
		}

		// Serve the API key verification file.
		add_action( 'init', [ $this, 'serve_key_file' ] );
	}

	/**
	 * Ping IndexNow when a post is published.
	 *
	 * @param int $post_id Post ID.
	 */
	public function ping_on_publish( int $post_id ): void {
		if ( wp_is_post_revision( $post_id ) || defined( 'DOING_AUTOSAVE' ) ) {
			return;
		}

		$url = get_permalink( $post_id );
		if ( $url ) {
			$this->submit_url( $url );
		}
	}

	/**
	 * Ping IndexNow when a published post is updated.
	 *
	 * @param int      $post_id     Post ID.
	 * @param \WP_Post $post_after  Post after update.
	 * @param \WP_Post $post_before Post before update.
	 */
	public function ping_on_update( int $post_id, $post_after, $post_before ): void {
		if ( wp_is_post_revision( $post_id ) || defined( 'DOING_AUTOSAVE' ) ) {
			return;
		}

		// Only ping if the post was already published (not a new publish).
		if ( 'publish' !== $post_before->post_status || 'publish' !== $post_after->post_status ) {
			return;
		}

		$url = get_permalink( $post_id );
		if ( $url ) {
			$this->submit_url( $url );
		}
	}

	/**
	 * Submit a URL to all enabled IndexNow providers.
	 *
	 * @param string $url The URL to submit.
	 * @return array
	 */
	public function submit_url( string $url ): array {
		$api_key = $this->settings['indexnow_api_key'] ?? '';
		if ( empty( $api_key ) ) {
			return [];
		}

		$providers = $this->settings['indexnow_providers'] ?? array_keys( self::ENDPOINTS );
		$results   = [];

		foreach ( $providers as $provider ) {
			$endpoint = self::ENDPOINTS[ $provider ] ?? null;
			if ( ! $endpoint ) {
				continue;
			}

			$request_url = add_query_arg( [
				'url' => $url,
				'key' => $api_key,
			], $endpoint );

			$response = wp_remote_get( $request_url, [
				'timeout'  => 10,
				'blocking' => true,
			] );

			$status  = wp_remote_retrieve_response_code( $response );
			$success = $status >= 200 && $status < 300;

			$results[ $provider ] = [
				'success' => $success,
				'status'  => $status,
				'message' => $success ? 'OK' : wp_remote_retrieve_response_message( $response ),
			];
		}

		$this->add_log_entry( $url, $results );

		return $results;
	}

	/**
	 * Serve the IndexNow API key verification file.
	 */
	public function serve_key_file(): void {
		$api_key = $this->settings['indexnow_api_key'] ?? '';
		if ( empty( $api_key ) ) {
			return;
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$expected    = '/' . $api_key . '.txt';

		if ( $request_uri === $expected ) {
			header( 'Content-Type: text/plain; charset=utf-8' );
			echo esc_html( $api_key );
			exit;
		}
	}

	/**
	 * Get the ping log.
	 *
	 * @return array
	 */
	public function get_log(): array {
		return get_option( self::LOG_OPTION, [] );
	}

	/**
	 * Clear the ping log.
	 */
	public function clear_log(): void {
		delete_option( self::LOG_OPTION );
	}

	/**
	 * Add an entry to the ping log.
	 *
	 * @param string $url     The URL that was pinged.
	 * @param array  $results Results per provider.
	 */
	private function add_log_entry( string $url, array $results ): void {
		$log = $this->get_log();

		array_unshift( $log, [
			'url'       => $url,
			'results'   => $results,
			'timestamp' => current_time( 'mysql' ),
		] );

		// Trim to max entries.
		$log = array_slice( $log, 0, self::MAX_LOG_ENTRIES );

		update_option( self::LOG_OPTION, $log, false );
	}
}
