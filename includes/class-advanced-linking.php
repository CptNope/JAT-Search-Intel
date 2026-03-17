<?php
/**
 * Advanced Internal Linking.
 *
 * Auto-suggests internal links while editing (based on Citation Graph + content
 * similarity), alerts on orphaned content in the post editor sidebar, visualizes
 * link equity flow, and detects broken internal links with auto-fix suggestions.
 *
 * @package Search_Intel
 * @since   3.2.0
 */

namespace Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Advanced_Linking {

    /** @var array Plugin settings. */
    private $settings;

    /** @var string Transient prefix for link suggestions cache. */
    private const SUGGEST_CACHE_PREFIX = 'si_link_suggest_';

    /** @var int Suggestion cache TTL (4 hours). */
    private const SUGGEST_TTL = 14400;

    /** @var string Post meta key for broken internal links. */
    private const BROKEN_LINKS_KEY = '_search_intel_broken_internal_links';

    /**
     * Constructor.
     *
     * @param array $settings Plugin settings.
     */
    public function __construct( array $settings ) {
        $this->settings = $settings;

        if ( ! ( $this->settings['enable_advanced_linking'] ?? false ) ) {
            return;
        }

        // REST endpoint for editor suggestions.
        add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );

        // Broken internal link check on cron.
        add_action( 'search_intel_broken_link_check', [ $this, 'run_broken_link_check' ] );

        if ( ! wp_next_scheduled( 'search_intel_broken_link_check' ) ) {
            wp_schedule_event( time(), 'daily', 'search_intel_broken_link_check' );
        }

