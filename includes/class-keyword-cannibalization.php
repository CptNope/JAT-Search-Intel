<?php
/**
 * Keyword Cannibalization Detector.
 *
 * Detects multiple posts targeting the same focus keyword, generates a
 * cannibalization report grouped by keyword with score comparisons,
 * suggests actions (merge, redirect, differentiate, de-optimize), and
 * integrates with Citation Graph to show link relationships.
 *
 * @package Search_Intel
 * @since   3.2.0
 */

namespace Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Keyword_Cannibalization {

    /** @var array Plugin settings. */
    private $settings;

    /** @var string Post meta key for focus keyword. */
    private const KEYWORD_META_KEY = '_search_intel_focus_keyword';

    /** @var string Transient key for cached report. */
    private const REPORT_CACHE_KEY = 'search_intel_cannibalization_report';

    /** @var int Cache TTL in seconds (6 hours). */
    private const CACHE_TTL = 21600;

    /**
     * Constructor.
     *
     * @param array $settings Plugin settings.
     */
    public function __construct( array $settings ) {
        $this->settings = $settings;

        if ( ! ( $this->settings['enable_cannibalization'] ?? false ) ) {
            return;
        }

        if ( is_admin() ) {
            add_action( 'admin_menu', [ $this, 'register_admin_page' ], 25 );
        }

        // Invalidate cache when a post is saved.
        add_action( 'save_post', [ $this, 'invalidate_cache' ] );
    }

    /*----------------------------------------------------------------------
     * Keyword index building
     *--------------------------------------------------------------------*/

    /**
     * Build a keyword index mapping focus keywords to post IDs.
     *
     * Returns only keywords that appear on 2+ posts (actual cannibalization).
     *
     * @param string $post_type Post type to scan (empty = all public types).
     * @return array Keyword-to-post-IDs map, only for duplicated keywords.
     */
    public function build_keyword_index( string $post_type = '' ): array {
        $args = [
            'post_type'   => $post_type !== '' ? $post_type : 'any',
            'post_status' => 'publish',
            'fields'      => 'ids',
            'numberposts' => -1,
            'meta_key'    => self::KEYWORD_META_KEY,
            'meta_value'  => '',
            'meta_compare' => '!=',
        ];

        $post_ids = get_posts( $args );
        $index    = [];

        foreach ( $post_ids as $post_id ) {
            $keyword = $this->normalize_keyword(
                get_post_meta( $post_id, self::KEYWORD_META_KEY, true )
            );
            if ( $keyword === '' ) {
                continue;
            }
            $index[ $keyword ][] = $post_id;
        }

        // Only keep keywords with 2+ posts.
        return array_filter( $index, fn( $ids ) => count( $ids ) >= 2 );
    }

    /**
     * Normalize a keyword for comparison.
     *
     * Lowercases, trims whitespace, and collapses multiple spaces.
     *
     * @param string $keyword Raw keyword.
     * @return string Normalized keyword.
     */
    public function normalize_keyword( string $keyword ): string {
        $keyword = mb_strtolower( trim( $keyword ) );
        return (string) preg_replace( '/\s+/', ' ', $keyword );
    }

    /*----------------------------------------------------------------------
     * Cannibalization report
     *--------------------------------------------------------------------*/

    /**
     * Generate the full cannibalization report.
     *
     * @param bool $use_cache Whether to use cached report.
     * @return array Array of keyword groups, each containing:
     *               { keyword, post_count, posts: [ { id, title, url, seo_score, aeo_score, geo_score } ], suggestion }
     */
    public function generate_report( bool $use_cache = true ): array {
        if ( $use_cache ) {
            $cached = get_transient( self::REPORT_CACHE_KEY );
            if ( false !== $cached ) {
                return $cached;
            }
        }

        $index  = $this->build_keyword_index();
        $report = [];

        foreach ( $index as $keyword => $post_ids ) {
            $posts = [];
            foreach ( $post_ids as $post_id ) {
                $posts[] = [
                    'id'        => $post_id,
                    'title'     => get_the_title( $post_id ),
                    'url'       => get_permalink( $post_id ),
                    'seo_score' => (int) get_post_meta( $post_id, '_search_intel_seo_score', true ),
                    'aeo_score' => (int) get_post_meta( $post_id, '_search_intel_aeo_score', true ),
                    'geo_score' => (int) get_post_meta( $post_id, '_search_intel_geo_score', true ),
                ];
            }

            // Sort posts by SEO score descending (highest = best performer).
            usort( $posts, fn( $a, $b ) => $b['seo_score'] <=> $a['seo_score'] );

            $group = [
                'keyword'    => $keyword,
                'post_count' => count( $posts ),
                'posts'      => $posts,
                'suggestion' => $this->suggest_action( $posts ),
                'links'      => $this->get_link_relationships( $post_ids ),
            ];

            $report[] = $group;
        }

        // Sort groups by post count descending (worst cannibalization first).
        usort( $report, fn( $a, $b ) => $b['post_count'] <=> $a['post_count'] );

        set_transient( self::REPORT_CACHE_KEY, $report, self::CACHE_TTL );

        return $report;
    }

