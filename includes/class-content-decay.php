<?php
/**
 * Content Decay Detection.
 *
 * Tracks historical score snapshots per post, detects posts whose scores have
 * dropped significantly over time, provides an admin dashboard of declining
 * content, auto-queues decaying posts for re-analysis, and suggests content
 * refresh actions based on score changes.
 *
 * @package Search_Intel
 * @since   3.2.0
 */

namespace Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Content_Decay {

    /** @var array Plugin settings. */
    private $settings;

    /** @var string Option prefix for score snapshots. */
    private const SNAPSHOT_META_KEY = '_search_intel_score_history';

    /** @var string Post meta key for decay flag. */
    private const DECAY_FLAG_KEY = '_search_intel_decaying';

    /** @var int Maximum snapshots to retain per post. */
    private const MAX_SNAPSHOTS = 90;

    /** @var float Default decay threshold (percentage drop). */
    private const DEFAULT_DECAY_THRESHOLD = 10.0;

    /** @var int Default lookback period in days. */
    private const DEFAULT_LOOKBACK_DAYS = 30;

    /**
     * Constructor.
     *
     * @param array $settings Plugin settings.
     */
    public function __construct( array $settings ) {
        $this->settings = $settings;

        if ( ! ( $this->settings['enable_content_decay'] ?? false ) ) {
            return;
        }

        // Store snapshot on every analysis.
        add_action( 'search_intel_post_analyzed', [ $this, 'store_snapshot' ], 10, 2 );

        // Daily cron for decay detection.
        add_action( 'search_intel_decay_check', [ $this, 'run_decay_check' ] );

        if ( ! wp_next_scheduled( 'search_intel_decay_check' ) ) {
            wp_schedule_event( time(), 'daily', 'search_intel_decay_check' );
        }

        // Admin page.
        if ( is_admin() ) {
            add_action( 'admin_menu', [ $this, 'register_admin_page' ], 25 );
        }
    }

    /*----------------------------------------------------------------------
     * Score snapshot storage
     *--------------------------------------------------------------------*/

    /**
     * Store a score snapshot for a post.
     *
     * Called after every content analysis via the `search_intel_post_analyzed`
     * action. Stores date + SEO/AEO/GEO/readability scores.
     *
     * @param int   $post_id Post ID.
     * @param array $scores  { seo: int, aeo: int, geo: int, readability: int }
     */
    public function store_snapshot( int $post_id, array $scores ): void {
        $history = $this->get_score_history( $post_id );
        $today   = gmdate( 'Y-m-d' );

        // Replace today's entry if it already exists (re-analysis same day).
        $history = array_filter( $history, fn( $snap ) => ( $snap['date'] ?? '' ) !== $today );

        $history[] = [
            'date'        => $today,
            'seo'         => (int) ( $scores['seo'] ?? 0 ),
            'aeo'         => (int) ( $scores['aeo'] ?? 0 ),
            'geo'         => (int) ( $scores['geo'] ?? 0 ),
            'readability' => (int) ( $scores['readability'] ?? 0 ),
        ];

        // Trim to max snapshots, keeping most recent.
        if ( count( $history ) > self::MAX_SNAPSHOTS ) {
            $history = array_slice( $history, -self::MAX_SNAPSHOTS );
        }

        update_post_meta( $post_id, self::SNAPSHOT_META_KEY, $history );
    }

    /**
     * Get the full score history for a post.
     *
     * @param int $post_id Post ID.
     * @return array Array of snapshot entries sorted by date ascending.
     */
    public function get_score_history( int $post_id ): array {
        $history = get_post_meta( $post_id, self::SNAPSHOT_META_KEY, true );
        if ( ! is_array( $history ) ) {
            return [];
        }
        usort( $history, fn( $a, $b ) => strcmp( $a['date'] ?? '', $b['date'] ?? '' ) );
        return $history;
    }

    /**
     * Get the latest snapshot for a post.
     *
     * @param int $post_id Post ID.
     * @return array|null Snapshot or null if no history.
     */
    public function get_latest_snapshot( int $post_id ): ?array {
        $history = $this->get_score_history( $post_id );
        return ! empty( $history ) ? end( $history ) : null;
    }

