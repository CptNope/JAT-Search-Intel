<?php
namespace Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Scale and performance features for Search Intel.
 *
 * Object caching support, background processing queue, bulk editor,
 * CDN-friendly output, and headless WordPress REST API support.
 *
 * @since 3.0.0
 */
class Scale {

	/** @var array */
	private $settings;

	/** Queue option name. */
	private const QUEUE_OPTION = 'search_intel_bg_queue';

	/**
	 * @param array $settings Plugin settings.
	 */
	public function __construct( array $settings ) {
		$this->settings = $settings;

		if ( empty( $settings['enable_scale'] ) ) {
			return;
		}

		add_action( 'search_intel_process_queue', [ $this, 'process_queue' ] );
		add_action( 'rest_api_init', [ $this, 'register_headless_routes' ] );
		add_action( 'admin_menu', [ $this, 'register_admin_menu' ] );
		add_action( 'wp_ajax_search_intel_bulk_edit', [ $this, 'ajax_bulk_edit' ] );

		// Schedule queue processor if not already.
		if ( ! wp_next_scheduled( 'search_intel_process_queue' ) ) {
			wp_schedule_event( time(), 'every_five_minutes', 'search_intel_process_queue' );
		}
	}

	// ── Object Cache ─────────────────────────────────────────────────────────

	/**
	 * Get a cached value, using object cache if available, transients as fallback.
	 *
	 * @param string $key   Cache key.
	 * @param string $group Cache group.
	 * @return mixed|false Cached value or false.
	 */
	public function cache_get( string $key, string $group = 'search_intel' ) {
		if ( $this->has_object_cache() ) {
			return wp_cache_get( $key, $group );
		}
		return get_transient( 'search_intel_' . $key );
	}

	/**
	 * Set a cached value.
	 *
	 * @param string $key    Cache key.
	 * @param mixed  $value  Value to cache.
	 * @param int    $expiry Expiry in seconds.
	 * @param string $group  Cache group.
	 * @return bool
	 */
	public function cache_set( string $key, $value, int $expiry = 3600, string $group = 'search_intel' ): bool {
		if ( $this->has_object_cache() ) {
			return wp_cache_set( $key, $value, $group, $expiry );
		}
		return set_transient( 'search_intel_' . $key, $value, $expiry );
	}

	/**
	 * Delete a cached value.
	 *
	 * @param string $key   Cache key.
	 * @param string $group Cache group.
	 * @return bool
	 */
	public function cache_delete( string $key, string $group = 'search_intel' ): bool {
		if ( $this->has_object_cache() ) {
			return wp_cache_delete( $key, $group );
		}
		return delete_transient( 'search_intel_' . $key );
	}

	/**
	 * Check if a persistent object cache is available (Redis/Memcached).
	 *
	 * @return bool
	 */
	public function has_object_cache(): bool {
		return wp_using_ext_object_cache();
	}

	// ── Background Processing Queue ──────────────────────────────────────────

	/**
	 * Add a job to the background queue.
	 *
	 * @param string $type Job type (e.g., 'rescore', 'regenerate', 'link_graph').
	 * @param array  $data Job data.
	 * @return string Job ID.
	 */
	public function enqueue_job( string $type, array $data = [] ): string {
		$queue = get_option( self::QUEUE_OPTION, [] );
		$queue = is_array( $queue ) ? $queue : [];

		$job_id = wp_generate_uuid4();
		$queue[] = [
			'id'      => $job_id,
			'type'    => $type,
			'data'    => $data,
			'status'  => 'pending',
			'created' => current_time( 'mysql' ),
		];

		update_option( self::QUEUE_OPTION, $queue, false );
		return $job_id;
	}

	/**
	 * Get the current queue.
	 *
	 * @return array
	 */
	public function get_queue(): array {
		$queue = get_option( self::QUEUE_OPTION, [] );
		return is_array( $queue ) ? $queue : [];
	}

