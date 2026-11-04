<?php
namespace Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Topical Authority Map.
 *
 * Calculates authority scores per topic based on content volume, freshness, and E-E-A-T signals.
 * Provides content gap analysis, topic cluster dashboard, and multimodal optimization analysis.
 */
class Authority_Map {

	/** @var array */
	private $settings;

	public function __construct( array $settings ) {
		$this->settings = $settings;

		if ( empty( $settings['enable_authority_map'] ) ) {
			return;
		}

		add_action( 'admin_menu', [ $this, 'register_admin_page' ] );
		add_action( 'wp_ajax_search_intel_refresh_authority', [ $this, 'ajax_refresh' ] );
	}

	// ── Authority Score ──────────────────────────────────────────────────────

	/**
	 * Calculate authority score for a category/topic (0–100).
	 *
	 * Factors:
	 * - Content volume (number of posts)
	 * - Average freshness (how recently updated)
	 * - E-E-A-T signals (author bios, expertise notes, citations)
	 * - Internal linking density
	 * - Schema and AI meta coverage
	 *
	 * @param int $term_id Category term ID.
	 * @return array{score: int, factors: array, grade: string}
	 */
	public function calculate_topic_authority( int $term_id ): array {
		$posts = get_posts( [
			'category'    => $term_id,
			'numberposts' => 100,
			'post_status' => 'publish',
			'fields'      => 'ids',
		] );

		$count   = count( $posts );
		$factors = [];
		$total   = 0;
		$max     = 0;

		// 1. Content volume (25 points): 10+ posts = full score.
		$vol_score             = min( 25, (int) round( ( $count / 10 ) * 25 ) );
		$factors['volume']     = [
			'label'  => 'Content Volume',
			'score'  => $vol_score,
			'max'    => 25,
			'detail' => sprintf( '%d published posts', $count ),
		];
		$total += $vol_score;
		$max   += 25;

		// 2. Content freshness (25 points): % updated in last 90 days.
		$fresh_score             = $this->score_category_freshness( $posts );
		$factors['freshness']    = [
			'label'  => 'Content Freshness',
			'score'  => $fresh_score,
			'max'    => 25,
			'detail' => sprintf( '%d%% updated in last 90 days', $count > 0 ? (int) round( $fresh_score / 25 * 100 ) : 0 ),
		];
		$total += $fresh_score;
		$max   += 25;

		// 3. E-E-A-T signals (20 points).
		$eeat_score           = $this->score_category_eeat( $posts );
		$factors['eeat']      = [
			'label'  => 'E-E-A-T Signals',
			'score'  => $eeat_score,
			'max'    => 20,
			'detail' => sprintf( '%d%% of posts have expertise signals', $count > 0 ? (int) round( $eeat_score / 20 * 100 ) : 0 ),
		];
		$total += $eeat_score;
		$max   += 20;

		// 4. Internal linking (15 points).
		$link_score            = $this->score_category_linking( $posts );
		$factors['linking']    = [
			'label'  => 'Internal Linking',
			'score'  => $link_score,
			'max'    => 15,
			'detail' => sprintf( '%d%% interconnected', $count > 0 ? (int) round( $link_score / 15 * 100 ) : 0 ),
		];
		$total += $link_score;
		$max   += 15;

		// 5. Structured data coverage (15 points).
		$schema_score           = $this->score_category_schema( $posts );
		$factors['schema']      = [
			'label'  => 'Structured Data',
			'score'  => $schema_score,
			'max'    => 15,
			'detail' => sprintf( '%d%% with enhanced schema', $count > 0 ? (int) round( $schema_score / 15 * 100 ) : 0 ),
		];
		$total += $schema_score;
		$max   += 15;

		$pct   = (int) round( ( $total / $max ) * 100 );
		$grade = $this->score_to_grade( $pct );

		return [
			'score'   => $pct,
			'factors' => $factors,
			'grade'   => $grade,
		];
	}

	/**
	 * Score category freshness.
	 *
	 * @param array $post_ids Post IDs.
	 * @return int 0–25.
	 */
	private function score_category_freshness( array $post_ids ): int {
		if ( empty( $post_ids ) ) {
			return 0;
		}

		$now   = time();
		$fresh = 0;

		foreach ( $post_ids as $pid ) {
			$modified = (int) get_the_modified_date( 'U', $pid );
			$age_days = ( $now - $modified ) / 86400;
			if ( $age_days <= 90 ) {
				$fresh++;
			}
		}

		$ratio = $fresh / count( $post_ids );
		return (int) round( $ratio * 25 );
	}

