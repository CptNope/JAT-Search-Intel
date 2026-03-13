<?php
namespace Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Citation Graph Builder.
 *
 * Builds an internal link graph, tracks external citations, auto-detects topic clusters,
 * identifies pillar pages, finds missing internal links and orphaned content,
 * and calculates internal linking scores per cluster.
 */
class Citation_Graph {

	/** @var array */
	private $settings;

	public function __construct( array $settings ) {
		$this->settings = $settings;

		if ( empty( $settings['enable_citation_graph'] ) ) {
			return;
		}

		add_action( 'save_post', [ $this, 'update_post_graph' ], 20, 2 );
		add_action( 'admin_menu', [ $this, 'register_admin_page' ] );
		add_action( 'wp_ajax_si_rebuild_graph', [ $this, 'ajax_rebuild' ] );
	}

	// ── Internal Link Graph ──────────────────────────────────────────────────

	/**
	 * Extract all internal links from a post's content.
	 *
	 * @param int $post_id Post ID.
	 * @return array{outbound: array<int>, urls: array<string>}
	 */
	public function extract_internal_links( int $post_id ): array {
		$post = get_post( $post_id );
		if ( ! $post || empty( $post->post_content ) ) {
			return [ 'outbound' => [], 'urls' => [] ];
		}

		$home    = home_url();
		$content = $post->post_content;
		$ids     = [];
		$urls    = [];

		if ( ! preg_match_all( '/<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>/i', $content, $matches ) ) {
			return [ 'outbound' => [], 'urls' => [] ];
		}

		foreach ( $matches[1] as $url ) {
			$url = esc_url( $url );

			// Only internal links.
			if ( strpos( $url, $home ) !== 0 && strpos( $url, '/' ) !== 0 ) {
				continue;
			}

			$urls[]    = $url;
			$target_id = url_to_postid( $url );
			if ( $target_id && $target_id !== $post_id ) {
				$ids[] = $target_id;
			}
		}

		return [
			'outbound' => array_unique( $ids ),
			'urls'     => array_unique( $urls ),
		];
	}

	/**
	 * Extract all external links from a post's content.
	 *
	 * @param int $post_id Post ID.
	 * @return array List of external URLs.
	 */
	public function extract_external_links( int $post_id ): array {
		$post = get_post( $post_id );
		if ( ! $post || empty( $post->post_content ) ) {
			return [];
		}

		$home    = home_url();
		$content = $post->post_content;
		$urls    = [];

		if ( ! preg_match_all( '/<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>/i', $content, $matches ) ) {
			return [];
		}

		foreach ( $matches[1] as $url ) {
			$url = esc_url( $url );

			// Skip internal and relative URLs.
			if ( strpos( $url, $home ) === 0 || strpos( $url, '/' ) === 0 || strpos( $url, 'http' ) !== 0 ) {
				continue;
			}

			$urls[] = $url;
		}

		return array_unique( $urls );
	}

	/**
	 * Build the full internal link graph for all published posts.
	 *
	 * @return array{nodes: array, edges: array}
	 */
	public function build_link_graph(): array {
		$posts = get_posts( [
			'numberposts' => -1,
			'post_status' => 'publish',
			'post_type'   => [ 'post', 'page' ],
			'fields'      => 'ids',
		] );

		$nodes = [];
		$edges = [];

		foreach ( $posts as $pid ) {
			$post = get_post( $pid );
			$cats = wp_get_post_categories( $pid, [ 'fields' => 'names' ] );

			$nodes[ $pid ] = [
				'id'         => $pid,
				'title'      => $post ? $post->post_title : '',
				'type'       => $post ? $post->post_type : 'post',
				'categories' => is_array( $cats ) ? $cats : [],
				'url'        => get_permalink( $pid ),
			];

			$links = $this->extract_internal_links( $pid );
			foreach ( $links['outbound'] as $target ) {
				$edges[] = [
					'source' => $pid,
					'target' => $target,
				];
			}
		}

		return [
			'nodes' => $nodes,
			'edges' => $edges,
		];
	}