        // Admin page.
        if ( is_admin() ) {
            add_action( 'admin_menu', [ $this, 'register_admin_page' ], 25 );
        }
    }

    /*----------------------------------------------------------------------
     * Link suggestion engine
     *--------------------------------------------------------------------*/

    /**
     * Get internal link suggestions for a post based on content similarity
     * and Citation Graph topology.
     *
     * @param int    $post_id Post ID to get suggestions for.
     * @param string $content Post content (plain text).
     * @param int    $limit   Max suggestions to return.
     * @return array Array of suggestions: { target_id, title, url, score, reason, anchor_hint }
     */
    public function get_suggestions( int $post_id, string $content = '', int $limit = 10 ): array {
        $cache_key = self::SUGGEST_CACHE_PREFIX . $post_id;
        $cached    = get_transient( $cache_key );
        if ( false !== $cached && $content === '' ) {
            return $cached;
        }

        if ( $content === '' ) {
            $post = get_post( $post_id );
            $content = $post ? wp_strip_all_tags( $post->post_content ?? '' ) : '';
        }

        $keywords  = $this->extract_keywords( $content );
        $post_cats = wp_get_post_categories( $post_id, [ 'fields' => 'ids' ] );

        // Get existing internal links to avoid duplicates.
        $existing = $this->get_existing_links( $post_id );

        // Candidate posts: same categories/tags, published, not self.
        $candidates = $this->get_candidate_posts( $post_id, $post_cats );

        $scored = [];
        foreach ( $candidates as $cand ) {
            if ( in_array( $cand['id'], $existing, true ) ) {
                continue; // Already linked.
            }

            $score   = 0;
            $reasons = [];

            // Keyword overlap scoring.
            $cand_keywords = $this->extract_keywords(
                wp_strip_all_tags( $cand['content'] ?? '' )
            );
            $overlap = count( array_intersect( $keywords, $cand_keywords ) );
            if ( $overlap > 0 ) {
                $score += min( 40, $overlap * 8 );
                $reasons[] = "{$overlap} keyword overlap";
            }

            // Title match scoring.
            $title_words = array_filter( explode( ' ', mb_strtolower( $cand['title'] ?? '' ) ) );
            $title_match = count( array_intersect( $keywords, $title_words ) );
            if ( $title_match > 0 ) {
                $score += min( 30, $title_match * 10 );
                $reasons[] = "title match ({$title_match} words)";
            }

            // Shared taxonomy scoring.
            $cand_cats    = wp_get_post_categories( $cand['id'], [ 'fields' => 'ids' ] );
            $shared_cats  = count( array_intersect( $post_cats, is_array( $cand_cats ) ? $cand_cats : [] ) );
            if ( $shared_cats > 0 ) {
                $score += $shared_cats * 10;
                $reasons[] = "{$shared_cats} shared categories";
            }

            // Citation Graph boost (cluster proximity).
            $graph_score = $this->get_graph_proximity_score( $post_id, $cand['id'] );
            if ( $graph_score > 0 ) {
                $score += $graph_score;
                $reasons[] = 'same topic cluster';
            }

            if ( $score > 0 ) {
                $scored[] = [
                    'target_id'   => $cand['id'],
                    'title'       => $cand['title'],
                    'url'         => get_permalink( $cand['id'] ),
                    'score'       => min( 100, $score ),
                    'reason'      => implode( ', ', $reasons ),
                    'anchor_hint' => $this->suggest_anchor_text( $content, $cand['title'] ),
                ];
            }
        }

        usort( $scored, fn( $a, $b ) => $b['score'] <=> $a['score'] );
        $suggestions = array_slice( $scored, 0, $limit );

        set_transient( $cache_key, $suggestions, self::SUGGEST_TTL );

        return $suggestions;
    }

    /**
     * Extract meaningful keywords from text.
     *
     * @param string $text Plain text content.
     * @return array Array of lowercase keywords (stop words removed).
     */
    public function extract_keywords( string $text ): array {
        $stop_words = [
            'the', 'a', 'an', 'is', 'are', 'was', 'were', 'be', 'been', 'being',
            'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could',
            'should', 'may', 'might', 'shall', 'can', 'to', 'of', 'in', 'for',
            'on', 'with', 'at', 'by', 'from', 'as', 'into', 'through', 'during',
            'before', 'after', 'above', 'below', 'between', 'out', 'off', 'over',
            'under', 'again', 'further', 'then', 'once', 'and', 'but', 'or', 'nor',
            'not', 'so', 'yet', 'both', 'each', 'few', 'more', 'most', 'other',
            'some', 'such', 'no', 'only', 'own', 'same', 'than', 'too', 'very',
            'just', 'that', 'this', 'these', 'those', 'it', 'its', 'he', 'she',
            'they', 'them', 'their', 'we', 'our', 'you', 'your', 'i', 'me', 'my',
            'what', 'which', 'who', 'whom', 'how', 'when', 'where', 'why', 'all',
            'if', 'about', 'up', 'there', 'here', 'also', 'much', 'many',
        ];

        $words = preg_split( '/[\s\W]+/', mb_strtolower( $text ) );
        $words = array_filter( $words, fn( $w ) => mb_strlen( $w ) >= 3 && ! in_array( $w, $stop_words, true ) );

        // Return unique keywords by frequency (top 30).
        $freq = array_count_values( $words );
        arsort( $freq );

        return array_slice( array_keys( $freq ), 0, 30 );
    }

    /**
     * Get candidate posts from same categories/tags.
     *
     * @param int   $post_id   Current post ID.
     * @param array $cat_ids   Category IDs.
     * @return array Array of [ id, title, content ].
     */
    private function get_candidate_posts( int $post_id, array $cat_ids ): array {
        $args = [
            'post_type'      => 'any',
            'post_status'    => 'publish',
            'numberposts'    => 100,
            'post__not_in'   => [ $post_id ],
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        if ( ! empty( $cat_ids ) ) {
            $args['category__in'] = $cat_ids;
        }

        $posts  = get_posts( $args );
        $result = [];

        foreach ( $posts as $p ) {
            $result[] = [
                'id'      => (int) $p->ID,
                'title'   => (string) $p->post_title,
                'content' => (string) $p->post_content,
            ];
        }

        return $result;
    }

    /**
     * Suggest anchor text by finding the candidate title words in the source content.
     *
     * @param string $content Source content.
     * @param string $title   Target post title.
     * @return string Suggested anchor text.
     */
    private function suggest_anchor_text( string $content, string $title ): string {
        // Try to find a phrase from the title in the content.
        $title_lower   = mb_strtolower( $title );
        $content_lower = mb_strtolower( $content );

        if ( mb_strpos( $content_lower, $title_lower ) !== false ) {
            return $title;
        }

        // Try progressively shorter substrings of the title.
        $words = explode( ' ', $title );
        for ( $len = count( $words ) - 1; $len >= 2; $len-- ) {
            for ( $start = 0; $start + $len <= count( $words ); $start++ ) {
                $phrase = implode( ' ', array_slice( $words, $start, $len ) );
                if ( mb_strpos( $content_lower, mb_strtolower( $phrase ) ) !== false ) {
                    return $phrase;
                }
            }
        }

        return $title; // Fallback to full title.
    }

    /**
     * Get graph proximity score between two posts using Citation Graph.
     *
     * @param int $from_id Source post.
     * @param int $to_id   Target post.
     * @return int Score bonus (0-20).
     */
    private function get_graph_proximity_score( int $from_id, int $to_id ): int {
        $core = Core::get_instance();
        if ( ! isset( $core->citation_graph ) ) {
            return 0;
        }

        // Check if posts are in the same topic cluster.
        if ( method_exists( $core->citation_graph, 'get_post_cluster' ) ) {
            $cluster_a = $core->citation_graph->get_post_cluster( $from_id );
            $cluster_b = $core->citation_graph->get_post_cluster( $to_id );
            if ( $cluster_a !== null && $cluster_a === $cluster_b ) {
                return 20;
            }
        }

        return 0;
    }

    /**
     * Get existing internal link target IDs from a post's content.
     *
     * @param int $post_id Post ID.
     * @return array Array of linked post IDs.
     */
    private function get_existing_links( int $post_id ): array {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return [];
        }

        $content  = $post->post_content ?? '';
        $site_url = home_url();
        $ids      = [];

        if ( preg_match_all( '/<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>/i', $content, $matches ) ) {
            foreach ( $matches[1] as $url ) {
                if ( strpos( $url, $site_url ) === 0 ) {
                    $linked_id = url_to_postid( $url );
                    if ( $linked_id > 0 ) {
                        $ids[] = $linked_id;
                    }
                }
            }
        }

        return array_unique( $ids );
    }

    /*----------------------------------------------------------------------
     * Orphaned content detection
     *--------------------------------------------------------------------*/

    /**
     * Check if a post is orphaned (has zero inbound internal links).
     *
     * @param int $post_id Post ID.
     * @return bool True if orphaned.
     */
    public function is_orphaned( int $post_id ): bool {
        $core = Core::get_instance();

        if ( isset( $core->citation_graph ) && method_exists( $core->citation_graph, 'get_inbound_links' ) ) {
            $inbound = $core->citation_graph->get_inbound_links( $post_id );
            return empty( $inbound );
        }

        // Fallback: search for this post's URL in all published content.
        $permalink = get_permalink( $post_id );
        if ( ! $permalink ) {
            return false;
        }

        $linking_posts = get_posts( [
            'post_type'   => 'any',
            'post_status' => 'publish',
            'fields'      => 'ids',
            'numberposts' => 1,
            's'           => $permalink,
            'post__not_in' => [ $post_id ],
        ] );

        return empty( $linking_posts );
    }

    /**
     * Get all orphaned published posts.
     *
     * @param int $limit Max results.
     * @return array Array of [ id, title, url ].
     */
    public function get_orphaned_posts( int $limit = 50 ): array {
        // If Citation Graph has an orphan list, use it directly.
        $core = Core::get_instance();
        if ( isset( $core->citation_graph ) && method_exists( $core->citation_graph, 'get_orphaned_posts' ) ) {
            $orphans = $core->citation_graph->get_orphaned_posts();
            $result  = [];
            foreach ( array_slice( $orphans, 0, $limit ) as $post_id ) {
                $result[] = [
                    'id'    => $post_id,
                    'title' => get_the_title( $post_id ),
                    'url'   => get_permalink( $post_id ),
                ];
            }
            return $result;
        }

        return [];
    }

    /*----------------------------------------------------------------------
     * Link equity flow
     *--------------------------------------------------------------------*/

    /**
     * Get link equity flow data for visualization.
     *
     * Returns nodes (posts) and edges (internal links) with equity weights.
     *
     * @param int $limit Max nodes.
     * @return array Associative array with 'nodes' and 'edges' keys.
     */
    public function get_equity_flow( int $limit = 50 ): array {
        $core = Core::get_instance();
        if ( ! isset( $core->citation_graph ) ) {
            return [ 'nodes' => [], 'edges' => [] ];
        }

        $posts = get_posts( [
            'post_type'   => 'any',
            'post_status' => 'publish',
            'numberposts' => $limit,
            'orderby'     => 'date',
            'order'       => 'DESC',
        ] );

        $nodes = [];
        $edges = [];

        foreach ( $posts as $p ) {
            $pid = $p->ID ?? ( $p['ID'] ?? 0 );

            $inbound  = method_exists( $core->citation_graph, 'get_inbound_links' )
                ? count( $core->citation_graph->get_inbound_links( $pid ) )
                : 0;
            $outbound = method_exists( $core->citation_graph, 'get_internal_links' )
                ? count( $core->citation_graph->get_internal_links( $pid ) )
                : 0;

            $nodes[] = [
                'id'       => $pid,
                'title'    => $p->post_title ?? ( $p['post_title'] ?? '' ),
                'inbound'  => $inbound,
                'outbound' => $outbound,
            ];

            // Collect edges.
            if ( method_exists( $core->citation_graph, 'get_internal_links' ) ) {
                $links = $core->citation_graph->get_internal_links( $pid );
                foreach ( (array) $links as $link ) {
                    $to = (int) ( $link['target_id'] ?? 0 );
                    if ( $to > 0 ) {
                        $edges[] = [ 'from' => $pid, 'to' => $to ];
                    }
                }
            }
        }

        return [ 'nodes' => $nodes, 'edges' => $edges ];
    }

    /*----------------------------------------------------------------------
     * Broken internal link detection
     *--------------------------------------------------------------------*/

    /**
     * Check a single post for broken internal links.
     *
     * @param int $post_id Post ID.
     * @return array Array of broken links: { url, status, anchor_text }
     */
    public function check_internal_links( int $post_id ): array {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return [];
        }

        $content  = $post->post_content ?? '';
        $site_url = home_url();
        $broken   = [];

        if ( ! preg_match_all( '/<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $content, $matches, PREG_SET_ORDER ) ) {
            return [];
        }

        foreach ( $matches as $match ) {
            $url    = $match[1];
            $anchor = wp_strip_all_tags( $match[2] );

            // Only check internal links.
            if ( strpos( $url, $site_url ) !== 0 && strpos( $url, '/' ) !== 0 ) {
                continue;
            }

            $linked_id = url_to_postid( $url );
            if ( 0 === $linked_id ) {
                // URL doesn't resolve to a post — broken.
                $broken[] = [
                    'url'         => $url,
                    'status'      => 'not_found',
                    'anchor_text' => $anchor,
                    'suggestion'  => $this->suggest_fix( $url ),
                ];
                continue;
            }

            $linked_post = get_post( $linked_id );
            if ( ! $linked_post || ( $linked_post->post_status ?? '' ) !== 'publish' ) {
                $broken[] = [
                    'url'         => $url,
                    'status'      => 'not_published',
                    'anchor_text' => $anchor,
                    'suggestion'  => $this->suggest_fix( $url, $linked_id ),
                ];
            }
        }

        if ( ! empty( $broken ) ) {
            update_post_meta( $post_id, self::BROKEN_LINKS_KEY, $broken );
        } else {
            delete_post_meta( $post_id, self::BROKEN_LINKS_KEY );
        }

        return $broken;
    }

    /**
     * Run broken internal link check across all published posts.
     *
     * @return array Checked/posts_with_broken/total_broken counts.
     */
    public function run_broken_link_check(): array {
        $stats = [ 'checked' => 0, 'posts_with_broken' => 0, 'total_broken' => 0 ];

        $post_ids = get_posts( [
            'post_type'   => 'any',
            'post_status' => 'publish',
            'fields'      => 'ids',
            'numberposts' => -1,
        ] );

        foreach ( $post_ids as $post_id ) {
            $stats['checked']++;
            $broken = $this->check_internal_links( $post_id );
            if ( ! empty( $broken ) ) {
                $stats['posts_with_broken']++;
                $stats['total_broken'] += count( $broken );
            }
        }

        return $stats;
    }

    /**
     * Suggest a fix for a broken internal link.
     *
     * @param string $url       Broken URL.
     * @param int    $linked_id Post ID if resolved (0 if not).
     * @return string Fix suggestion.
     */
    private function suggest_fix( string $url, int $linked_id = 0 ): string {
        if ( $linked_id > 0 ) {
            $post = get_post( $linked_id );
            if ( $post && ( $post->post_status ?? '' ) === 'trash' ) {
                return 'This post is in the trash. Restore it or remove the link.';
            }
            if ( $post && ( $post->post_status ?? '' ) === 'draft' ) {
                return 'This post is a draft. Publish it or remove the link.';
            }
            return 'This post is no longer published. Consider removing the link or redirecting.';
        }

        // Check if a redirect exists.
        $core = Core::get_instance();
        if ( isset( $core->redirects ) && method_exists( $core->redirects, 'find_matching_redirect' ) ) {
            $path     = wp_parse_url( $url, PHP_URL_PATH );
            $redirect = $core->redirects->find_matching_redirect( $path ?? '' );
            if ( $redirect ) {
                return 'A redirect exists for this URL. Update the link to point directly to the redirect target.';
            }
        }

        return 'URL not found. Remove the link or set up a redirect.';
    }

    /*----------------------------------------------------------------------
     * REST API for editor integration
     *--------------------------------------------------------------------*/

    /**
     * Register REST routes for link suggestions.
     */
    public function register_rest_routes(): void {
        register_rest_route( 'search-intel/v1', '/link-suggestions/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'rest_get_suggestions' ],
            'permission_callback' => function () {
                return current_user_can( 'edit_posts' );
            },
            'args' => [
                'id' => [
                    'validate_callback' => fn( $val ) => is_numeric( $val ) && $val > 0,
                ],
            ],
        ] );

        register_rest_route( 'search-intel/v1', '/orphan-check/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'rest_orphan_check' ],
            'permission_callback' => function () {
                return current_user_can( 'edit_posts' );
            },
            'args' => [
                'id' => [
                    'validate_callback' => fn( $val ) => is_numeric( $val ) && $val > 0,
                ],
            ],
        ] );
    }

    /**
     * REST callback: get link suggestions.
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response
     */
    public function rest_get_suggestions( $request ) {
        $post_id = (int) $request['id'];
        return new \WP_REST_Response( $this->get_suggestions( $post_id ), 200 );
    }

    /**
     * REST callback: check if post is orphaned.
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response
     */
    public function rest_orphan_check( $request ) {
        $post_id = (int) $request['id'];
        return new \WP_REST_Response( [
            'orphaned'    => $this->is_orphaned( $post_id ),
            'suggestions' => $this->get_suggestions( $post_id, '', 5 ),
        ], 200 );
    }

    /*----------------------------------------------------------------------
     * Admin page
     *--------------------------------------------------------------------*/

    /**
     * Register the admin page.
     */
    public function register_admin_page(): void {
        add_submenu_page(
            'search-intel',
            'Internal Linking',
            'Internal Linking',
            'manage_options',
            'search-intel-advanced-linking',
            [ $this, 'render_admin_page' ]
        );
    }

    /**
     * Render the admin page.
     */
    public function render_admin_page(): void {
        $orphans = $this->get_orphaned_posts( 25 );
        $equity  = $this->get_equity_flow( 30 );
        ?>
        <div class="wrap">
            <h1>Internal Linking Intelligence</h1>

            <h2>Orphaned Content</h2>
            <p>Posts with zero inbound internal links — they need links from other content to be discoverable.</p>
            <?php if ( empty( $orphans ) ) : ?>
                <div class="notice notice-success"><p>No orphaned content detected.</p></div>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead><tr><th>Post</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ( $orphans as $o ) : ?>
                        <tr>
                            <td>
                                <a href="<?php echo esc_url( get_edit_post_link( $o['id'] ) ); ?>">
                                    <?php echo esc_html( $o['title'] ); ?>
                                </a>
                            </td>
                            <td>
                                <a href="<?php echo esc_url( get_edit_post_link( $o['id'] ) ); ?>" class="button button-small">Edit</a>
                                <a href="<?php echo esc_url( $o['url'] ); ?>" class="button button-small" target="_blank">View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <h2 style="margin-top:30px;">Link Equity Flow</h2>
            <p>Top <?php echo count( $equity['nodes'] ); ?> posts by recency with inbound/outbound link counts.</p>
            <?php if ( ! empty( $equity['nodes'] ) ) : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead><tr><th>Post</th><th style="width:100px;">Inbound</th><th style="width:100px;">Outbound</th></tr></thead>
                    <tbody>
                    <?php foreach ( $equity['nodes'] as $node ) : ?>
                        <tr>
                            <td><?php echo esc_html( $node['title'] ); ?></td>
                            <td><?php echo esc_html( $node['inbound'] ); ?></td>
                            <td><?php echo esc_html( $node['outbound'] ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }
}