    /**
     * Get report summary statistics.
     *
     * @return array { total_keywords: int, total_posts_affected: int, groups: int }
     */
    public function get_summary(): array {
        $report      = $this->generate_report();
        $total_posts = 0;

        foreach ( $report as $group ) {
            $total_posts += $group['post_count'];
        }

        return [
            'total_keywords'      => count( $report ),
            'total_posts_affected' => $total_posts,
            'groups'              => count( $report ),
        ];
    }

    /*----------------------------------------------------------------------
     * Action suggestions
     *--------------------------------------------------------------------*/

    /**
     * Suggest an action for a group of cannibalizing posts.
     *
     * Logic:
     * - If one post scores 20+ points above the rest → redirect others to it.
     * - If scores are very close (within 10 points) → differentiate keywords.
     * - If 3+ posts → consider merging the best two and redirecting others.
     * - Otherwise → de-optimize the weaker posts.
     *
     * @param array $posts Sorted array of post data (highest score first).
     * @return array { action: string, detail: string, primary_post_id: int }
     */
    public function suggest_action( array $posts ): array {
        if ( count( $posts ) < 2 ) {
            return [
                'action'          => 'none',
                'detail'          => 'Not enough posts to evaluate.',
                'primary_post_id' => 0,
            ];
        }

        $best       = $posts[0];
        $second     = $posts[1];
        $score_gap  = $best['seo_score'] - $second['seo_score'];
        $post_count = count( $posts );

        // Clear winner — redirect others.
        if ( $score_gap >= 20 ) {
            $others = array_slice( $posts, 1 );
            $ids    = implode( ', ', array_column( $others, 'id' ) );
            return [
                'action'          => 'redirect',
                'detail'          => "Post {$best['id']} is the clear winner (score gap: +{$score_gap}). Consider 301 redirecting posts {$ids} to it.",
                'primary_post_id' => $best['id'],
            ];
        }

        // Many posts, similar scores — merge.
        if ( $post_count >= 3 && $score_gap < 20 ) {
            return [
                'action'          => 'merge',
                'detail'          => "{$post_count} posts compete for this keyword with similar scores. Merge the best content into post {$best['id']} and redirect the rest.",
                'primary_post_id' => $best['id'],
            ];
        }

        // Two posts, close scores — differentiate.
        if ( $score_gap < 10 ) {
            return [
                'action'          => 'differentiate',
                'detail'          => "Posts {$best['id']} and {$second['id']} have similar scores (gap: {$score_gap}). Differentiate their focus keywords to target distinct search intents.",
                'primary_post_id' => $best['id'],
            ];
        }

        // Default — de-optimize the weaker post.
        return [
            'action'          => 'de-optimize',
            'detail'          => "De-optimize post {$second['id']} for this keyword (remove from title/meta) and strengthen post {$best['id']}.",
            'primary_post_id' => $best['id'],
        ];
    }

    /*----------------------------------------------------------------------
     * Citation Graph integration
     *--------------------------------------------------------------------*/

