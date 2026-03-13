<?php
namespace Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Internal Linking Suggestions — finds related content for cross-linking.
 *
 * Analyzes post content to suggest internal links based on shared
 * taxonomy terms, keyword overlap, and content similarity.
 *
 * @since 2.0.1
 */
class Internal_Linker {

	/**
	 * Maximum number of suggestions to return.
	 */
	private const MAX_SUGGESTIONS = 10;

	/**
	 * Transient TTL for cached suggestions (1 day).
	 */
	private const CACHE_TTL = DAY_IN_SECONDS;

	/** @var array */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param array $settings Plugin settings.
	 */
	public function __construct( array $settings ) {
		$this->settings = $settings;

		if ( ! empty( $settings['enable_internal_linker'] ) ) {
			// Invalidate cache when posts are updated.
			add_action( 'save_post', [ $this, 'invalidate_cache' ], 20, 1 );

			if ( is_admin() ) {
				add_action( 'add_meta_boxes', [ $this, 'register_metabox' ] );
			}
		}
	}

	/**
	 * Get linking suggestions for a given post.
	 *
	 * @param int $post_id Post ID.
	 * @return array List of suggested posts with relevance scores.
	 */
	public function get_suggestions( int $post_id ): array {
		$cache_key = 'si_link_suggest_' . $post_id;
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$post = get_post( $post_id );
		if ( ! $post || empty( $post->post_content ) ) {
			return [];
		}

		$candidates = $this->find_related_posts( $post_id );
		$keywords   = $this->extract_keywords( $post->post_content );
		$scored     = [];

		foreach ( $candidates as $candidate ) {
			$score = $this->score_relevance( $post_id, $candidate, $keywords );
			if ( $score > 0 ) {
				$scored[] = [
					'post_id'   => $candidate->ID,
					'title'     => $candidate->post_title,
					'url'       => get_permalink( $candidate->ID ),
					'score'     => $score,
					'reason'    => $this->get_reason( $post_id, $candidate ),
				];
			}
		}

		// Sort by score descending.
		usort( $scored, function ( $a, $b ) {
			return $b['score'] <=> $a['score'];
		} );

		$suggestions = array_slice( $scored, 0, self::MAX_SUGGESTIONS );
		set_transient( $cache_key, $suggestions, self::CACHE_TTL );

		return $suggestions;
	}

	/**
	 * Find candidate posts that share taxonomy terms with the given post.
	 *
	 * @param int $post_id Post ID.
	 * @return array List of WP_Post objects.
	 */
	public function find_related_posts( int $post_id ): array {
		$post_type  = get_post_type( $post_id );
		$taxonomies = get_object_taxonomies( $post_type );

		$term_ids = [];
		foreach ( $taxonomies as $taxonomy ) {
			$terms = get_the_terms( $post_id, $taxonomy );
			if ( is_array( $terms ) ) {
				foreach ( $terms as $term ) {
					$term_ids[] = $term->term_id;
				}
			}
		}

		if ( empty( $term_ids ) ) {
			// Fallback: recent posts of same type.
			return get_posts( [
				'post_type'      => $post_type,
				'post_status'    => 'publish',
				'posts_per_page' => self::MAX_SUGGESTIONS * 2,
				'exclude'        => [ $post_id ],
			] );
		}

		return get_posts( [
			'post_type'      => $post_type,
			'post_status'    => 'publish',
			'posts_per_page' => self::MAX_SUGGESTIONS * 3,
			'exclude'        => [ $post_id ],
			'tax_query'      => [
				[
					'taxonomy' => $taxonomies[0] ?? 'category',
					'terms'    => $term_ids,
					'field'    => 'term_id',
				],
			],
		] );
	}

	/**
	 * Extract significant keywords from content.
	 *
	 * @param string $content Post content.
	 * @return array Keyword => frequency map.
	 */
	public function extract_keywords( string $content ): array {
		$text  = wp_strip_all_tags( $content );
		$text  = strtolower( $text );
		$words = preg_split( '/\W+/', $text, -1, PREG_SPLIT_NO_EMPTY );

		// Stop words to exclude.
		$stop_words = [
			'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for',
			'of', 'with', 'by', 'from', 'is', 'are', 'was', 'were', 'be', 'been',
			'being', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would',
			'could', 'should', 'may', 'might', 'can', 'shall', 'not', 'no', 'nor',
			'so', 'if', 'then', 'than', 'that', 'this', 'these', 'those', 'it',
			'its', 'he', 'she', 'they', 'we', 'you', 'i', 'me', 'my', 'your',
			'his', 'her', 'their', 'our', 'which', 'what', 'who', 'whom', 'where',
			'when', 'how', 'as', 'also', 'just', 'about', 'up', 'out', 'all',
			'there', 'each', 'every', 'both', 'few', 'more', 'most', 'other',
			'some', 'such', 'only', 'very', 'into', 'over', 'after', 'before',
		];

		$freq = [];
		foreach ( $words as $word ) {
			if ( strlen( $word ) < 3 || in_array( $word, $stop_words, true ) ) {
				continue;
			}
			$freq[ $word ] = ( $freq[ $word ] ?? 0 ) + 1;
		}

		arsort( $freq );
		return array_slice( $freq, 0, 30, true );
	}