    /**
     * Get a snapshot from N days ago (or closest earlier one).
     *
     * @param int $post_id Post ID.
     * @param int $days_ago Number of days to look back.
     * @return array|null Snapshot or null.
     */
    public function get_snapshot_days_ago( int $post_id, int $days_ago ): ?array {
        $target  = gmdate( 'Y-m-d', strtotime( "-{$days_ago} days" ) );
        $history = $this->get_score_history( $post_id );

        // Find the snapshot on or closest before the target date.
        $best = null;
        foreach ( $history as $snap ) {
            if ( ( $snap['date'] ?? '' ) <= $target ) {
                $best = $snap;
            }
        }
        return $best;
    }

    /*----------------------------------------------------------------------
     * Decay detection
     *--------------------------------------------------------------------*/

    /**
     * Get the decay threshold percentage from settings.
     *
     * @return float
     */
    public function get_decay_threshold(): float {
        return (float) ( $this->settings['decay_threshold'] ?? self::DEFAULT_DECAY_THRESHOLD );
    }

    /**
     * Get the lookback period in days from settings.
     *
     * @return int
     */
    public function get_lookback_days(): int {
        return max( 7, (int) ( $this->settings['decay_lookback_days'] ?? self::DEFAULT_LOOKBACK_DAYS ) );
    }

    /**
     * Detect whether a specific post is decaying.
     *
     * A post is decaying if any of its module scores (SEO, AEO, GEO) have
     * dropped by more than the threshold percentage over the lookback period.
     *
     * @param int $post_id Post ID.
     * @return array { decaying: bool, drops: array of { module: string, old: int, new: int, drop_pct: float } }
     */
    public function detect_decay( int $post_id ): array {
        $result = [ 'decaying' => false, 'drops' => [] ];

        $latest = $this->get_latest_snapshot( $post_id );
        $older  = $this->get_snapshot_days_ago( $post_id, $this->get_lookback_days() );

        if ( null === $latest || null === $older ) {
            return $result;
        }

        // Don't compare if both snapshots are from the same date.
        if ( ( $latest['date'] ?? '' ) === ( $older['date'] ?? '' ) ) {
            return $result;
        }

        $threshold = $this->get_decay_threshold();

        foreach ( [ 'seo', 'aeo', 'geo' ] as $module ) {
            $old_score = (int) ( $older[ $module ] ?? 0 );
            $new_score = (int) ( $latest[ $module ] ?? 0 );

            if ( $old_score <= 0 ) {
                continue;
            }

            $drop_pct = ( ( $old_score - $new_score ) / $old_score ) * 100;

            if ( $drop_pct >= $threshold ) {
                $result['decaying'] = true;
                $result['drops'][]  = [
                    'module'   => strtoupper( $module ),
                    'old'      => $old_score,
                    'new'      => $new_score,
                    'drop_pct' => round( $drop_pct, 1 ),
                ];
            }
        }

        return $result;
    }

    /**
     * Run the daily decay check across all published posts with score history.
     *
     * Flags decaying posts and optionally queues them for re-analysis.
     *
     * @return array { checked: int, decaying: int, queued: int }
     */
    public function run_decay_check(): array {
        $stats = [ 'checked' => 0, 'decaying' => 0, 'queued' => 0 ];

        $post_ids = get_posts( [
            'post_type'   => 'any',
            'post_status' => 'publish',
            'fields'      => 'ids',
            'numberposts' => -1,
            'meta_key'    => self::SNAPSHOT_META_KEY,
        ] );

        foreach ( $post_ids as $post_id ) {
            $stats['checked']++;
            $result = $this->detect_decay( $post_id );

            if ( $result['decaying'] ) {
                $stats['decaying']++;
                update_post_meta( $post_id, self::DECAY_FLAG_KEY, [
                    'detected' => gmdate( 'Y-m-d H:i:s' ),
                    'drops'    => $result['drops'],
                ] );

                // Auto-queue for re-analysis if Scale module is available.
                if ( $this->settings['decay_auto_queue'] ?? true ) {
                    $core = Core::get_instance();
                    if ( isset( $core->scale ) && method_exists( $core->scale, 'enqueue_job' ) ) {
                        $core->scale->enqueue_job( 'rescore', $post_id );
                        $stats['queued']++;
                    }
                }
            } else {
                delete_post_meta( $post_id, self::DECAY_FLAG_KEY );
            }
        }

        return $stats;
    }