	/**
	 * Process the next batch of queued jobs.
	 *
	 * @param int $batch_size Number of jobs to process.
	 * @return int Number of jobs processed.
	 */
	public function process_queue( int $batch_size = 5 ): int {
		$queue     = $this->get_queue();
		$processed = 0;

		foreach ( $queue as &$job ) {
			if ( $job['status'] !== 'pending' ) {
				continue;
			}

			$job['status']  = 'processing';
			$job['started'] = current_time( 'mysql' );

			$success = $this->execute_job( $job );

			$job['status']    = $success ? 'completed' : 'failed';
			$job['completed'] = current_time( 'mysql' );

			$processed++;
			if ( $processed >= $batch_size ) {
				break;
			}
		}

		// Remove completed/failed jobs older than 1 hour.
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - 3600 );
		$queue  = array_values( array_filter( $queue, function ( $job ) use ( $cutoff ) {
			if ( in_array( $job['status'], [ 'completed', 'failed' ], true ) ) {
				return ( $job['completed'] ?? '' ) > $cutoff;
			}
			return true;
		} ) );

		update_option( self::QUEUE_OPTION, $queue, false );
		return $processed;
	}

	/**
	 * Execute a single queued job.
	 *
	 * @param array $job Job data.
	 * @return bool
	 */
	private function execute_job( array $job ): bool {
		$core = Core::get_instance();

		switch ( $job['type'] ) {
			case 'rescore':
				$post_id = (int) ( $job['data']['post_id'] ?? 0 );
				if ( $post_id <= 0 ) {
					return false;
				}
				$post = get_post( $post_id );
				if ( ! $post ) {
					return false;
				}
				$content     = wp_strip_all_tags( $post->post_content );
				$keyword     = (string) get_post_meta( $post_id, '_search_intel_focus_keyword', true );
				$description = (string) get_post_meta( $post_id, '_search_intel_description', true );
				$results     = $core->analyzer->analyze( $post_id, $content, $post->post_title, $keyword, $description );
				if ( isset( $results['seo']['score'] ) ) {
					update_post_meta( $post_id, '_search_intel_seo_score', (int) $results['seo']['score'] );
				}
				if ( isset( $results['aeo']['score'] ) ) {
					update_post_meta( $post_id, '_search_intel_aeo_score', (int) $results['aeo']['score'] );
				}
				if ( isset( $results['geo']['score'] ) ) {
					update_post_meta( $post_id, '_search_intel_geo_score', (int) $results['geo']['score'] );
				}
				return true;

			case 'regenerate':
				$post_id = (int) ( $job['data']['post_id'] ?? 0 );
				if ( $post_id <= 0 ) {
					return false;
				}
				$post = get_post( $post_id );
				if ( ! $post ) {
					return false;
				}
				$desc = get_post_meta( $post_id, '_search_intel_description', true );
				if ( empty( $desc ) ) {
					$auto = $core->seo->get_description( $post_id );
					if ( $auto ) {
						update_post_meta( $post_id, '_search_intel_description', sanitize_text_field( $auto ) );
					}
				}
				return true;

			case 'link_graph':
				$post_id = (int) ( $job['data']['post_id'] ?? 0 );
				if ( $post_id <= 0 ) {
					return false;
				}
				$post = get_post( $post_id );
				if ( $post ) {
					$core->citation_graph->update_post_graph( $post_id, $post );
				}
				return true;

			default:
				return false;
		}
	}

	// ── Bulk Editor ──────────────────────────────────────────────────────────

	/**
	 * Get posts for the bulk editor table.
	 *
	 * @param array $args Query arguments.
	 * @return array{posts: array, total: int}
	 */
	public function get_bulk_editor_data( array $args = [] ): array {
		$defaults = [
			'post_type'      => 'post',
			'posts_per_page' => 25,
			'paged'          => 1,
			'orderby'        => 'ID',
			'order'          => 'DESC',
		];
		$args = array_merge( $defaults, $args );

		$query = new \WP_Query( array_merge( $args, [ 'post_status' => 'publish' ] ) );

		$rows = [];
		if ( $query->have_posts() ) {
			foreach ( $query->posts as $post ) {
				$rows[] = [
					'ID'          => $post->ID,
					'title'       => $post->post_title,
					'seo_title'   => (string) get_post_meta( $post->ID, '_search_intel_title', true ),
					'description' => (string) get_post_meta( $post->ID, '_search_intel_description', true ),
					'keyword'     => (string) get_post_meta( $post->ID, '_search_intel_focus_keyword', true ),
					'seo_score'   => (int) get_post_meta( $post->ID, '_search_intel_seo_score', true ),
					'aeo_score'   => (int) get_post_meta( $post->ID, '_search_intel_aeo_score', true ),
					'geo_score'   => (int) get_post_meta( $post->ID, '_search_intel_geo_score', true ),
				];
			}
		}

		return [
			'posts' => $rows,
			'total' => (int) $query->found_posts,
		];
	}

	/**
	 * Bulk update meta fields for multiple posts.
	 *
	 * @param array $updates Array of [ post_id => [ field => value ] ].
	 * @return int Number of posts updated.
	 */
	public function bulk_update_meta( array $updates ): int {
		$allowed = [
			'title'       => '_search_intel_title',
			'description' => '_search_intel_description',
			'keyword'     => '_search_intel_focus_keyword',
		];

		$count = 0;
		foreach ( $updates as $post_id => $fields ) {
			$post_id = absint( $post_id );
			if ( $post_id <= 0 ) {
				continue;
			}

			$updated = false;
			foreach ( $fields as $field => $value ) {
				$meta_key = $allowed[ $field ] ?? '';
				if ( empty( $meta_key ) ) {
					continue;
				}
				update_post_meta( $post_id, $meta_key, sanitize_text_field( $value ) );
				$updated = true;
			}

			if ( $updated ) {
				$count++;
			}
		}

		return $count;
	}

	/**
	 * AJAX handler for bulk editing.
	 */
	public function ajax_bulk_edit(): void {
		check_ajax_referer( 'search_intel_bulk_edit', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions.' );
		}

		$updates = $_POST['updates'] ?? [];
		if ( ! is_array( $updates ) ) {
			wp_send_json_error( 'Invalid data.' );
		}

		$count = $this->bulk_update_meta( $updates );
		wp_send_json_success( [ 'updated' => $count ] );
	}

	// ── Headless REST API ────────────────────────────────────────────────────

	/**
	 * Register REST API routes for headless WordPress support.
	 */
	public function register_headless_routes(): void {
		register_rest_route( 'search-intel/v1', '/seo/(?P<id>\d+)', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'rest_get_seo_data' ],
			'permission_callback' => '__return_true',
			'args'                => [
				'id' => [
					'validate_callback' => function ( $param ) {
						return is_numeric( $param );
					},
				],
			],
		] );

		register_rest_route( 'search-intel/v1', '/aeo/(?P<id>\d+)', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'rest_get_aeo_data' ],
			'permission_callback' => '__return_true',
			'args'                => [
				'id' => [
					'validate_callback' => function ( $param ) {
						return is_numeric( $param );
					},
				],
			],
		] );

		register_rest_route( 'search-intel/v1', '/scores/(?P<id>\d+)', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'rest_get_scores' ],
			'permission_callback' => '__return_true',
			'args'                => [
				'id' => [
					'validate_callback' => function ( $param ) {
						return is_numeric( $param );
					},
				],
			],
		] );

		register_rest_route( 'search-intel/v1', '/meta/(?P<id>\d+)', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'rest_get_all_meta' ],
			'permission_callback' => '__return_true',
			'args'                => [
				'id' => [
					'validate_callback' => function ( $param ) {
						return is_numeric( $param );
					},
				],
			],
		] );
	}

	/**
	 * REST callback: get SEO data for a post.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function rest_get_seo_data( $request ): \WP_REST_Response {
		$post_id = (int) $request['id'];
		$post    = get_post( $post_id );

		if ( ! $post || $post->post_status !== 'publish' ) {
			return new \WP_REST_Response( [ 'error' => 'Post not found' ], 404 );
		}

		return new \WP_REST_Response( [
			'id'          => $post_id,
			'title'       => (string) get_post_meta( $post_id, '_search_intel_title', true ) ?: $post->post_title,
			'description' => (string) get_post_meta( $post_id, '_search_intel_description', true ),
			'canonical'   => (string) get_post_meta( $post_id, '_search_intel_canonical', true ) ?: get_permalink( $post_id ),
			'robots'      => (string) get_post_meta( $post_id, '_search_intel_robots', true ),
			'keyword'     => (string) get_post_meta( $post_id, '_search_intel_focus_keyword', true ),
			'og_title'    => (string) get_post_meta( $post_id, '_search_intel_og_title', true ),
			'og_desc'     => (string) get_post_meta( $post_id, '_search_intel_og_description', true ),
			'og_image'    => (string) get_post_meta( $post_id, '_search_intel_og_image', true ),
		] );
	}

	/**
	 * REST callback: get AEO data for a post.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function rest_get_aeo_data( $request ): \WP_REST_Response {
		$post_id = (int) $request['id'];
		$post    = get_post( $post_id );

		if ( ! $post || $post->post_status !== 'publish' ) {
			return new \WP_REST_Response( [ 'error' => 'Post not found' ], 404 );
		}

		return new \WP_REST_Response( [
			'id'            => $post_id,
			'ai_summary'    => (string) get_post_meta( $post_id, '_search_intel_ai_summary', true ),
			'content_type'  => (string) get_post_meta( $post_id, '_search_intel_content_type', true ),
			'direct_answer' => (string) get_post_meta( $post_id, '_search_intel_direct_answer', true ),
		] );
	}

	/**
	 * REST callback: get all scores for a post.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function rest_get_scores( $request ): \WP_REST_Response {
		$post_id = (int) $request['id'];
		$post    = get_post( $post_id );

		if ( ! $post || $post->post_status !== 'publish' ) {
			return new \WP_REST_Response( [ 'error' => 'Post not found' ], 404 );
		}

		return new \WP_REST_Response( [
			'id'          => $post_id,
			'seo'         => (int) get_post_meta( $post_id, '_search_intel_seo_score', true ),
			'aeo'         => (int) get_post_meta( $post_id, '_search_intel_aeo_score', true ),
			'geo'         => (int) get_post_meta( $post_id, '_search_intel_geo_score', true ),
			'readability' => (int) get_post_meta( $post_id, '_search_intel_readability_score', true ),
		] );
	}

	/**
	 * REST callback: get all SEO/AEO/GEO meta for a post.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function rest_get_all_meta( $request ): \WP_REST_Response {
		$post_id = (int) $request['id'];
		$post    = get_post( $post_id );

		if ( ! $post || $post->post_status !== 'publish' ) {
			return new \WP_REST_Response( [ 'error' => 'Post not found' ], 404 );
		}

		$seo = $this->rest_get_seo_data( $request )->get_data();
		$aeo = $this->rest_get_aeo_data( $request )->get_data();
		$scores = $this->rest_get_scores( $request )->get_data();

		return new \WP_REST_Response( [
			'id'     => $post_id,
			'seo'    => $seo,
			'aeo'    => $aeo,
			'scores' => $scores,
		] );
	}

	// ── CDN-Friendly ─────────────────────────────────────────────────────────

	/**
	 * Get a cache-busted asset URL using file modification time instead of query strings.
	 *
	 * @param string $path Relative path to asset.
	 * @return string Versioned URL.
	 */
	public function get_asset_url( string $path ): string {
		$dir  = defined( 'SEARCH_INTEL_DIR' ) ? SEARCH_INTEL_DIR : '';
		$url  = defined( 'SEARCH_INTEL_URL' ) ? SEARCH_INTEL_URL : '';
		$file = $dir . $path;

		if ( file_exists( $file ) ) {
			$version = filemtime( $file );
			$ext     = pathinfo( $path, PATHINFO_EXTENSION );
			$base    = substr( $path, 0, -( strlen( $ext ) + 1 ) );
			return $url . $base . '.' . $version . '.' . $ext;
		}

		return $url . $path;
	}

	// ── Admin Page ───────────────────────────────────────────────────────────

	/**
	 * Register admin menu.
	 */
	public function register_admin_menu(): void {
		add_submenu_page(
			'search-intel',
			'Bulk Editor',
			'Bulk Editor',
			'manage_options',
			'search-intel-bulk-editor',
			[ $this, 'render_admin_page' ]
		);
	}

	/**
	 * Render the bulk editor admin page.
	 */
	public function render_admin_page(): void {
		$data = $this->get_bulk_editor_data( [
			'paged' => absint( $_GET['paged'] ?? 1 ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view/pagination parameter.
		] );

		echo '<div class="wrap"><h1>Bulk Editor</h1>';
		echo '<p>Mass edit title, description, and keyword for published posts.</p>';

		if ( empty( $data['posts'] ) ) {
			echo '<p>No published posts found.</p></div>';
			return;
		}

		echo '<form method="post">';
		wp_nonce_field( 'search_intel_bulk_edit', 'nonce' );
		echo '<table class="widefat striped">';
		echo '<thead><tr><th>ID</th><th>Post Title</th><th>SEO Title</th><th>Description</th><th>Keyword</th><th>SEO</th><th>AEO</th><th>GEO</th></tr></thead>';
		echo '<tbody>';

		foreach ( $data['posts'] as $row ) {
			printf(
				'<tr><td>%d</td><td>%s</td><td><input type="text" name="updates[%d][title]" value="%s" class="regular-text"></td><td><input type="text" name="updates[%d][description]" value="%s" class="regular-text"></td><td><input type="text" name="updates[%d][keyword]" value="%s" style="width:120px"></td><td>%d</td><td>%d</td><td>%d</td></tr>',
				absint( $row['ID'] ),
				esc_html( $row['title'] ),
				absint( $row['ID'] ),
				esc_attr( $row['seo_title'] ),
				absint( $row['ID'] ),
				esc_attr( $row['description'] ),
				absint( $row['ID'] ),
				esc_attr( $row['keyword'] ),
				absint( $row['seo_score'] ),
				absint( $row['aeo_score'] ),
				absint( $row['geo_score'] )
			);
		}

		echo '</tbody></table>';
		submit_button( 'Save Changes' );
		echo '</form>';

		// Pagination.
		$total_pages = (int) ceil( $data['total'] / 25 );
		if ( $total_pages > 1 ) {
			echo '<div class="tablenav"><div class="tablenav-pages">';
			for ( $i = 1; $i <= $total_pages; $i++ ) {
				printf(
					'<a class="page-numbers%s" href="%s">%d</a> ',
					( $i === absint( $_GET['paged'] ?? 1 ) ) ? ' current' : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view/pagination parameter.
					esc_url( add_query_arg( 'paged', $i ) ),
					absint( $i )
				);
			}
			echo '</div></div>';
		}

		// Queue status.
		$queue = $this->get_queue();
		$pending = count( array_filter( $queue, function ( $j ) {
			return $j['status'] === 'pending';
		} ) );
		if ( $pending > 0 ) {
			printf( '<div class="notice notice-info"><p>%d background jobs pending.</p></div>', absint( $pending ) );
		}

		echo '</div>';
	}
}