	/**
	 * Get inbound links for a specific post.
	 *
	 * @param int   $post_id Target post ID.
	 * @param array $graph   Pre-built graph (optional — builds if empty).
	 * @return array List of source post IDs linking to this post.
	 */
	public function get_inbound_links( int $post_id, array $graph = [] ): array {
		if ( empty( $graph ) ) {
			$graph = $this->build_link_graph();
		}

		$inbound = [];
		foreach ( $graph['edges'] as $edge ) {
			if ( (int) $edge['target'] === $post_id ) {
				$inbound[] = (int) $edge['source'];
			}
		}

		return array_unique( $inbound );
	}

	// ── External Citation Tracking ───────────────────────────────────────────

	/**
	 * Get external citation data per post.
	 *
	 * @param int $post_id Post ID.
	 * @return array{outbound_citations: array, domain_distribution: array}
	 */
	public function get_citation_data( int $post_id ): array {
		$external = $this->extract_external_links( $post_id );

		$domains = [];
		foreach ( $external as $url ) {
			$parsed = wp_parse_url( $url );
			$host   = $parsed['host'] ?? '';
			if ( $host ) {
				$domains[ $host ] = ( $domains[ $host ] ?? 0 ) + 1;
			}
		}

		arsort( $domains );

		return [
			'outbound_citations'  => $external,
			'domain_distribution' => $domains,
		];
	}

	// ── Topic Cluster Detection ──────────────────────────────────────────────

	/**
	 * Auto-detect topic clusters based on shared categories, tags, and link density.
	 *
	 * @return array List of cluster arrays.
	 */
	public function detect_topic_clusters(): array {
		$graph      = $this->build_link_graph();
		$categories = get_categories( [ 'hide_empty' => true ] );
		$clusters   = [];

		foreach ( $categories as $cat ) {
			$posts = get_posts( [
				'category'    => $cat->term_id,
				'numberposts' => 100,
				'post_status' => 'publish',
				'fields'      => 'ids',
			] );

			if ( count( $posts ) < 2 ) {
				continue;
			}

			// Calculate internal link density within cluster.
			$internal_links = 0;
			$possible_links = count( $posts ) * ( count( $posts ) - 1 );

			foreach ( $graph['edges'] as $edge ) {
				if ( in_array( (int) $edge['source'], $posts, true ) && in_array( (int) $edge['target'], $posts, true ) ) {
					$internal_links++;
				}
			}

			$density = $possible_links > 0 ? round( $internal_links / $possible_links, 4 ) : 0;

			// Identify the pillar (most inbound links within cluster).
			$inbound_counts = [];
			foreach ( $posts as $pid ) {
				$inbound_counts[ $pid ] = 0;
			}
			foreach ( $graph['edges'] as $edge ) {
				$target = (int) $edge['target'];
				if ( isset( $inbound_counts[ $target ] ) && in_array( (int) $edge['source'], $posts, true ) ) {
					$inbound_counts[ $target ]++;
				}
			}

			arsort( $inbound_counts );
			$pillar_id = array_key_first( $inbound_counts );

			$clusters[] = [
				'category'       => $cat->name,
				'term_id'        => $cat->term_id,
				'post_count'     => count( $posts ),
				'post_ids'       => $posts,
				'internal_links' => $internal_links,
				'link_density'   => $density,
				'pillar_id'      => $pillar_id,
				'pillar_title'   => $pillar_id ? get_the_title( $pillar_id ) : '',
				'linking_score'  => $this->calculate_cluster_linking_score( $posts, $graph ),
			];
		}

		// Sort by post count descending.
		usort( $clusters, function ( $a, $b ) {
			return $b['post_count'] - $a['post_count'];
		} );

		return $clusters;
	}

