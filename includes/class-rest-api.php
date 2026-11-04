<?php
namespace JAT_Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST API controller for JAT Search Intel.
 *
 * Registers endpoints under the jat-search-intel/v1 namespace.
 *
 * @since 1.3.0
 */
class Rest_Api {

	/** @var string */
	private const NAMESPACE = 'jat-search-intel/v1';

	/** @var array Plugin settings. */
	private $settings;

	/** @var Core */
	private $core;

	/**
	 * @param array $settings Merged plugin settings.
	 * @param Core  $core     Core instance.
	 */
	public function __construct( array $settings, Core $core ) {
		$this->settings = $settings;
		$this->core     = $core;

		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register all REST routes.
	 */
	public function register_routes(): void {
		if ( empty( $this->settings['enable_rest_api'] ) ) {
			return;
		}

		// GET /scores/{id}
		register_rest_route( self::NAMESPACE, '/scores/(?P<id>\d+)', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_scores' ],
			'permission_callback' => [ $this, 'can_read_post' ],
			'args'                => $this->get_id_args(),
		] );

		// GET /geo/{id}
		register_rest_route( self::NAMESPACE, '/geo/(?P<id>\d+)', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_geo' ],
			'permission_callback' => [ $this, 'can_read_post' ],
			'args'                => $this->get_id_args(),
		] );