    /*----------------------------------------------------------------------
     * Decaying posts retrieval
     *--------------------------------------------------------------------*/

    /**
     * Get all currently decaying posts.
     *
     * @param int $limit Max posts to return.
     * @return array Array of [ post_id, title, url, drops, detected ] sorted by worst drop first.
     */
    public function get_decaying_posts( int $limit = 50 ): array {
        $post_ids = get_posts( [
            'post_type'   => 'any',
            'post_status' => 'publish',
            'fields'      => 'ids',
            'numberposts' => $limit * 2, // Over-fetch in case some are stale.
            'meta_key'    => self::DECAY_FLAG_KEY,
        ] );

        $results = [];
        foreach ( $post_ids as $post_id ) {
            $flag = get_post_meta( $post_id, self::DECAY_FLAG_KEY, true );
            if ( ! is_array( $flag ) || empty( $flag['drops'] ) ) {
                continue;
            }

            // Calculate the worst single drop percentage.
            $worst_drop = 0;
            foreach ( $flag['drops'] as $d ) {
                if ( ( $d['drop_pct'] ?? 0 ) > $worst_drop ) {
                    $worst_drop = $d['drop_pct'];
                }
            }

            $results[] = [
                'post_id'    => $post_id,
                'title'      => get_the_title( $post_id ),
                'url'        => get_permalink( $post_id ),
                'drops'      => $flag['drops'],
                'detected'   => $flag['detected'] ?? '',
                'worst_drop' => $worst_drop,
            ];
        }

        // Sort by worst drop descending.
        usort( $results, fn( $a, $b ) => $b['worst_drop'] <=> $a['worst_drop'] );

        return array_slice( $results, 0, $limit );
    }

    /*----------------------------------------------------------------------
     * Content refresh suggestions
     *--------------------------------------------------------------------*/

    /**
     * Generate refresh suggestions for a decaying post.
     *
     * Analyzes which scores dropped and returns actionable recommendations.
     *
     * @param int $post_id Post ID.
     * @return array Array of suggestion strings.
     */
    public function get_refresh_suggestions( int $post_id ): array {
        $result = $this->detect_decay( $post_id );
        if ( ! $result['decaying'] ) {
            return [ 'This post is not currently flagged as decaying.' ];
        }

        $suggestions = [];

        foreach ( $result['drops'] as $drop ) {
            $module = $drop['module'];
            $pct    = $drop['drop_pct'];

            switch ( $module ) {
                case 'SEO':
                    $suggestions[] = "SEO score dropped {$pct}%: Review your focus keyword placement, update the meta description, check for broken links, and ensure headings contain relevant keywords.";
                    if ( $drop['new'] < 50 ) {
                        $suggestions[] = 'SEO score is below 50: Consider a comprehensive content refresh — update statistics, add new internal links, and expand thin sections.';
                    }
                    break;

                case 'AEO':
                    $suggestions[] = "AEO score dropped {$pct}%: Add or update FAQ sections, ensure direct answers appear in the first paragraph, add structured lists or tables, and review question-style headings.";
                    if ( $drop['new'] < 50 ) {
                        $suggestions[] = 'AEO score is below 50: Add FAQ schema data, include a clear direct-answer field, and structure content with concise answer blocks.';
                    }
                    break;

                case 'GEO':
                    $suggestions[] = "GEO score dropped {$pct}%: Update the AI summary, add source citations, define key entities, refresh the fact-check date, and ensure author credentials are complete.";
                    if ( $drop['new'] < 50 ) {
                        $suggestions[] = 'GEO score is below 50: Add an AI summary, include at least 2 source citations, define 3+ entities, and ensure content depth exceeds 800 words.';
                    }
                    break;
            }
        }

        $suggestions[] = 'Tip: Update the post\'s modified date by saving it — this signals freshness to search engines and AI systems.';

        return $suggestions;
    }