	/**
	 * Score E-E-A-T signals for category posts.
	 *
	 * @param array $post_ids Post IDs.
	 * @return int 0–20.
	 */
	private function score_category_eeat( array $post_ids ): int {
		if ( empty( $post_ids ) ) {
			return 0;
		}

		$with_signals = 0;

		foreach ( $post_ids as $pid ) {
			$has_signal = false;

			if ( get_post_meta( $pid, '_search_intel_author_expertise_notes', true ) ) {
				$has_signal = true;
			}
			if ( get_post_meta( $pid, '_search_intel_ai_summary', true ) ) {
				$has_signal = true;
			}
			if ( get_post_meta( $pid, '_search_intel_fact_checked', true ) ) {
				$has_signal = true;
			}

			if ( $has_signal ) {
				$with_signals++;
			}
		}

		$ratio = $with_signals / count( $post_ids );
		return (int) round( $ratio * 20 );
	}

	/**
	 * Score internal linking density for category posts.
	 *
	 * @param array $post_ids Post IDs.
	 * @return int 0–15.
	 */
	private function score_category_linking( array $post_ids ): int {
		if ( count( $post_ids ) < 2 ) {
			return 0;
		}

		$linked = 0;

		foreach ( $post_ids as $pid ) {
			$links = get_post_meta( $pid, '_search_intel_internal_links', true );
			if ( $links && is_array( $links ) ) {
				foreach ( $links as $target ) {
					if ( in_array( (int) $target, $post_ids, true ) ) {
						$linked++;
						break;
					}
				}
			}
		}

		$ratio = $linked / count( $post_ids );
		return (int) round( $ratio * 15 );
	}

	/**
	 * Score structured data coverage for category posts.
	 *
	 * @param array $post_ids Post IDs.
	 * @return int 0–15.
	 */
	private function score_category_schema( array $post_ids ): int {
		if ( empty( $post_ids ) ) {
			return 0;
		}

		$with_schema = 0;

		foreach ( $post_ids as $pid ) {
			if ( get_post_meta( $pid, '_search_intel_schema_type_extra', true ) ) {
				$with_schema++;
			}
		}

		$ratio = $with_schema / count( $post_ids );
		return (int) round( $ratio * 15 );
	}

	/**
	 * Convert percentage to letter grade.
	 *
	 * @param int $pct 0–100.
	 * @return string
	 */
	private function score_to_grade( int $pct ): string {
		if ( $pct >= 90 ) {
			return 'A';
		}
		if ( $pct >= 80 ) {
			return 'B';
		}
		if ( $pct >= 70 ) {
			return 'C';
		}
		if ( $pct >= 60 ) {
			return 'D';
		}
		return 'F';
	}

	// ── Content Gap Analysis ─────────────────────────────────────────────────

	/**
	 * Analyze content gaps vs competitor coverage.
	 *
	 * Compares category depth, freshness, and coverage to identify
	 * topics that need more content or optimization.
	 *
	 * @return array{topics: array, recommendations: array}
	 */
	public function analyze_content_gaps(): array {
		$categories = get_categories( [ 'hide_empty' => false ] );
		$topics     = [];
		$recs       = [];

		foreach ( $categories as $cat ) {
			$authority = $this->calculate_topic_authority( $cat->term_id );

			$topics[] = [
				'term_id'  => $cat->term_id,
				'name'     => $cat->name,
				'count'    => $cat->count,
				'score'    => $authority['score'],
				'grade'    => $authority['grade'],
				'factors'  => $authority['factors'],
			];

			// Generate recommendations for weak topics.
			if ( $authority['score'] < 50 ) {
				$weakest = $this->find_weakest_factor( $authority['factors'] );

				$recs[] = [
					'topic'    => $cat->name,
					'score'    => $authority['score'],
					'priority' => $authority['score'] < 25 ? 'high' : 'medium',
					'weakest'  => $weakest['label'] ?? '',
					'message'  => $this->gap_recommendation( $cat->name, $authority ),
				];
			}
		}

		// Sort topics by score ascending (weakest first).
		usort( $topics, function ( $a, $b ) {
			return $a['score'] - $b['score'];
		} );

		// Sort recommendations by priority.
		usort( $recs, function ( $a, $b ) {
			$order = [ 'high' => 0, 'medium' => 1, 'low' => 2 ];
			return ( $order[ $a['priority'] ] ?? 3 ) - ( $order[ $b['priority'] ] ?? 3 );
		} );

		return [
			'topics'          => $topics,
			'recommendations' => $recs,
		];
	}