		// GET/PUT /meta/{id}
		register_rest_route( self::NAMESPACE, '/meta/(?P<id>\d+)', [
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_meta' ],
				'permission_callback' => [ $this, 'can_read_post' ],
				'args'                => $this->get_id_args(),
			],
			[
				'methods'             => 'PUT',
				'callback'            => [ $this, 'update_meta' ],
				'permission_callback' => [ $this, 'can_edit_post' ],
				'args'                => $this->get_meta_update_args(),
			],
		] );

		// POST /analyze/{id}
		register_rest_route( self::NAMESPACE, '/analyze/(?P<id>\d+)', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'analyze_post' ],
			'permission_callback' => [ $this, 'can_edit_post' ],
			'args'                => $this->get_id_args(),
		] );

		// POST /bulk/rescore
		register_rest_route( self::NAMESPACE, '/bulk/rescore', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'bulk_rescore' ],
			'permission_callback' => [ $this, 'can_manage_options' ],
			'args'                => [
				'post_type'  => [
					'type'              => 'string',
					'default'           => 'post',
					'sanitize_callback' => 'sanitize_text_field',
				],
				'batch_size' => [
					'type'              => 'integer',
					'default'           => 50,
					'sanitize_callback' => 'absint',
				],
				'offset'     => [
					'type'              => 'integer',
					'default'           => 0,
					'sanitize_callback' => 'absint',
				],
			],
		] );

		// GET/PUT /settings
		register_rest_route( self::NAMESPACE, '/settings', [
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_settings' ],
				'permission_callback' => [ $this, 'can_manage_options' ],
			],
			[
				'methods'             => 'PUT',
				'callback'            => [ $this, 'update_settings' ],
				'permission_callback' => [ $this, 'can_manage_options' ],
			],
		] );

		// POST /sitemap/invalidate
		register_rest_route( self::NAMESPACE, '/sitemap/invalidate', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'invalidate_sitemap' ],
			'permission_callback' => [ $this, 'can_manage_options' ],
		] );
	}

	// ── Permission Callbacks ────────────────────────────────────────────────

	/**
	 * Check if the current user can read the post.
	 */
	public function can_read_post( \WP_REST_Request $request ): bool {
		$post = get_post( (int) $request['id'] );
		if ( ! $post ) {
			return false;
		}
		if ( 'publish' === $post->post_status ) {
			return true;
		}
		return current_user_can( 'read_post', $post->ID );
	}

	/**
	 * Check if the current user can edit the post.
	 */
	public function can_edit_post( \WP_REST_Request $request ): bool {
		$post = get_post( (int) $request['id'] );
		if ( ! $post ) {
			return false;
		}
		return current_user_can( 'edit_post', $post->ID );
	}

	/**
	 * Check if the current user can manage plugin settings.
	 */
	public function can_manage_options(): bool {
		return current_user_can( 'manage_options' );
	}

	// ── Endpoint Handlers ───────────────────────────────────────────────────

	/**
	 * GET /scores/{id} — SEO + AEO + GEO + readability scores.
	 */
	public function get_scores( \WP_REST_Request $request ): \WP_REST_Response {
		$post_id = (int) $request['id'];
		$post    = get_post( $post_id );

		if ( ! $post ) {
			return new \WP_REST_Response( [ 'code' => 'not_found', 'message' => 'Post not found.' ], 404 );
		}

		return new \WP_REST_Response( [
			'post_id'     => $post_id,
			'seo_score'   => (int) get_post_meta( $post_id, '_jat_search_intel_seo_score', true ),
			'aeo_score'   => (int) get_post_meta( $post_id, '_jat_search_intel_aeo_score', true ),
			'geo_score'   => (int) get_post_meta( $post_id, '_jat_search_intel_geo_score', true ),
			'readability' => (int) get_post_meta( $post_id, '_jat_search_intel_readability_score', true ),
		], 200 );
	}

	/**
	 * GET /geo/{id} — full GEO metadata for a post.
	 */
	public function get_geo( \WP_REST_Request $request ): \WP_REST_Response {
		$post_id = (int) $request['id'];
		$post    = get_post( $post_id );

		if ( ! $post ) {
			return new \WP_REST_Response( [ 'code' => 'not_found', 'message' => 'Post not found.' ], 404 );
		}

		$ai_summary     = get_post_meta( $post_id, '_jat_search_intel_ai_summary', true );
		$topics         = get_post_meta( $post_id, '_jat_search_intel_topics', true );
		$entities       = get_post_meta( $post_id, '_jat_search_intel_entities', true );
		$content_type   = get_post_meta( $post_id, '_jat_search_intel_content_type', true );
		$content_intent = get_post_meta( $post_id, '_jat_search_intel_content_intent', true );
		$audience       = get_post_meta( $post_id, '_jat_search_intel_audience', true );
		$expertise      = get_post_meta( $post_id, '_jat_search_intel_expertise_level', true );
		$fact_checked   = get_post_meta( $post_id, '_jat_search_intel_fact_checked', true );
		$local_rel      = get_post_meta( $post_id, '_jat_search_intel_local_relevance', true );
		$local_loc      = get_post_meta( $post_id, '_jat_search_intel_local_location', true );
		$geo_score      = (int) get_post_meta( $post_id, '_jat_search_intel_geo_score', true );

		return new \WP_REST_Response( [
			'post_id'          => $post_id,
			'ai_summary'       => $ai_summary ?: '',
			'topics'           => $topics ?: [],
			'entities'         => $entities ?: [],
			'content_type'     => $content_type ?: '',
			'content_intent'   => $content_intent ?: '',
			'audience'         => $audience ?: '',
			'expertise_level'  => $expertise ?: '',
			'fact_checked'     => $fact_checked ?: '',
			'local_relevance'  => (bool) $local_rel,
			'local_location'   => $local_loc ?: '',
			'geo_score'        => $geo_score,
		], 200 );
	}

	/**
	 * GET /meta/{id} — all JAT Search Intel meta fields.
	 */
	public function get_meta( \WP_REST_Request $request ): \WP_REST_Response {
		$post_id = (int) $request['id'];
		$post    = get_post( $post_id );

		if ( ! $post ) {
			return new \WP_REST_Response( [ 'code' => 'not_found', 'message' => 'Post not found.' ], 404 );
		}

		$fields = $this->get_meta_fields();
		$data   = [ 'post_id' => $post_id ];

		foreach ( $fields as $key => $meta_key ) {
			$data[ $key ] = get_post_meta( $post_id, $meta_key, true );
		}

		return new \WP_REST_Response( $data, 200 );
	}

	/**
	 * PUT /meta/{id} — update JAT Search Intel meta fields.
	 */
	public function update_meta( \WP_REST_Request $request ): \WP_REST_Response {
		$post_id = (int) $request['id'];
		$post    = get_post( $post_id );

		if ( ! $post ) {
			return new \WP_REST_Response( [ 'code' => 'not_found', 'message' => 'Post not found.' ], 404 );
		}

		$fields  = $this->get_meta_fields();
		$params  = $request->get_json_params();
		$updated = [];

		foreach ( $params as $key => $value ) {
			if ( 'id' === $key || ! isset( $fields[ $key ] ) ) {
				continue;
			}

			$meta_key = $fields[ $key ];

			// Sanitize based on field type.
			if ( is_array( $value ) ) {
				$sanitized = array_map( 'sanitize_text_field', $value );
			} elseif ( in_array( $key, [ 'canonical', 'social_image' ], true ) ) {
				$sanitized = esc_url_raw( $value );
			} elseif ( in_array( $key, [ 'noindex', 'nofollow', 'local_relevance' ], true ) ) {
				$sanitized = (bool) $value ? '1' : '';
			} elseif ( in_array( $key, [ 'ai_summary', 'direct_answer' ], true ) ) {
				$sanitized = wp_kses_post( $value );
			} else {
				$sanitized = sanitize_text_field( $value );
			}

			update_post_meta( $post_id, $meta_key, $sanitized );
			$updated[ $key ] = $sanitized;
		}

		return new \WP_REST_Response( [
			'post_id' => $post_id,
			'updated' => $updated,
		], 200 );
	}

	/**
	 * POST /analyze/{id} — trigger content analysis.
	 */
	public function analyze_post( \WP_REST_Request $request ): \WP_REST_Response {
		$post_id = (int) $request['id'];
		$post    = get_post( $post_id );

		if ( ! $post ) {
			return new \WP_REST_Response( [ 'code' => 'not_found', 'message' => 'Post not found.' ], 404 );
		}

		$results = $this->core->analyzer->analyze_and_store( $post );

		return new \WP_REST_Response( [
			'post_id' => $post_id,
			'results' => $results,
		], 200 );
	}

	/**
	 * POST /bulk/rescore — rescore a batch of posts.
	 */
	public function bulk_rescore( \WP_REST_Request $request ): \WP_REST_Response {
		$post_type  = $request['post_type'];
		$batch_size = min( (int) $request['batch_size'], 100 ); // Cap at 100.
		$offset     = (int) $request['offset'];

		$posts = get_posts( [
			'post_type'      => $post_type,
			'post_status'    => 'publish',
			'posts_per_page' => $batch_size,
			'offset'         => $offset,
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'fields'         => 'ids',
		] );

		$total = wp_count_posts( $post_type );
		$total_published = (int) $total->publish;

		$scored = [];
		foreach ( $posts as $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post ) {
				continue;
			}

			$content     = wp_strip_all_tags( $post->post_content );
			$title       = $post->post_title;
			$keyword     = get_post_meta( $post_id, '_jat_search_intel_focus_keyword', true ) ?: '';
			$description = get_post_meta( $post_id, '_jat_search_intel_description', true ) ?: '';

			$results = $this->core->analyzer->analyze( $post_id, $content, $title, $keyword, $description );

			if ( isset( $results['seo']['score'] ) ) {
				update_post_meta( $post_id, '_jat_search_intel_seo_score', (int) $results['seo']['score'] );
			}
			if ( isset( $results['aeo']['score'] ) ) {
				update_post_meta( $post_id, '_jat_search_intel_aeo_score', (int) $results['aeo']['score'] );
			}
			if ( isset( $results['geo']['score'] ) ) {
				update_post_meta( $post_id, '_jat_search_intel_geo_score', (int) $results['geo']['score'] );
			}
			if ( isset( $results['readability']['score'] ) ) {
				update_post_meta( $post_id, '_jat_search_intel_readability_score', (int) $results['readability']['score'] );
			}

			$scored[] = $post_id;
		}

		$next_offset = $offset + $batch_size;
		$has_more    = $next_offset < $total_published;

		return new \WP_REST_Response( [
			'processed'  => count( $scored ),
			'post_ids'   => $scored,
			'offset'     => $offset,
			'next_offset' => $has_more ? $next_offset : null,
			'has_more'   => $has_more,
			'total'      => $total_published,
		], 200 );
	}

	/**
	 * GET /settings — read plugin settings.
	 */
	public function get_settings(): \WP_REST_Response {
		return new \WP_REST_Response( $this->core->get_settings(), 200 );
	}

	/**
	 * PUT /settings — update plugin settings.
	 */
	public function update_settings( \WP_REST_Request $request ): \WP_REST_Response {
		$params   = $request->get_json_params();
		$current  = $this->core->get_settings();
		$defaults = Core::get_default_settings();

		// Only allow updating known keys.
		$updated = [];
		foreach ( $params as $key => $value ) {
			if ( ! array_key_exists( $key, $defaults ) ) {
				continue;
			}

			// Type-cast based on default.
			$default_val = $defaults[ $key ];
			if ( is_bool( $default_val ) ) {
				$current[ $key ] = (bool) $value;
			} elseif ( is_int( $default_val ) ) {
				$current[ $key ] = absint( $value );
			} elseif ( is_array( $default_val ) ) {
				$current[ $key ] = is_array( $value ) ? array_map( 'sanitize_text_field', $value ) : $default_val;
			} else {
				$current[ $key ] = sanitize_text_field( $value );
			}

			$updated[ $key ] = $current[ $key ];
		}

		update_option( 'jat_search_intel_settings', $current );

		return new \WP_REST_Response( [
			'updated'  => $updated,
			'settings' => $current,
		], 200 );
	}

	/**
	 * POST /sitemap/invalidate — clear sitemap caches.
	 */
	public function invalidate_sitemap(): \WP_REST_Response {
		$this->core->sitemap->invalidate_cache();

		return new \WP_REST_Response( [
			'success' => true,
			'message' => 'Sitemap cache cleared.',
		], 200 );
	}

	// ── Helpers ─────────────────────────────────────────────────────────────

	/**
	 * Map of REST field names → post meta keys.
	 */
	private function get_meta_fields(): array {
		return [
			'title'                  => '_jat_search_intel_title',
			'description'            => '_jat_search_intel_description',
			'focus_keyword'          => '_jat_search_intel_focus_keyword',
			'secondary_keywords'     => '_jat_search_intel_secondary_keywords',
			'canonical'              => '_jat_search_intel_canonical',
			'noindex'                => '_jat_search_intel_noindex',
			'nofollow'               => '_jat_search_intel_nofollow',
			'social_title'           => '_jat_search_intel_social_title',
			'social_description'     => '_jat_search_intel_social_description',
			'social_image'           => '_jat_search_intel_social_image',
			'schema_type'            => '_jat_search_intel_schema_type',
			'faqs'                   => '_jat_search_intel_faqs',
			'direct_answer'          => '_jat_search_intel_direct_answer',
			'citations'              => '_jat_search_intel_citations',
			'ai_summary'             => '_jat_search_intel_ai_summary',
			'topics'                 => '_jat_search_intel_topics',
			'entities'               => '_jat_search_intel_entities',
			'expertise_level'        => '_jat_search_intel_expertise_level',
			'content_type'           => '_jat_search_intel_content_type',
			'content_intent'         => '_jat_search_intel_content_intent',
			'audience'               => '_jat_search_intel_audience',
			'fact_checked'           => '_jat_search_intel_fact_checked',
			'author_expertise_notes' => '_jat_search_intel_author_expertise_notes',
			'local_relevance'        => '_jat_search_intel_local_relevance',
			'local_location'         => '_jat_search_intel_local_location',
			'seo_score'              => '_jat_search_intel_seo_score',
			'aeo_score'              => '_jat_search_intel_aeo_score',
			'geo_score'              => '_jat_search_intel_geo_score',
			'readability_score'      => '_jat_search_intel_readability_score',
		];
	}

	/**
	 * Shared args schema for {id} parameter.
	 */
	private function get_id_args(): array {
		return [
			'id' => [
				'type'              => 'integer',
				'required'          => true,
				'validate_callback' => function ( $param ) {
					return is_numeric( $param ) && (int) $param > 0;
				},
				'sanitize_callback' => 'absint',
			],
		];
	}

	/**
	 * Args schema for PUT /meta/{id}.
	 */
	private function get_meta_update_args(): array {
		$args = $this->get_id_args();

		// All meta fields are optional in a PUT.
		foreach ( array_keys( $this->get_meta_fields() ) as $field ) {
			$args[ $field ] = [
				'required' => false,
			];
		}

		return $args;
	}
}