	/**
	 * Score relevance of a candidate post to the source post.
	 *
	 * @param int      $source_id Source post ID.
	 * @param object   $candidate Candidate post object.
	 * @param array    $keywords  Source keywords map.
	 * @return float Relevance score (0-100).
	 */
	public function score_relevance( int $source_id, object $candidate, array $keywords ): float {
		$score = 0.0;

		// 1. Shared taxonomy terms (max 50 points).
		$source_terms    = $this->get_all_term_ids( $source_id );
		$candidate_terms = $this->get_all_term_ids( $candidate->ID );
		$shared          = count( array_intersect( $source_terms, $candidate_terms ) );
		$total           = max( count( $source_terms ), 1 );
		$score          += min( 50.0, ( $shared / $total ) * 50 );

		// 2. Keyword overlap (max 40 points).
		if ( ! empty( $candidate->post_content ) ) {
			$candidate_kw = $this->extract_keywords( $candidate->post_content );
			$overlap      = count( array_intersect_key( $keywords, $candidate_kw ) );
			$total_kw     = max( count( $keywords ), 1 );
			$score       += min( 40.0, ( $overlap / $total_kw ) * 40 );
		}

		// 3. Title keyword match (max 10 points).
		$title_words = preg_split( '/\W+/', strtolower( $candidate->post_title ?? '' ), -1, PREG_SPLIT_NO_EMPTY );
		$title_match = count( array_intersect( $title_words, array_keys( $keywords ) ) );
		$score      += min( 10.0, $title_match * 2.5 );

		return round( $score, 1 );
	}

	/**
	 * Get a human-readable reason for the suggestion.
	 *
	 * @param int    $source_id Source post ID.
	 * @param object $candidate Candidate post object.
	 * @return string
	 */
	public function get_reason( int $source_id, object $candidate ): string {
		$source_terms    = $this->get_all_term_ids( $source_id );
		$candidate_terms = $this->get_all_term_ids( $candidate->ID );
		$shared          = count( array_intersect( $source_terms, $candidate_terms ) );

		if ( $shared > 0 ) {
			return sprintf( '%d shared taxonomy terms', $shared );
		}

		return 'Related content';
	}

	/**
	 * Get all term IDs across all taxonomies for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	private function get_all_term_ids( int $post_id ): array {
		$post_type  = get_post_type( $post_id );
		$taxonomies = get_object_taxonomies( $post_type );
		$ids        = [];

		foreach ( $taxonomies as $taxonomy ) {
			$terms = get_the_terms( $post_id, $taxonomy );
			if ( is_array( $terms ) ) {
				foreach ( $terms as $term ) {
					$ids[] = $term->term_id;
				}
			}
		}

		return $ids;
	}

	/**
	 * Invalidate the suggestion cache for a post.
	 *
	 * @param int $post_id Post ID.
	 */
	public function invalidate_cache( int $post_id ): void {
		delete_transient( 'si_link_suggest_' . $post_id );
	}

	/**
	 * Register the metabox for internal linking suggestions.
	 */
	public function register_metabox(): void {
		$post_types = $this->settings['enabled_post_types'] ?? [ 'post', 'page' ];
		foreach ( $post_types as $post_type ) {
			add_meta_box(
				'si-internal-links',
				__( 'Internal Linking Suggestions', 'search-intel' ),
				[ $this, 'render_metabox' ],
				$post_type,
				'side',
				'low'
			);
		}
	}

	/**
	 * Render the metabox content.
	 *
	 * @param object $post Current post object.
	 */
	public function render_metabox( object $post ): void {
		$suggestions = $this->get_suggestions( $post->ID );

		if ( empty( $suggestions ) ) {
			echo '<p>' . esc_html__( 'No suggestions available yet.', 'search-intel' ) . '</p>';
			return;
		}

		echo '<ul style="margin:0; padding:0; list-style:none;">';
		foreach ( $suggestions as $suggestion ) {
			echo '<li style="margin-bottom:8px; padding:6px; background:#f9f9f9; border-radius:3px;">';
			echo '<a href="' . esc_url( $suggestion['url'] ) . '" target="_blank" rel="noopener">';
			echo esc_html( $suggestion['title'] );
			echo '</a>';
			echo '<br><small style="color:#666;">';
			echo esc_html( $suggestion['reason'] );
			echo ' &middot; Score: ' . esc_html( (string) $suggestion['score'] );
			echo '</small></li>';
		}
		echo '</ul>';
	}
}