	/**
	 * Find the weakest scoring factor.
	 *
	 * @param array $factors Factor data.
	 * @return array Weakest factor.
	 */
	private function find_weakest_factor( array $factors ): array {
		$weakest = null;
		$lowest  = 1.0;

		foreach ( $factors as $factor ) {
			$ratio = $factor['max'] > 0 ? $factor['score'] / $factor['max'] : 0;
			if ( $ratio < $lowest ) {
				$lowest  = $ratio;
				$weakest = $factor;
			}
		}

		return $weakest ?? [];
	}

	/**
	 * Generate a gap recommendation message.
	 *
	 * @param string $topic     Topic name.
	 * @param array  $authority Authority data.
	 * @return string
	 */
	private function gap_recommendation( string $topic, array $authority ): string {
		$weakest = $this->find_weakest_factor( $authority['factors'] );
		$key     = '';

		foreach ( $authority['factors'] as $k => $f ) {
			if ( $f === $weakest ) {
				$key = $k;
				break;
			}
		}

		$messages = [
			'volume'    => sprintf( 'Add more content to "%s" — only %s.', $topic, $weakest['detail'] ?? 'few posts' ),
			'freshness' => sprintf( 'Update older posts in "%s" to improve freshness signals.', $topic ),
			'eeat'      => sprintf( 'Add author expertise and fact-check dates to "%s" posts.', $topic ),
			'linking'   => sprintf( 'Improve internal linking between "%s" posts.', $topic ),
			'schema'    => sprintf( 'Add structured data to "%s" posts for richer AI understanding.', $topic ),
		];

		return $messages[ $key ] ?? sprintf( 'Improve authority for "%s" (score: %d/100).', $topic, $authority['score'] );
	}

	// ── Topic Cluster Dashboard ──────────────────────────────────────────────

	/**
	 * Get full topic cluster dashboard data with drill-down.
	 *
	 * @return array{clusters: array, site_authority: int}
	 */
	public function get_dashboard_data(): array {
		$categories    = get_categories( [ 'hide_empty' => true ] );
		$clusters      = [];
		$total_score   = 0;

		foreach ( $categories as $cat ) {
			$authority = $this->calculate_topic_authority( $cat->term_id );

			$posts = get_posts( [
				'category'    => $cat->term_id,
				'numberposts' => 50,
				'post_status' => 'publish',
				'fields'      => 'ids',
			] );

			$post_details = [];
			foreach ( $posts as $pid ) {
				$post = get_post( $pid );
				$post_details[] = [
					'id'        => $pid,
					'title'     => $post ? $post->post_title : '',
					'modified'  => get_the_modified_date( 'Y-m-d', $pid ),
					'has_meta'  => (bool) get_post_meta( $pid, '_search_intel_ai_summary', true ),
					'has_schema' => (bool) get_post_meta( $pid, '_search_intel_schema_type_extra', true ),
				];
			}

			$clusters[] = [
				'term_id'   => $cat->term_id,
				'name'      => $cat->name,
				'authority' => $authority,
				'posts'     => $post_details,
			];

			$total_score += $authority['score'];
		}

		$site_authority = count( $clusters ) > 0 ? (int) round( $total_score / count( $clusters ) ) : 0;

		// Sort by authority score ascending.
		usort( $clusters, function ( $a, $b ) {
			return $a['authority']['score'] - $b['authority']['score'];
		} );

		return [
			'clusters'       => $clusters,
			'site_authority' => $site_authority,
		];
	}

	// ── Multimodal Optimization Analysis ─────────────────────────────────────

	/**
	 * Analyze multimodal optimization: image alt text, video transcripts, structured data.
	 *
	 * @return array{summary: array, posts: array}
	 */
	public function analyze_multimodal(): array {
		$posts = get_posts( [
			'numberposts' => 100,
			'post_status' => 'publish',
			'post_type'   => 'post',
			'fields'      => 'ids',
		] );

		$total          = count( $posts );
		$with_alt       = 0;
		$missing_alt    = 0;
		$with_video     = 0;
		$with_schema    = 0;
		$post_details   = [];

		foreach ( $posts as $pid ) {
			$post    = get_post( $pid );
			$content = $post ? $post->post_content : '';
			$issues  = [];

			// Check images for alt text.
			$img_total  = 0;
			$img_no_alt = 0;
			if ( preg_match_all( '/<img\s[^>]*>/i', $content, $imgs ) ) {
				$img_total = count( $imgs[0] );
				foreach ( $imgs[0] as $img ) {
					if ( ! preg_match( '/alt=["\'][^"\']+["\']/', $img ) ) {
						$img_no_alt++;
					}
				}
			}

			if ( $img_no_alt > 0 ) {
				$missing_alt += $img_no_alt;
				$issues[] = sprintf( '%d image(s) missing alt text', $img_no_alt );
			}
			if ( $img_total > 0 && $img_no_alt === 0 ) {
				$with_alt++;
			}

			// Check for video embeds.
			$has_video = (bool) preg_match( '/<iframe[^>]*(?:youtube|vimeo|video)[^>]*>/i', $content );
			if ( $has_video ) {
				$with_video++;
				$video_schema = get_post_meta( $pid, '_search_intel_video_url', true );
				if ( ! $video_schema ) {
					$issues[] = 'Video detected but no VideoObject schema configured';
				}
			}

			// Check structured data.
			$has_schema = (bool) get_post_meta( $pid, '_search_intel_schema_type_extra', true );
			if ( $has_schema ) {
				$with_schema++;
			}

			if ( ! empty( $issues ) ) {
				$post_details[] = [
					'post_id' => $pid,
					'title'   => $post ? $post->post_title : '',
					'issues'  => $issues,
				];
			}
		}

		return [
			'summary' => [
				'total_posts'          => $total,
				'posts_with_alt_text'  => $with_alt,
				'missing_alt_count'    => $missing_alt,
				'posts_with_video'     => $with_video,
				'posts_with_schema'    => $with_schema,
				'alt_coverage'         => $total > 0 ? (int) round( $with_alt / $total * 100 ) : 0,
				'schema_coverage'      => $total > 0 ? (int) round( $with_schema / $total * 100 ) : 0,
			],
			'posts' => $post_details,
		];
	}