	/**
	 * Identify potential pillar pages across the site.
	 *
	 * A pillar page has: many inbound internal links, broad topic coverage, and high word count.
	 *
	 * @return array List of pillar page candidates.
	 */
	public function identify_pillar_pages(): array {
		$graph    = $this->build_link_graph();
		$pillars  = [];

		foreach ( $graph['nodes'] as $pid => $node ) {
			$inbound  = $this->get_inbound_links( $pid, $graph );
			$outbound = 0;
			foreach ( $graph['edges'] as $edge ) {
				if ( (int) $edge['source'] === $pid ) {
					$outbound++;
				}
			}

			$post      = get_post( $pid );
			$wordcount = $post ? str_word_count( wp_strip_all_tags( $post->post_content ) ) : 0;

			// Pillar heuristic: >3 inbound, >2 outbound, >1000 words.
			if ( count( $inbound ) >= 3 && $outbound >= 2 && $wordcount >= 1000 ) {
				$pillars[] = [
					'post_id'        => $pid,
					'title'          => $node['title'],
					'inbound_count'  => count( $inbound ),
					'outbound_count' => $outbound,
					'word_count'     => $wordcount,
					'categories'     => $node['categories'],
					'score'          => count( $inbound ) + $outbound + (int) ( $wordcount / 500 ),
				];
			}
		}

		// Sort by score descending.
		usort( $pillars, function ( $a, $b ) {
			return $b['score'] - $a['score'];
		} );

		return $pillars;
	}

	// ── Missing Links & Orphans ──────────────────────────────────────────────

	/**
	 * Detect missing internal links — cluster posts that should link to each other but don't.
	 *
	 * @return array List of missing link suggestions.
	 */
	public function detect_missing_links(): array {
		$clusters = $this->detect_topic_clusters();
		$graph    = $this->build_link_graph();
		$missing  = [];

		foreach ( $clusters as $cluster ) {
			$posts = $cluster['post_ids'];
			$edges = [];

			foreach ( $graph['edges'] as $edge ) {
				$key = $edge['source'] . '->' . $edge['target'];
				$edges[ $key ] = true;
			}

			foreach ( $posts as $source ) {
				foreach ( $posts as $target ) {
					if ( $source === $target ) {
						continue;
					}

					$key = $source . '->' . $target;
					if ( ! isset( $edges[ $key ] ) ) {
						$missing[] = [
							'source_id'    => $source,
							'source_title' => get_the_title( $source ),
							'target_id'    => $target,
							'target_title' => get_the_title( $target ),
							'cluster'      => $cluster['category'],
							'reason'       => 'Same topic cluster, no link exists',
						];
					}
				}
			}
		}

		return $missing;
	}

	/**
	 * Detect orphaned content — published posts with zero inbound internal links.
	 *
	 * @return array List of orphaned posts.
	 */
	public function detect_orphaned_content(): array {
		$graph    = $this->build_link_graph();
		$orphaned = [];

		// Build inbound map.
		$inbound_map = [];
		foreach ( $graph['edges'] as $edge ) {
			$target = (int) $edge['target'];
			$inbound_map[ $target ] = ( $inbound_map[ $target ] ?? 0 ) + 1;
		}

		foreach ( $graph['nodes'] as $pid => $node ) {
			if ( ! isset( $inbound_map[ $pid ] ) ) {
				$orphaned[] = [
					'post_id' => $pid,
					'title'   => $node['title'],
					'type'    => $node['type'],
					'url'     => $node['url'],
				];
			}
		}

		return $orphaned;
	}

	// ── Linking Score ────────────────────────────────────────────────────────

	/**
	 * Calculate internal linking score for a cluster (0–100).
	 *
	 * @param array $post_ids Cluster post IDs.
	 * @param array $graph    Link graph.
	 * @return int Score 0–100.
	 */
	public function calculate_cluster_linking_score( array $post_ids, array $graph ): int {
		$count = count( $post_ids );
		if ( $count < 2 ) {
			return 0;
		}

		$possible = $count * ( $count - 1 );
		$actual   = 0;

		foreach ( $graph['edges'] as $edge ) {
			if (
				in_array( (int) $edge['source'], $post_ids, true ) &&
				in_array( (int) $edge['target'], $post_ids, true )
			) {
				$actual++;
			}
		}

		// A 30% link density is considered "excellent" for a cluster.
		$density = $actual / $possible;
		$score   = (int) min( 100, round( ( $density / 0.3 ) * 100 ) );

		return $score;
	}

	// ── Post Save Hook ───────────────────────────────────────────────────────