    /**
     * Get internal link relationships between cannibalizing posts.
     *
     * Uses Citation Graph data if available.
     *
     * @param array $post_ids Array of post IDs in the cannibalization group.
     * @return array Array of { from: int, to: int } link pairs.
     */
    public function get_link_relationships( array $post_ids ): array {
        $core = Core::get_instance();
        if ( ! isset( $core->citation_graph ) || ! method_exists( $core->citation_graph, 'get_internal_links' ) ) {
            return [];
        }

        $links = [];
        foreach ( $post_ids as $from_id ) {
            $outbound = $core->citation_graph->get_internal_links( $from_id );
            if ( ! is_array( $outbound ) ) {
                continue;
            }
            foreach ( $outbound as $link ) {
                $to_id = (int) ( $link['target_id'] ?? 0 );
                if ( $to_id > 0 && in_array( $to_id, $post_ids, true ) ) {
                    $links[] = [
                        'from' => $from_id,
                        'to'   => $to_id,
                    ];
                }
            }
        }

        return $links;
    }

    /*----------------------------------------------------------------------
     * Cache management
     *--------------------------------------------------------------------*/

    /**
     * Invalidate the cached report (called on post save).
     */
    public function invalidate_cache(): void {
        delete_transient( self::REPORT_CACHE_KEY );
    }

    /*----------------------------------------------------------------------
     * Admin page
     *--------------------------------------------------------------------*/

    /**
     * Register the cannibalization report admin page.
     */
    public function register_admin_page(): void {
        add_submenu_page(
            'search-intel',
            'Keyword Cannibalization',
            'Cannibalization',
            'manage_options',
            'search-intel-cannibalization',
            [ $this, 'render_admin_page' ]
        );
    }

    /**
     * Render the cannibalization report admin page.
     */
    public function render_admin_page(): void {
        $report  = $this->generate_report( false );
        $summary = $this->get_summary();
        ?>
        <div class="wrap">
            <h1>Keyword Cannibalization Report</h1>
            <p>Found <strong><?php echo esc_html( (string) $summary['total_keywords'] ); ?></strong> cannibalized keywords
               affecting <strong><?php echo esc_html( (string) $summary['total_posts_affected'] ); ?></strong> posts.</p>

            <?php if ( empty( $report ) ) : ?>
                <div class="notice notice-success"><p>No keyword cannibalization detected. Each focus keyword is unique.</p></div>
            <?php else : ?>
                <?php foreach ( $report as $group ) : ?>
                    <div class="card" style="max-width:100%;margin-bottom:20px;padding:15px;">
                        <h2 style="margin-top:0;">
                            Keyword: <code><?php echo esc_html( $group['keyword'] ); ?></code>
                            <span style="font-size:14px;color:#666;">(<?php echo esc_html( $group['post_count'] ); ?> posts)</span>
                        </h2>

                        <table class="wp-list-table widefat fixed striped" style="margin-bottom:10px;">
                            <thead>
                                <tr>
                                    <th>Post</th>
                                    <th style="width:80px;">SEO</th>
                                    <th style="width:80px;">AEO</th>
                                    <th style="width:80px;">GEO</th>
                                    <th style="width:100px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ( $group['posts'] as $i => $post ) : ?>
                                <tr<?php echo 0 === $i ? ' style="background:#d1fae5;"' : ''; ?>>
                                    <td>
                                        <a href="<?php echo esc_url( get_edit_post_link( $post['id'] ) ); ?>">
                                            <?php echo esc_html( $post['title'] ); ?>
                                        </a>
                                        <?php if ( 0 === $i ) : ?><span style="color:#16a34a;font-weight:bold;"> ★ Best</span><?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html( $post['seo_score'] ); ?></td>
                                    <td><?php echo esc_html( $post['aeo_score'] ); ?></td>
                                    <td><?php echo esc_html( $post['geo_score'] ); ?></td>
                                    <td>
                                        <a href="<?php echo esc_url( get_edit_post_link( $post['id'] ) ); ?>" class="button button-small">Edit</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>

                        <div style="background:#fef3c7;padding:10px;border-left:4px solid #d97706;">
                            <strong>Recommendation (<?php echo esc_html( ucfirst( $group['suggestion']['action'] ) ); ?>):</strong>
                            <?php echo esc_html( $group['suggestion']['detail'] ); ?>
                        </div>

                        <?php if ( ! empty( $group['links'] ) ) : ?>
                            <p style="margin-top:10px;color:#666;font-size:13px;">
                                <strong>Internal links between these posts:</strong>
                                <?php foreach ( $group['links'] as $link ) : ?>
                                    Post <?php echo esc_html( $link['from'] ); ?> → Post <?php echo esc_html( $link['to'] ); ?>&ensp;
                                <?php endforeach; ?>
                            </p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php
    }
}
