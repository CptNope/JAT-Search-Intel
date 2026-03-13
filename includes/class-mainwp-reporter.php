<?php
namespace Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MainWP Child-Side Reporter.
 *
 * Hooks into the MainWP Child plugin's callback API to expose
 * Search Intel site scores, feature status, post counts, and
 * remote action triggers (rescore, regenerate, toggle features).
 *
 * Requires the MainWP Child plugin to be active on the child site.
 *
 * @since 3.1.0
 */
class MainWP_Reporter {

	/** @var array */
	private $settings;

	/** Callback action prefix. */
	private const PREFIX = 'search_intel';

	/**
	 * @param array $settings Plugin settings.
	 */
	public function __construct( array $settings ) {
		$this->settings = $settings;

		if ( ! $this->is_mainwp_child_active() ) {
			return;
		}

		add_filter( 'mainwp_child_extra_execution', [ $this, 'handle_callback' ], 10, 2 );
		add_filter( 'mainwp_site_sync_others_data', [ $this, 'inject_sync_data' ], 10, 2 );
	}

	// ── Detection ────────────────────────────────────────────────────────────

	/**
	 * Check if MainWP Child plugin is active.
	 *
	 * @return bool
	 */
	public function is_mainwp_child_active(): bool {
		return defined( 'MAINWP_CHILD_FILE' ) || class_exists( 'MainWP\Child\MainWP_Child' );
	}

	// ── Sync Data ────────────────────────────────────────────────────────────

	/**
	 * Inject Search Intel data into the MainWP sync payload.
	 *
	 * This data is sent to the MainWP Dashboard on every sync, making it
	 * available for the dashboard extension's scores grid.
	 *
	 * @param array $data    Existing sync data.
	 * @param array $options Sync options (unused).
	 * @return array
	 */
	public function inject_sync_data( array $data, array $options = [] ): array {
		$data[ self::PREFIX ] = $this->get_site_summary();
		return $data;
	}

	/**
	 * Get a summary of the site's Search Intel status and scores.
	 *
	 * @return array
	 */
	public function get_site_summary(): array {
		$core = Core::get_instance();

		return [
			'version'        => defined( 'SEARCH_INTEL_VERSION' ) ? SEARCH_INTEL_VERSION : '0.0.0',
			'features'       => $this->get_feature_status(),
			'scores'         => $this->get_aggregate_scores(),
			'post_count'     => $this->get_scored_post_count(),
			'last_analysis'  => $this->get_last_analysis_date(),
			'queue_pending'  => $this->get_pending_queue_count(),
		];
	}

	/**
	 * Get enabled/disabled status of all major features.
	 *
	 * @return array<string, bool>
	 */
	public function get_feature_status(): array {
		return [
			'seo'          => ! empty( $this->settings['enable_seo'] ),
			'aeo'          => ! empty( $this->settings['enable_aeo'] ),
			'geo'          => ! empty( $this->settings['enable_geo'] ),
			'schema'       => ! empty( $this->settings['enable_schema'] ),
			'sitemap'      => ! empty( $this->settings['enable_sitemap'] ),
			'redirects'    => ! empty( $this->settings['enable_redirects'] ),
			'indexnow'     => ! empty( $this->settings['enable_indexnow'] ),
			'woocommerce'  => ! empty( $this->settings['enable_woocommerce'] ),
			'rbac'         => ! empty( $this->settings['enable_rbac'] ),
			'white_label'  => ! empty( $this->settings['enable_white_label'] ),
			'scale'        => ! empty( $this->settings['enable_scale'] ),
			'multilang'    => ! empty( $this->settings['enable_multilang'] ),
		];
	}

	/**
	 * Get aggregate scores across all published posts.
	 *
	 * @return array{avg_seo: float, avg_aeo: float, avg_geo: float, avg_readability: float}
	 */
	public function get_aggregate_scores(): array {
		$posts = get_posts( [
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		] );

		if ( empty( $posts ) ) {
			return [
				'avg_seo'         => 0.0,
				'avg_aeo'         => 0.0,
				'avg_geo'         => 0.0,
				'avg_readability' => 0.0,
			];
		}

		$seo = $aeo = $geo = $read = 0;
		$count = count( $posts );

		foreach ( $posts as $post_id ) {
			$seo  += (int) get_post_meta( $post_id, '_search_intel_seo_score', true );
			$aeo  += (int) get_post_meta( $post_id, '_search_intel_aeo_score', true );
			$geo  += (int) get_post_meta( $post_id, '_search_intel_geo_score', true );
			$read += (int) get_post_meta( $post_id, '_search_intel_readability_score', true );
		}

		return [
			'avg_seo'         => round( $seo / $count, 1 ),
			'avg_aeo'         => round( $aeo / $count, 1 ),
			'avg_geo'         => round( $geo / $count, 1 ),
			'avg_readability' => round( $read / $count, 1 ),
		];
	}

	/**
	 * Count posts that have at least one Search Intel score.
	 *
	 * @return int
	 */
	public function get_scored_post_count(): int {
		$posts = get_posts( [
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => [
				[
					'key'     => '_search_intel_seo_score',
					'value'   => '0',
					'compare' => '>',
					'type'    => 'NUMERIC',
				],
			],
		] );

		return count( $posts );
	}

	/**
	 * Get the date of the most recent analysis.
	 *
	 * @return string ISO 8601 date or empty string.
	 */
	public function get_last_analysis_date(): string {
		$posts = get_posts( [
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'orderby'        => 'modified',
			'order'          => 'DESC',
			'meta_key'       => '_search_intel_seo_score',
		] );

		if ( empty( $posts ) ) {
			return '';
		}

		return get_post_modified_time( 'c', true, $posts[0] );
	}