	/**
	 * Update graph data when a post is saved.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 */
	public function update_post_graph( int $post_id, $post ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( $post->post_status !== 'publish' ) {
			return;
		}

		$links    = $this->extract_internal_links( $post_id );
		$external = $this->extract_external_links( $post_id );

		update_post_meta( $post_id, '_search_intel_internal_links', $links['outbound'] );
		update_post_meta( $post_id, '_search_intel_external_links', $external );
	}

	// ── Admin ────────────────────────────────────────────────────────────────

	/**
	 * Register admin submenu page.
	 */
	public function register_admin_page(): void {
		add_submenu_page(
			'search-intel',
			'Citation Graph',
			'Citation Graph',
			'manage_options',
			'search-intel-citation-graph',
			[ $this, 'render_admin_page' ]
		);
	}

	/**
	 * Render the admin page.
	 */
	public function render_admin_page(): void {
		$clusters = $this->detect_topic_clusters();
		$orphaned = $this->detect_orphaned_content();
		$pillars  = $this->identify_pillar_pages();

		echo '<div class="wrap"><h1>Citation Graph</h1>';

		// Topic clusters.
		echo '<h2>Topic Clusters</h2>';
		if ( ! empty( $clusters ) ) {
			echo '<table class="wp-list-table widefat striped"><thead><tr>';
			echo '<th>Cluster</th><th>Posts</th><th>Internal Links</th><th>Density</th><th>Pillar</th><th>Score</th>';
			echo '</tr></thead><tbody>';
			foreach ( $clusters as $c ) {
				printf(
					'<tr><td>%s</td><td>%d</td><td>%d</td><td>%.1f%%</td><td>%s</td><td>%d/100</td></tr>',
					esc_html( $c['category'] ),
					$c['post_count'],
					$c['internal_links'],
					$c['link_density'] * 100,
					esc_html( $c['pillar_title'] ),
					$c['linking_score']
				);
			}
			echo '</tbody></table>';
		} else {
			echo '<p>No topic clusters detected yet.</p>';
		}

		// Pillar pages.
		echo '<h2>Pillar Pages</h2>';
		if ( ! empty( $pillars ) ) {
			echo '<table class="wp-list-table widefat striped"><thead><tr>';
			echo '<th>Title</th><th>Inbound</th><th>Outbound</th><th>Words</th><th>Score</th>';
			echo '</tr></thead><tbody>';
			foreach ( $pillars as $p ) {
				printf(
					'<tr><td>%s</td><td>%d</td><td>%d</td><td>%s</td><td>%d</td></tr>',
					esc_html( $p['title'] ),
					$p['inbound_count'],
					$p['outbound_count'],
					number_format( $p['word_count'] ),
					$p['score']
				);
			}
			echo '</tbody></table>';
		} else {
			echo '<p>No pillar pages identified yet.</p>';
		}

		// Orphaned content.
		echo '<h2>Orphaned Content</h2>';
		if ( ! empty( $orphaned ) ) {
			echo '<table class="wp-list-table widefat striped"><thead><tr>';
			echo '<th>Title</th><th>Type</th><th>Actions</th>';
			echo '</tr></thead><tbody>';
			foreach ( array_slice( $orphaned, 0, 20 ) as $o ) {
				printf(
					'<tr><td><a href="%s">%s</a></td><td>%s</td><td><a href="%s">Edit</a></td></tr>',
					esc_url( $o['url'] ),
					esc_html( $o['title'] ),
					esc_html( $o['type'] ),
					esc_url( get_edit_post_link( $o['post_id'] ) ?? '' )
				);
			}
			echo '</tbody></table>';
		} else {
			echo '<p>No orphaned content found.</p>';
		}

		echo '</div>';
	}

	/**
	 * AJAX handler: rebuild graph.
	 */
	public function ajax_rebuild(): void {
		check_ajax_referer( 'si_graph_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Unauthorized.' ] );
		}

		$graph    = $this->build_link_graph();
		$clusters = $this->detect_topic_clusters();

		wp_send_json_success( [
			'nodes'    => count( $graph['nodes'] ),
			'edges'    => count( $graph['edges'] ),
			'clusters' => count( $clusters ),
		] );
	}
}