    /*----------------------------------------------------------------------
     * Score trend data for sparklines
     *--------------------------------------------------------------------*/

    /**
     * Get score trend data for a post, suitable for sparkline visualization.
     *
     * @param int    $post_id Post ID.
     * @param string $module  Module key: 'seo', 'aeo', 'geo', or 'readability'.
     * @param int    $days    Number of days to include (default 30).
     * @return array Array of [ date => string, score => int ] entries.
     */
    public function get_trend_data( int $post_id, string $module = 'seo', int $days = 30 ): array {
        $history  = $this->get_score_history( $post_id );
        $cutoff   = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );
        $filtered = [];

        foreach ( $history as $snap ) {
            if ( ( $snap['date'] ?? '' ) >= $cutoff ) {
                $filtered[] = [
                    'date'  => $snap['date'],
                    'score' => (int) ( $snap[ $module ] ?? 0 ),
                ];
            }
        }

        return $filtered;
    }

    /**
     * Get the overall score change for a post over a period.
     *
     * @param int    $post_id Post ID.
     * @param string $module  Module key.
     * @param int    $days    Lookback period.
     * @return array { current: int, previous: int, change: int, change_pct: float }
     */
    public function get_score_change( int $post_id, string $module = 'seo', int $days = 30 ): array {
        $latest = $this->get_latest_snapshot( $post_id );
        $older  = $this->get_snapshot_days_ago( $post_id, $days );

        $current  = (int) ( $latest[ $module ] ?? 0 );
        $previous = (int) ( $older[ $module ] ?? 0 );
        $change   = $current - $previous;

        return [
            'current'    => $current,
            'previous'   => $previous,
            'change'     => $change,
            'change_pct' => $previous > 0 ? round( ( $change / $previous ) * 100, 1 ) : 0.0,
        ];
    }

    /*----------------------------------------------------------------------
     * Admin page
     *--------------------------------------------------------------------*/

    /**
     * Register the Decay Dashboard admin page.
     */
    public function register_admin_page(): void {
        add_submenu_page(
            'search-intel',
            'Content Decay',
            'Content Decay',
            'manage_options',
            'search-intel-content-decay',
            [ $this, 'render_admin_page' ]
        );
    }

    /**
     * Render the decay dashboard admin page.
     */
    public function render_admin_page(): void {
        $decaying = $this->get_decaying_posts( 50 );
        ?>
        <div class="wrap">
            <h1>Content Decay Dashboard</h1>
            <p>Posts whose SEO, AEO, or GEO scores have dropped more than
               <?php echo esc_html( $this->get_decay_threshold() ); ?>%
               over the past <?php echo esc_html( $this->get_lookback_days() ); ?> days.</p>

            <?php if ( empty( $decaying ) ) : ?>
                <div class="notice notice-success"><p>No decaying content detected. All scores are stable.</p></div>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Post</th>
                            <th>Score Drops</th>
                            <th>Detected</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $decaying as $item ) : ?>
                        <tr>
                            <td>
                                <strong><a href="<?php echo esc_url( get_edit_post_link( $item['post_id'] ) ); ?>">
                                    <?php echo esc_html( $item['title'] ); ?>
                                </a></strong>
                            </td>
                            <td>
                                <?php foreach ( $item['drops'] as $d ) : ?>
                                    <span style="color:#dc2626;font-weight:bold;">
                                        <?php echo esc_html( $d['module'] ); ?>:
                                        <?php echo esc_html( $d['old'] ); ?> → <?php echo esc_html( $d['new'] ); ?>
                                        (−<?php echo esc_html( $d['drop_pct'] ); ?>%)
                                    </span><br>
                                <?php endforeach; ?>
                            </td>
                            <td><?php echo esc_html( $item['detected'] ); ?></td>
                            <td>
                                <a href="<?php echo esc_url( get_edit_post_link( $item['post_id'] ) ); ?>" class="button button-small">Edit</a>
                                <a href="<?php echo esc_url( $item['url'] ); ?>" class="button button-small" target="_blank">View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }
}