	// ── Admin ────────────────────────────────────────────────────────────────

	/**
	 * Register admin submenu page.
	 */
	public function register_admin_page(): void {
		add_submenu_page(
			'search-intel',
			'Topical Authority',
			'Authority Map',
			'manage_options',
			'search-intel-authority',
			[ $this, 'render_admin_page' ]
		);
	}

	/**
	 * Render the admin page.
	 */
	public function render_admin_page(): void {
		$dashboard  = $this->get_dashboard_data();
		$gaps       = $this->analyze_content_gaps();
		$multimodal = $this->analyze_multimodal();

		echo '<div class="wrap"><h1>Topical Authority Map</h1>';

		// Site-wide authority.
		printf( '<h2>Site Authority Score: %d/100</h2>', absint( $dashboard['site_authority'] ) );

		// Cluster table.
		echo '<h3>Topic Clusters</h3>';
		echo '<table class="wp-list-table widefat striped"><thead><tr>';
		echo '<th>Topic</th><th>Score</th><th>Grade</th><th>Posts</th></tr></thead><tbody>';
		foreach ( $dashboard['clusters'] as $c ) {
			printf(
				'<tr><td>%s</td><td>%d/100</td><td>%s</td><td>%d</td></tr>',
				esc_html( $c['name'] ),
				esc_html( $c['authority']['score'] ),
				esc_html( $c['authority']['grade'] ),
				count( $c['posts'] )
			);
		}
		echo '</tbody></table>';

		// Content gaps.
		if ( ! empty( $gaps['recommendations'] ) ) {
			echo '<h3>Content Gap Recommendations</h3><ul>';
			foreach ( $gaps['recommendations'] as $rec ) {
				printf(
					'<li><strong>[%s]</strong> %s</li>',
					esc_html( strtoupper( $rec['priority'] ) ),
					esc_html( $rec['message'] )
				);
			}
			echo '</ul>';
		}

		// Multimodal summary.
		echo '<h3>Multimodal Optimization</h3>';
		$s = $multimodal['summary'];
		printf( '<p>Alt text coverage: %d%% | Schema coverage: %d%% | Videos detected: %d</p>',
			esc_html( $s['alt_coverage'] ), esc_html( $s['schema_coverage'] ), esc_html( $s['posts_with_video'] )
		);

		if ( $s['missing_alt_count'] > 0 ) {
			printf( '<p class="notice notice-warning">%d image(s) across your content are missing alt text.</p>', absint( $s['missing_alt_count'] ) );
		}

		if ( ! empty( $multimodal['posts'] ) ) {
			echo '<table class="wp-list-table widefat striped"><thead><tr>';
			echo '<th>Post</th><th>Issues</th></tr></thead><tbody>';
			foreach ( array_slice( $multimodal['posts'], 0, 15 ) as $p ) {
				printf(
					'<tr><td>%s</td><td>%s</td></tr>',
					esc_html( $p['title'] ),
					esc_html( implode( '; ', $p['issues'] ) )
				);
			}
			echo '</tbody></table>';
		}

		echo '</div>';
	}

	/**
	 * AJAX handler: refresh authority data.
	 */
	public function ajax_refresh(): void {
		check_ajax_referer( 'si_authority_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Unauthorized.' ] );
		}

		wp_send_json_success( $this->get_dashboard_data() );
	}
}
