<?php
/**
 * WordPress Abilities API integration (WordPress 6.9+).
 *
 * Registers machine-readable abilities so AI agents (through the REST API or
 * the MCP Adapter) can query this site's content and SEO data, and — for
 * logged-in editors — analyse posts and update their search metadata.
 *
 * Public abilities expose only what a visitor could already see on the page.
 * Everything that reads scores or writes data runs WordPress capability checks.
 *
 * @package JAT_Search_Intel
 */

namespace JAT_Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abilities.
 */
class Abilities {

	public const CATEGORY = 'jat-search-intel';

	/** @var array<string,mixed> */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param array<string,mixed> $settings Plugin settings.
	 */
	public function __construct( array $settings ) {
		$this->settings = $settings;

		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}
		add_action( 'wp_abilities_api_categories_init', [ $this, 'register_category' ] );
		add_action( 'wp_abilities_api_init', [ $this, 'register_abilities' ] );
	}

	/**
	 * Register the ability category.
	 */
	public function register_category(): void {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}
		wp_register_ability_category(
			self::CATEGORY,
			[
				'label'       => __( 'Search & AI visibility', 'jat-search-intel' ),
				'description' => __( 'Search this site, read page SEO data and improve search metadata.', 'jat-search-intel' ),
			]
		);
	}

	/**
	 * Ability definitions, keyed by name. Public so tests can inspect them.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function definitions(): array {
		$post_id_schema = [
			'type'        => 'integer',
			'description' => __( 'Post ID.', 'jat-search-intel' ),
			'minimum'     => 1,
		];
		$term_id_schema = [
			'type'        => 'integer',
			'description' => __( 'Term ID (category, tag or other taxonomy term).', 'jat-search-intel' ),
			'minimum'     => 1,
		];
		$page_schema    = [
			'type'       => 'object',
			'properties' => [
				'post_id'      => [ 'type' => 'integer' ],
				'url'          => [ 'type' => 'string' ],
				'title'        => [ 'type' => 'string' ],
				'description'  => [ 'type' => 'string' ],
				'modified'     => [ 'type' => 'string' ],
				'markdown_url' => [ 'type' => 'string' ],
			],
		];

		return [
			'jat-search-intel/search-content'  => [
				'label'               => __( 'Search site content', 'jat-search-intel' ),
				'description'         => __( 'Full-text search of published, indexable pages. Returns titles, URLs, summaries and a clean Markdown URL for each result.', 'jat-search-intel' ),
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'query' => [
							'type'        => 'string',
							'description' => __( 'Search terms.', 'jat-search-intel' ),
							'minLength'   => 2,
						],
						'limit' => [
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => 20,
							'default' => 5,
						],
					],
					'required'   => [ 'query' ],
				],
				'output_schema'       => [
					'type'  => 'array',
					'items' => $page_schema,
				],
				'execute_callback'    => [ $this, 'search_content' ],
				'permission_callback' => '__return_true',
				'meta'                => $this->meta( true, false, true, true ),
			],
			'jat-search-intel/get-page-seo'    => [
				'label'               => __( 'Get page SEO data', 'jat-search-intel' ),
				'description'         => __( 'Search title, meta description, canonical URL, robots directives, AI summary, FAQs and Markdown URL for one published page, by URL or post ID.', 'jat-search-intel' ),
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'url'     => [
							'type'        => 'string',
							'description' => __( 'Page URL on this site.', 'jat-search-intel' ),
						],
						'post_id' => $post_id_schema,
					],
					'default'    => [],
				],
				'output_schema'       => [ 'type' => 'object' ],
				'execute_callback'    => [ $this, 'get_page_seo' ],
				'permission_callback' => '__return_true',
				'meta'                => $this->meta( true, false, true, true ),
			],
			'jat-search-intel/analyze-post'    => [
				'label'               => __( 'Analyse a post for SEO, AEO and GEO', 'jat-search-intel' ),
				'description'         => __( 'Scores a post for search (SEO), answer engines (AEO) and AI citations (GEO), saves the scores, and returns every check plus passage-level edits (slow intros, unanswered question headings, unsourced figures, long paragraphs, missing comparison tables).', 'jat-search-intel' ),
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [ 'post_id' => $post_id_schema ],
					'required'   => [ 'post_id' ],
				],
				'output_schema'       => [ 'type' => 'object' ],
				'execute_callback'    => [ $this, 'analyze_post' ],
				'permission_callback' => [ $this, 'can_edit_post' ],
				'meta'                => $this->meta( false, false, true, false ),
			],
			'jat-search-intel/update-seo-meta' => [
				'label'               => __( 'Update a post\'s search metadata', 'jat-search-intel' ),
				'description'         => __( 'Sets the SEO title, meta description, focus keyword and/or AI summary of a post. Omitted fields are left unchanged; an empty string clears a field.', 'jat-search-intel' ),
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'post_id'       => $post_id_schema,
						'title'         => [
							'type'      => 'string',
							'maxLength' => 200,
						],
						'description'   => [
							'type'      => 'string',
							'maxLength' => 320,
						],
						'focus_keyword' => [
							'type'      => 'string',
							'maxLength' => 100,
						],
						'ai_summary'    => [
							'type'      => 'string',
							'maxLength' => 1000,
						],
					],
					'required'   => [ 'post_id' ],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'post_id' => [ 'type' => 'integer' ],
						'updated' => [
							'type'  => 'array',
							'items' => [ 'type' => 'string' ],
						],
					],
				],
				'execute_callback'    => [ $this, 'update_seo_meta' ],
				'permission_callback' => [ $this, 'can_edit_post' ],
				// Overwrites fields in place; not flagged destructive because nothing is deleted.
				'meta'                => $this->meta( false, false, true, false ),
			],
			'jat-search-intel/site-readiness'  => [
				'label'               => __( 'AI search readiness audit', 'jat-search-intel' ),
				'description'         => __( 'Runs the site-wide audit of crawler access, discovery, structured data and content freshness, and returns a 0-100 score with findings.', 'jat-search-intel' ),
				'output_schema'       => [ 'type' => 'object' ],
				'execute_callback'    => [ $this, 'site_readiness' ],
				'permission_callback' => [ $this, 'can_manage' ],
				'meta'                => $this->meta( true, false, true, false ),
			],
			'jat-search-intel/refresh-queue'   => [
				'label'               => __( 'Content refresh queue', 'jat-search-intel' ),
				'description'         => __( 'Lists the pages most in need of an update: longest unchanged, weighted by Search Console clicks.', 'jat-search-intel' ),
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'limit' => [
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => 50,
							'default' => 10,
						],
					],
					'default'    => [],
				],
				'output_schema'       => [ 'type' => 'array' ],
				'execute_callback'    => [ $this, 'refresh_queue' ],
				'permission_callback' => [ $this, 'can_edit_others' ],
				'meta'                => $this->meta( true, false, true, false ),
			],
			'jat-search-intel/get-term-seo'    => [
				'label'               => __( 'Get category or tag SEO data', 'jat-search-intel' ),
				'description'         => __( 'SEO title, meta description, canonical URL, robots settings and social fields of a category, tag or other public taxonomy term.', 'jat-search-intel' ),
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [ 'term_id' => $term_id_schema ],
					'required'   => [ 'term_id' ],
				],
				'output_schema'       => [ 'type' => 'object' ],
				'execute_callback'    => [ $this, 'get_term_seo' ],
				'permission_callback' => '__return_true',
				'meta'                => $this->meta( true, false, true, true ),
			],
			'jat-search-intel/update-term-seo' => [
				'label'               => __( 'Update a category or tag\'s SEO data', 'jat-search-intel' ),
				'description'         => __( 'Sets SEO fields of a taxonomy term. Omitted fields are left unchanged; an empty string clears a field.', 'jat-search-intel' ),
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'term_id'            => $term_id_schema,
						'title'              => [ 'type' => 'string', 'maxLength' => 200 ],
						'description'        => [ 'type' => 'string', 'maxLength' => 320 ],
						'focus_keyword'      => [ 'type' => 'string', 'maxLength' => 100 ],
						'canonical'          => [ 'type' => 'string', 'maxLength' => 2000 ],
						'noindex'            => [ 'type' => 'boolean' ],
						'nofollow'           => [ 'type' => 'boolean' ],
						'social_title'       => [ 'type' => 'string', 'maxLength' => 200 ],
						'social_description' => [ 'type' => 'string', 'maxLength' => 320 ],
						'social_image'       => [ 'type' => 'string', 'maxLength' => 2000 ],
					],
					'required'   => [ 'term_id' ],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'term_id' => [ 'type' => 'integer' ],
						'updated' => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
					],
				],
				'execute_callback'    => [ $this, 'update_term_seo' ],
				'permission_callback' => [ $this, 'can_edit_term' ],
				'meta'                => $this->meta( false, false, true, false ),
			],
			'jat-search-intel/posts-needing-attention' => [
				'label'               => __( 'Posts needing SEO attention', 'jat-search-intel' ),
				'description'         => __( 'Published posts with the lowest SEO, AEO or GEO scores, or with no meta description, so an agent can work through them.', 'jat-search-intel' ),
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'score' => [ 'type' => 'string', 'enum' => [ 'seo', 'aeo', 'geo' ], 'default' => 'seo' ],
						'limit' => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 10 ],
					],
					'default'    => [],
				],
				'output_schema'       => [ 'type' => 'array' ],
				'execute_callback'    => [ $this, 'posts_needing_attention' ],
				'permission_callback' => [ $this, 'can_edit_others' ],
				'meta'                => $this->meta( true, false, true, false ),
			],
			'jat-search-intel/get-index-status' => [
				'label'               => __( 'Google index status of a post', 'jat-search-intel' ),
				'description'         => __( 'Whether Google has indexed a post, its coverage state, last crawl and the canonical Google chose, from Search Console. Set refresh to inspect it again now (needs the Search Console connection).', 'jat-search-intel' ),
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'post_id' => $post_id_schema,
						'refresh' => [ 'type' => 'boolean', 'default' => false ],
					],
					'required'   => [ 'post_id' ],
				],
				'output_schema'       => [ 'type' => 'object' ],
				'execute_callback'    => [ $this, 'get_index_status' ],
				'permission_callback' => [ $this, 'can_edit_post' ],
				'meta'                => $this->meta( false, false, true, false ),
			],
			'jat-search-intel/get-ai-citations' => [
				'label'               => __( 'AI citations', 'jat-search-intel' ),
				'description'         => __( 'For each tracked question, whether ChatGPT, Claude, Gemini and Perplexity cited this site in their latest check, which of its pages they cited, whether they named it, and which other sites they cited instead. Also lists citations lost since the previous check.', 'jat-search-intel' ),
				'input_schema'        => [
					'type'       => 'object',
					'properties' => new \stdClass(),
					'default'    => [],
				],
				'output_schema'       => [ 'type' => 'object' ],
				'execute_callback'    => [ $this, 'get_ai_citations' ],
				'permission_callback' => [ $this, 'can_edit_others' ],
				'meta'                => $this->meta( true, false, true, false ),
			],
			'jat-search-intel/list-redirects'  => [
				'label'               => __( 'List redirects', 'jat-search-intel' ),
				'description'         => __( 'Redirect rules with their source, target, type and hit count, optionally filtered by a search term.', 'jat-search-intel' ),
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'search' => [ 'type' => 'string' ],
						'limit'  => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 25 ],
					],
					'default'    => [],
				],
				'output_schema'       => [ 'type' => 'object' ],
				'execute_callback'    => [ $this, 'list_redirects' ],
				'permission_callback' => [ $this, 'can_manage' ],
				'meta'                => $this->meta( true, false, true, false ),
			],
			'jat-search-intel/create-redirect' => [
				'label'               => __( 'Create a redirect', 'jat-search-intel' ),
				'description'         => __( 'Adds a redirect rule, for example from a deleted page\'s path to its replacement. Exact, prefix or regex matching; 301, 302, 307 or 410.', 'jat-search-intel' ),
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'source'      => [ 'type' => 'string', 'description' => __( 'Path to redirect from, such as /old-page/.', 'jat-search-intel' ), 'minLength' => 1 ],
						'target'      => [ 'type' => 'string', 'description' => __( 'URL or path to redirect to (empty for 410).', 'jat-search-intel' ) ],
						'status_code' => [ 'type' => 'integer', 'enum' => [ 301, 302, 307, 410 ], 'default' => 301 ],
						'match_type'  => [ 'type' => 'string', 'enum' => [ 'exact', 'prefix', 'regex' ], 'default' => 'exact' ],
						'note'        => [ 'type' => 'string', 'maxLength' => 200 ],
					],
					'required'   => [ 'source' ],
				],
				'output_schema'       => [ 'type' => 'object' ],
				'execute_callback'    => [ $this, 'create_redirect' ],
				'permission_callback' => [ $this, 'can_manage' ],
				'meta'                => $this->meta( false, false, false, false ),
			],
		];
	}

	/**
	 * Register all abilities.
	 */
	public function register_abilities(): void {
		foreach ( $this->definitions() as $name => $args ) {
			$args['category'] = self::CATEGORY;
			wp_register_ability( $name, $args );
		}
	}

	/**
	 * Ability meta block.
	 *
	 * @param bool $readonly    Read-only.
	 * @param bool $destructive Overwrites data.
	 * @param bool $idempotent  Safe to repeat.
	 * @param bool $public      Available to anonymous clients.
	 * @return array
	 */
	private function meta( bool $readonly, bool $destructive, bool $idempotent, bool $public ): array {
		return [
			'annotations'  => [
				'readonly'    => $readonly,
				'destructive' => $destructive,
				'idempotent'  => $idempotent,
			],
			// Every ability is listed for REST/MCP clients; permission callbacks decide who may run it.
			'show_in_rest' => true,
			// Anonymous clients may only discover and run the read-only public abilities.
			'public'       => $public,
			'mcp'          => [ 'public' => $public ],
		];
	}

	// ── Permissions ─────────────────────────────────────────────────────

	/**
	 * Current user can edit the requested post.
	 *
	 * @param mixed $input Input.
	 * @return bool
	 */
	public function can_edit_post( $input = null ): bool {
		$post_id = (int) ( is_array( $input ) ? ( $input['post_id'] ?? 0 ) : 0 );
		return $post_id > 0 && current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Site administrator.
	 *
	 * @return bool
	 */
	public function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Current user can edit the requested term.
	 *
	 * @param mixed $input Input.
	 * @return bool
	 */
	public function can_edit_term( $input = null ): bool {
		$term_id = (int) ( is_array( $input ) ? ( $input['term_id'] ?? 0 ) : 0 );
		return $term_id > 0 && current_user_can( 'edit_term', $term_id );
	}

	/**
	 * Editor or above.
	 *
	 * @return bool
	 */
	public function can_edit_others(): bool {
		return current_user_can( 'edit_others_posts' );
	}

	// ── Callbacks ───────────────────────────────────────────────────────

	/**
	 * Search published content.
	 *
	 * @param mixed $input Input.
	 * @return array
	 */
	public function search_content( $input = null ): array {
		$input = is_array( $input ) ? $input : [];
		$query = sanitize_text_field( (string) ( $input['query'] ?? '' ) );
		$limit = max( 1, min( 20, (int) ( $input['limit'] ?? 5 ) ) );
		if ( '' === $query ) {
			return [];
		}

		$ids = get_posts(
			[
				's'                => $query,
				'post_type'        => $this->public_post_types(),
				'post_status'      => 'publish',
				'has_password'     => false,
				'posts_per_page'   => $limit * 2,
				'fields'           => 'ids',
				'suppress_filters' => false,
				'no_found_rows'    => true,
			]
		);

		$out = [];
		foreach ( $ids as $id ) {
			if ( ! $this->is_exposable( (int) $id ) ) {
				continue;
			}
			$out[] = $this->page_summary( (int) $id );
			if ( count( $out ) >= $limit ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * SEO data for one page.
	 *
	 * @param mixed $input Input.
	 * @return array|\WP_Error
	 */
	public function get_page_seo( $input = null ) {
		$input   = is_array( $input ) ? $input : [];
		$post_id = (int) ( $input['post_id'] ?? 0 );
		if ( ! $post_id && ! empty( $input['url'] ) ) {
			$post_id = (int) url_to_postid( esc_url_raw( (string) $input['url'] ) );
		}
		if ( ! $post_id || ! $this->is_exposable( $post_id ) ) {
			return new \WP_Error( 'jat_search_intel_not_found', __( 'No published, indexable page matches that URL or ID.', 'jat-search-intel' ), [ 'status' => 404 ] );
		}

		$data = $this->page_summary( $post_id );

		$canonical          = (string) get_post_meta( $post_id, '_jat_search_intel_canonical', true );
		$data['canonical']  = $canonical ?: (string) get_permalink( $post_id );
		$data['robots']     = get_post_meta( $post_id, '_jat_search_intel_nofollow', true ) ? 'index, nofollow' : 'index, follow';
		$data['ai_summary'] = (string) get_post_meta( $post_id, '_jat_search_intel_ai_summary', true );

		$faqs = get_post_meta( $post_id, '_jat_search_intel_faqs', true );
		if ( is_array( $faqs ) && $faqs ) {
			$data['faqs'] = array_values( array_filter( array_map( static function ( $faq ) {
				if ( ! is_array( $faq ) ) {
					return null;
				}
				$q = trim( wp_strip_all_tags( (string) ( $faq['question'] ?? '' ) ) );
				$a = trim( wp_strip_all_tags( (string) ( $faq['answer'] ?? '' ) ) );
				return '' !== $q && '' !== $a ? [ 'question' => $q, 'answer' => $a ] : null;
			}, $faqs ) ) );
		}

		$data['author'] = get_the_author_meta( 'display_name', (int) get_post_field( 'post_author', $post_id ) );

		// Scores are editorial data: only for users who can edit the post.
		if ( current_user_can( 'edit_post', $post_id ) ) {
			$data['focus_keyword'] = (string) get_post_meta( $post_id, '_jat_search_intel_focus_keyword', true );
			$data['scores']        = [
				'seo'         => (float) get_post_meta( $post_id, '_jat_search_intel_seo_score', true ),
				'aeo'         => (float) get_post_meta( $post_id, '_jat_search_intel_aeo_score', true ),
				'geo'         => (float) get_post_meta( $post_id, '_jat_search_intel_geo_score', true ),
				'readability' => (float) get_post_meta( $post_id, '_jat_search_intel_readability_score', true ),
			];
		}

		return $data;
	}

	/**
	 * Analyse and store scores.
	 *
	 * @param mixed $input Input.
	 * @return array|\WP_Error
	 */
	public function analyze_post( $input = null ) {
		$post_id = (int) ( is_array( $input ) ? ( $input['post_id'] ?? 0 ) : 0 );
		$results = Core::get_instance()->analyzer->analyze_and_store( $post_id );
		if ( ! $results ) {
			return new \WP_Error( 'jat_search_intel_not_found', __( 'Post not found.', 'jat-search-intel' ), [ 'status' => 404 ] );
		}

		$checks = [];
		foreach ( [ 'seo_score' => 'seo', 'aeo_score' => 'aeo', 'geo_score' => 'geo' ] as $key => $label ) {
			foreach ( (array) ( $results[ $key ]['checks'] ?? [] ) as $check ) {
				$checks[] = [
					'type'  => $label,
					'pass'  => ! empty( $check['pass'] ),
					'label' => (string) ( $check['label'] ?? '' ),
				];
			}
		}

		$post = get_post( $post_id );

		return [
			'post_id'    => $post_id,
			'scores'     => Content_Analyzer::scores_from_results( $results ),
			'word_count' => (int) ( $results['word_count'] ?? 0 ),
			'checks'     => $checks,
			// Passage-level edits, pointing at top-level block positions.
			'passages'   => $post ? Passage_Coach::analyze(
				(string) $post->post_content,
				[
					'title'      => (string) $post->post_title,
					'keyword'    => (string) get_post_meta( $post_id, '_jat_search_intel_focus_keyword', true ),
					'ai_summary' => (string) get_post_meta( $post_id, '_jat_search_intel_ai_summary', true ),
					'site_host'  => (string) wp_parse_url( home_url(), PHP_URL_HOST ),
				]
			) : [],
		];
	}

	/**
	 * Update SEO fields.
	 *
	 * @param mixed $input Input.
	 * @return array|\WP_Error
	 */
	public function update_seo_meta( $input = null ) {
		$input   = is_array( $input ) ? $input : [];
		$post_id = (int) ( $input['post_id'] ?? 0 );
		if ( ! get_post( $post_id ) ) {
			return new \WP_Error( 'jat_search_intel_not_found', __( 'Post not found.', 'jat-search-intel' ), [ 'status' => 404 ] );
		}

		$fields  = [
			'title'         => [ '_jat_search_intel_title', 'sanitize_text_field' ],
			'description'   => [ '_jat_search_intel_description', 'sanitize_textarea_field' ],
			'focus_keyword' => [ '_jat_search_intel_focus_keyword', 'sanitize_text_field' ],
			'ai_summary'    => [ '_jat_search_intel_ai_summary', 'sanitize_textarea_field' ],
		];
		$updated = [];
		foreach ( $fields as $field => list( $meta_key, $sanitize ) ) {
			if ( ! array_key_exists( $field, $input ) ) {
				continue;
			}
			$value = call_user_func( $sanitize, (string) $input[ $field ] );
			if ( '' === $value ) {
				delete_post_meta( $post_id, $meta_key );
			} else {
				update_post_meta( $post_id, $meta_key, $value );
			}
			$updated[] = $field;
		}

		if ( $updated ) {
			/**
			 * Fires after search metadata is changed through an ability.
			 *
			 * @param int      $post_id Post ID.
			 * @param string[] $updated Field names that changed.
			 * @param string   $source  Always 'ability'.
			 */
			do_action( 'jat_search_intel_seo_meta_updated', $post_id, $updated, 'ability' );
		}

		return [
			'post_id' => $post_id,
			'updated' => $updated,
		];
	}

	/**
	 * Readiness audit.
	 *
	 * @return array
	 */
	public function site_readiness(): array {
		$audit  = new Readiness( $this->settings );
		$checks = $audit->run();
		return [
			'score'  => Readiness::score( $checks ),
			'checks' => array_map( static function ( $c ) {
				unset( $c['link'] );
				return $c;
			}, $checks ),
		];
	}

	/**
	 * Refresh queue.
	 *
	 * @param mixed $input Input.
	 * @return array
	 */
	public function refresh_queue( $input = null ): array {
		$limit = max( 1, min( 50, (int) ( is_array( $input ) ? ( $input['limit'] ?? 10 ) : 10 ) ) );
		return ( new Readiness( $this->settings ) )->refresh_queue( $limit );
	}

	/**
	 * Latest AI citation results.
	 *
	 * @param mixed $input Input (unused).
	 * @return array<string,mixed>
	 */
	public function get_ai_citations( $input = null ): array {
		unset( $input );
		$summary   = Citation_Tracker::summary();
		$questions = [];
		foreach ( $summary['rows'] as $row ) {
			$engines = [];
			foreach ( $row['engines'] as $engine => $entry ) {
				$engines[ $engine ] = isset( $entry['error'] )
					? [ 'error' => (string) $entry['error'] ]
					: [
						'cited'      => ! empty( $entry['cited'] ),
						'read'       => ! empty( $entry['source'] ),
						'named'      => ! empty( $entry['mentioned'] ),
						'pages'      => (array) ( $entry['urls'] ?? [] ),
						'cited_else' => (array) ( $entry['top'] ?? [] ),
						'checked'    => isset( $entry['t'] ) ? gmdate( 'c', (int) $entry['t'] ) : '',
					];
			}
			$questions[] = [ 'question' => $row['q'], 'engines' => $engines ];
		}
		return [
			'citation_share' => $summary['checked'] ? round( $summary['cited'] / $summary['checked'], 3 ) : null,
			'questions'      => $questions,
			'lost'           => $summary['lost'],
			'top_competitors' => $summary['competitors'],
		];
	}

	/**
	 * A public term, or an error.
	 *
	 * @param int $term_id Term ID.
	 * @return \WP_Term|\WP_Error
	 */
	private function public_term( int $term_id ) {
		$term = get_term( $term_id );
		if ( ! $term instanceof \WP_Term || ! in_array( $term->taxonomy, Term_SEO::taxonomies(), true ) ) {
			return new \WP_Error( 'jat_search_intel_not_found', __( 'Term not found.', 'jat-search-intel' ), [ 'status' => 404 ] );
		}
		return $term;
	}

	/**
	 * Term SEO data. The focus keyword is only shown to users who can edit the term.
	 *
	 * @param mixed $input Input.
	 * @return array|\WP_Error
	 */
	public function get_term_seo( $input = null ) {
		$term = $this->public_term( (int) ( is_array( $input ) ? ( $input['term_id'] ?? 0 ) : 0 ) );
		if ( is_wp_error( $term ) ) {
			return $term;
		}
		$fields = Term_SEO::get_all( (int) $term->term_id );
		if ( ! current_user_can( 'edit_term', (int) $term->term_id ) ) {
			unset( $fields['focus_keyword'] );
		}
		$link = get_term_link( $term );
		return [
			'term_id'  => (int) $term->term_id,
			'taxonomy' => $term->taxonomy,
			'name'     => $term->name,
			'url'      => is_wp_error( $link ) ? '' : $link,
			'count'    => (int) $term->count,
			'noindex_effective' => Term_SEO::is_noindex( $term, $this->settings ),
		] + $fields;
	}

	/**
	 * Update term SEO fields.
	 *
	 * @param mixed $input Input.
	 * @return array|\WP_Error
	 */
	public function update_term_seo( $input = null ) {
		$input = is_array( $input ) ? $input : [];
		$term  = $this->public_term( (int) ( $input['term_id'] ?? 0 ) );
		if ( is_wp_error( $term ) ) {
			return $term;
		}
		$values  = array_intersect_key( $input, Term_SEO::FIELDS );
		$updated = Term_SEO::update( (int) $term->term_id, $values, true );
		return [
			'term_id' => (int) $term->term_id,
			'updated' => $updated,
		];
	}

	/**
	 * Lowest-scoring published posts, and posts with no meta description.
	 *
	 * @param mixed $input Input.
	 * @return array
	 */
	public function posts_needing_attention( $input = null ): array {
		$input = is_array( $input ) ? $input : [];
		$score = in_array( $input['score'] ?? 'seo', [ 'seo', 'aeo', 'geo' ], true ) ? (string) ( $input['score'] ?? 'seo' ) : 'seo';
		$limit = max( 1, min( 50, (int) ( $input['limit'] ?? 10 ) ) );
		$key   = '_jat_search_intel_' . $score . '_score';

		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Small, bounded admin query.
		$ids = get_posts(
			[
				'post_type'      => $this->public_post_types(),
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'fields'         => 'ids',
				'meta_key'       => $key,
				'orderby'        => 'meta_value_num',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			]
		);
		$missing = get_posts(
			[
				'post_type'      => $this->public_post_types(),
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => [
					'relation' => 'OR',
					[ 'key' => '_jat_search_intel_description', 'compare' => 'NOT EXISTS' ],
					[ 'key' => '_jat_search_intel_description', 'value' => '' ],
				],
			]
		);
		// phpcs:enable

		$out = [];
		foreach ( array_unique( array_merge( array_map( 'intval', $ids ), array_map( 'intval', $missing ) ) ) as $id ) {
			$reasons = [];
			if ( in_array( $id, array_map( 'intval', $ids ), true ) ) {
				/* translators: 1: score name (SEO, AEO or GEO), 2: score. */
				$reasons[] = sprintf( __( 'Low %1$s score (%2$d)', 'jat-search-intel' ), strtoupper( $score ), (int) get_post_meta( $id, $key, true ) );
			}
			if ( '' === (string) get_post_meta( $id, '_jat_search_intel_description', true ) ) {
				$reasons[] = __( 'No meta description', 'jat-search-intel' );
			}
			$out[] = [
				'post_id'   => $id,
				'title'     => get_the_title( $id ),
				'url'       => (string) get_permalink( $id ),
				'seo_score' => (float) get_post_meta( $id, '_jat_search_intel_seo_score', true ),
				'aeo_score' => (float) get_post_meta( $id, '_jat_search_intel_aeo_score', true ),
				'geo_score' => (float) get_post_meta( $id, '_jat_search_intel_geo_score', true ),
				'reasons'   => $reasons,
			];
		}
		return array_slice( $out, 0, $limit );
	}

	/**
	 * Stored (or fresh) Google index status.
	 *
	 * @param mixed $input Input.
	 * @return array|\WP_Error
	 */
	public function get_index_status( $input = null ) {
		$input   = is_array( $input ) ? $input : [];
		$post_id = (int) ( $input['post_id'] ?? 0 );
		if ( ! get_post( $post_id ) ) {
			return new \WP_Error( 'jat_search_intel_not_found', __( 'Post not found.', 'jat-search-intel' ), [ 'status' => 404 ] );
		}
		$result = Index_Status::get( $post_id );
		if ( ! empty( $input['refresh'] ) ) {
			if ( ! Index_Status::is_connected( $this->settings ) ) {
				return new \WP_Error( 'jat_search_intel_gsc', __( 'Search Console is not connected.', 'jat-search-intel' ), [ 'status' => 409 ] );
			}
			$result = ( new Index_Status( $this->settings ) )->check_post( $post_id );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}
		list( $state, $label ) = Index_Status::describe( $result );
		return [
			'post_id' => $post_id,
			'url'     => (string) get_permalink( $post_id ),
			'state'   => $state,
			'summary' => $label,
		] + $result;
	}

	/**
	 * Redirect rules.
	 *
	 * @param mixed $input Input.
	 * @return array
	 */
	public function list_redirects( $input = null ): array {
		$input  = is_array( $input ) ? $input : [];
		$limit  = max( 1, min( 100, (int) ( $input['limit'] ?? 25 ) ) );
		$search = sanitize_text_field( (string) ( $input['search'] ?? '' ) );
		$result = Core::get_instance()->redirects->get_redirects( 1, $limit, $search );
		return [
			'total'     => (int) $result['total'],
			'redirects' => array_map(
				static function ( $row ): array {
					$row = (array) $row;
					return [
						'id'          => (int) $row['id'],
						'source'      => (string) $row['source_url'],
						'target'      => (string) $row['target_url'],
						'status_code' => (int) $row['status_code'],
						'match_type'  => (string) ( $row['match_type'] ?? 'exact' ),
						'enabled'     => ! empty( $row['enabled'] ),
						'hits'        => (int) ( $row['hit_count'] ?? 0 ),
					];
				},
				(array) $result['items']
			),
		];
	}

	/**
	 * Create a redirect rule.
	 *
	 * @param mixed $input Input.
	 * @return array|\WP_Error
	 */
	public function create_redirect( $input = null ) {
		$input = is_array( $input ) ? $input : [];
		$id    = Core::get_instance()->redirects->save_redirect(
			[
				'source_url'  => sanitize_text_field( (string) ( $input['source'] ?? '' ) ),
				'target_url'  => sanitize_text_field( (string) ( $input['target'] ?? '' ) ),
				'status_code' => (int) ( $input['status_code'] ?? 301 ),
				'match_type'  => sanitize_key( (string) ( $input['match_type'] ?? 'exact' ) ),
				'enabled'     => true,
				'note'        => sanitize_text_field( (string) ( $input['note'] ?? __( 'Added by an AI agent', 'jat-search-intel' ) ) ),
			]
		);
		if ( ! $id ) {
			return new \WP_Error( 'jat_search_intel_redirect', __( 'The redirect could not be saved. Check that the source is set, differs from the target, and is a valid pattern.', 'jat-search-intel' ), [ 'status' => 400 ] );
		}
		return [ 'id' => (int) $id ];
	}

	// ── Helpers ─────────────────────────────────────────────────────────

	/**
	 * Post types the plugin manages that are also public.
	 *
	 * @return string[]
	 */
	private function public_post_types(): array {
		$types = (array) ( $this->settings['enabled_post_types'] ?? [ 'post', 'page' ] );
		return array_values( array_filter( $types, 'is_post_type_viewable' ) ) ?: [ 'post', 'page' ];
	}

	/**
	 * Published, public, not password-protected, not noindexed, not excluded from AI.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public function is_exposable( int $post_id ): bool {
		$post = get_post( $post_id );
		if ( ! $post || 'publish' !== $post->post_status || '' !== $post->post_password ) {
			return false;
		}
		if ( ! in_array( $post->post_type, $this->public_post_types(), true ) ) {
			return false;
		}
		return ! get_post_meta( $post_id, '_jat_search_intel_noindex', true )
			&& ! get_post_meta( $post_id, '_jat_search_intel_ai_noindex', true );
	}

	/**
	 * Common page fields.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	private function page_summary( int $post_id ): array {
		$core = Core::get_instance();
		$md   = ! empty( $this->settings['enable_markdown_endpoints'] ) ? AI_Search::markdown_url( $post_id ) : '';

		return [
			'post_id'      => $post_id,
			'url'          => (string) get_permalink( $post_id ),
			'title'        => wp_strip_all_tags( $core->seo->get_title( $post_id ) ),
			'description'  => wp_strip_all_tags( $core->seo->get_description( $post_id ) ),
			'modified'     => (string) get_post_modified_time( 'c', true, $post_id ),
			'markdown_url' => $md,
		];
	}
}