	/**
	 * Get count of pending background queue jobs.
	 *
	 * @return int
	 */
	public function get_pending_queue_count(): int {
		$queue = get_option( 'search_intel_bg_queue', [] );
		if ( ! is_array( $queue ) ) {
			return 0;
		}

		return count( array_filter( $queue, function ( $job ) {
			return ( $job['status'] ?? '' ) === 'pending';
		} ) );
	}

	// ── Remote Actions ───────────────────────────────────────────────────────

	/**
	 * Handle remote action callbacks from the MainWP Dashboard extension.
	 *
	 * @param array $result  Current result.
	 * @param array $message Callback message from the dashboard.
	 * @return array
	 */
	public function handle_callback( array $result, array $message ): array {
		$action = $message['action'] ?? '';

		if ( strpos( $action, self::PREFIX . '_' ) !== 0 ) {
			return $result;
		}

		$command = substr( $action, strlen( self::PREFIX . '_' ) );

		switch ( $command ) {
			case 'get_summary':
				$result['data']    = $this->get_site_summary();
				$result['result']  = 'success';
				break;

			case 'rescore_all':
				$result['data']    = $this->remote_rescore();
				$result['result']  = 'success';
				break;

			case 'regenerate_all':
				$result['data']    = $this->remote_regenerate();
				$result['result']  = 'success';
				break;

			case 'toggle_feature':
				$feature = $message['feature'] ?? '';
				$enabled = ! empty( $message['enabled'] );
				$result['data']    = $this->remote_toggle_feature( $feature, $enabled );
				$result['result']  = 'success';
				break;

			case 'push_settings':
				$settings = $message['settings'] ?? [];
				$result['data']    = $this->remote_push_settings( $settings );
				$result['result']  = 'success';
				break;

			case 'get_low_scores':
				$threshold = (int) ( $message['threshold'] ?? 50 );
				$result['data']    = $this->get_low_score_posts( $threshold );
				$result['result']  = 'success';
				break;
		}

		return $result;
	}

	/**
	 * Remote rescore: enqueue all published posts for background rescoring.
	 *
	 * @return array
	 */
	private function remote_rescore(): array {
		$core  = Core::get_instance();
		$posts = get_posts( [
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		] );

		$queued = 0;
		foreach ( $posts as $post_id ) {
			$core->scale->enqueue_job( 'rescore', [ 'post_id' => $post_id ] );
			$queued++;
		}

		return [ 'queued' => $queued ];
	}

	/**
	 * Remote regenerate: enqueue all posts for metadata regeneration.
	 *
	 * @return array
	 */
	private function remote_regenerate(): array {
		$core  = Core::get_instance();
		$posts = get_posts( [
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		] );

		$queued = 0;
		foreach ( $posts as $post_id ) {
			$core->scale->enqueue_job( 'regenerate', [ 'post_id' => $post_id ] );
			$queued++;
		}

		return [ 'queued' => $queued ];
	}

	/**
	 * Remote toggle: enable or disable a feature.
	 *
	 * @param string $feature Feature key (e.g. 'enable_seo', 'enable_schema').
	 * @param bool   $enabled Whether to enable or disable.
	 * @return array
	 */
	private function remote_toggle_feature( string $feature, bool $enabled ): array {
		$settings = get_option( 'search_intel_settings', [] );
		$settings = is_array( $settings ) ? $settings : [];

		// Validate feature key.
		$allowed = [
			'enable_seo', 'enable_aeo', 'enable_geo', 'enable_schema',
			'enable_sitemap', 'enable_redirects', 'enable_indexnow',
			'enable_woocommerce', 'enable_rbac', 'enable_white_label',
			'enable_scale', 'enable_multilang',
		];

		if ( ! in_array( $feature, $allowed, true ) ) {
			return [ 'error' => 'Invalid feature key: ' . $feature ];
		}

		$settings[ $feature ] = $enabled;
		update_option( 'search_intel_settings', $settings );

		return [ 'feature' => $feature, 'enabled' => $enabled ];
	}

	/**
	 * Remote push settings: overwrite plugin settings from the dashboard.
	 *
	 * @param array $settings Settings to import.
	 * @return array
	 */
	private function remote_push_settings( array $settings ): array {
		if ( empty( $settings ) ) {
			return [ 'error' => 'No settings provided.' ];
		}

		$defaults = Core::get_default_settings();
		$current  = get_option( 'search_intel_settings', [] );
		$current  = is_array( $current ) ? $current : [];

		foreach ( $settings as $key => $value ) {
			if ( ! array_key_exists( $key, $defaults ) ) {
				continue;
			}
			$current[ $key ] = $value;
		}

		update_option( 'search_intel_settings', $current );

		return [ 'imported' => count( $settings ) ];
	}

	/**
	 * Get posts with scores below a threshold.
	 *
	 * @param int $threshold Score threshold.
	 * @return array
	 */
	private function get_low_score_posts( int $threshold ): array {
		$posts = get_posts( [
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => 50,
			'meta_key'       => '_search_intel_seo_score',
			'orderby'        => 'meta_value_num',
			'order'          => 'ASC',
		] );

		$low = [];
		foreach ( $posts as $post ) {
			$seo  = (int) get_post_meta( $post->ID, '_search_intel_seo_score', true );
			$aeo  = (int) get_post_meta( $post->ID, '_search_intel_aeo_score', true );
			$geo  = (int) get_post_meta( $post->ID, '_search_intel_geo_score', true );

			if ( $seo < $threshold || $aeo < $threshold || $geo < $threshold ) {
				$low[] = [
					'post_id' => $post->ID,
					'title'   => $post->post_title,
					'seo'     => $seo,
					'aeo'     => $aeo,
					'geo'     => $geo,
				];
			}
		}

		return $low;
	}
}
