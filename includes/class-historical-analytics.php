<?php
/**
 * Historical Analytics.
 *
 * Provides per-post score history charts (SEO/AEO/GEO over time), a site-wide
 * score trend dashboard (30/60/90-day views), before/after comparison for
 * content updates, and a score change log tracking what changed, when, and
 * by whom.
 *
 * @package JAT_Search_Intel
 * @since   3.3.0
 */

namespace JAT_Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Historical_Analytics {

    /** @var array Plugin settings. */
    private $settings;

    /** @var string Post meta key for score history. */
    private const HISTORY_META_KEY = '_jat_search_intel_analytics_history';

    /** @var string Option key for site-wide daily aggregates. */
    private const SITE_AGGREGATE_KEY = 'jat_search_intel_site_score_aggregates';

    /** @var string Post meta key for score change log. */
    private const CHANGE_LOG_META_KEY = '_jat_search_intel_score_change_log';

    /** @var int Maximum history entries per post. */
    private const MAX_HISTORY_ENTRIES = 365;

    /** @var int Maximum change log entries per post. */
    private const MAX_CHANGE_LOG_ENTRIES = 100;

    /**
     * Constructor.
     *
     * @param array $settings Plugin settings.
     */
    public function __construct( array $settings ) {
        $this->settings = $settings;

        if ( ! ( $this->settings['enable_historical_analytics'] ?? false ) ) {
            return;
        }

        // Record score and change log on every analysis.
        add_action( 'jat_search_intel_post_analyzed', [ $this, 'on_post_analyzed' ], 10, 4 );

        // Daily site-wide aggregate cron.
        add_action( 'jat_search_intel_daily_aggregate', [ $this, 'compute_site_aggregate' ] );

        if ( ! wp_next_scheduled( 'jat_search_intel_daily_aggregate' ) ) {
            wp_schedule_event( time(), 'daily', 'jat_search_intel_daily_aggregate' );
        }

        // Admin page.
        if ( is_admin() ) {
            add_action( 'admin_menu', [ $this, 'register_admin_page' ] );
        }
    }

    /*----------------------------------------------------------------------
     * Score recording
     *--------------------------------------------------------------------*/

    /**
     * Handle the jat_search_intel_post_analyzed action.
     *
     * Records score history and logs changes.
     *
     * @param int   $post_id    Post ID.
     * @param array $new_scores New scores (seo, aeo, geo, readability).
     * @param array $old_scores Previous scores (seo, aeo, geo, readability).
     * @param array $results    Full analysis results.
     */
    public function on_post_analyzed( int $post_id, array $new_scores, array $old_scores = [], array $results = [] ): void {
        $this->record_score( $post_id, $new_scores );

        // Log change if scores actually changed.
        if ( $old_scores !== $new_scores ) {
            $action = 'Content analyzed';
            if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
                $action = 'Bulk rescore (cron)';
            } elseif ( wp_doing_ajax() ) {
                $action = 'Bulk rescore';
            }
            $this->log_change( $post_id, $action, $old_scores, $new_scores, get_current_user_id() );
        }
    }

    /**
     * Record a score snapshot for a post.
     *
     * @param int   $post_id Post ID.
     * @param array $scores  Associative array with 'seo', 'aeo', 'geo', 'readability' keys.
     */
    public function record_score( int $post_id, array $scores ): void {
        $history = $this->get_post_history( $post_id );

        $entry = [
            'date'        => gmdate( 'Y-m-d' ),
            'ts'          => time(),
            'seo'         => (float) ( $scores['seo'] ?? 0 ),
            'aeo'         => (float) ( $scores['aeo'] ?? 0 ),
            'geo'         => (float) ( $scores['geo'] ?? 0 ),
            'readability' => (float) ( $scores['readability'] ?? 0 ),
        ];

        // Avoid duplicate entries for the same day — overwrite.
        $today = $entry['date'];
        $history = array_filter( $history, static function ( $h ) use ( $today ) {
            return ( $h['date'] ?? '' ) !== $today;
        } );

        $history[] = $entry;

        // Trim to max entries.
        if ( count( $history ) > self::MAX_HISTORY_ENTRIES ) {
            $history = array_slice( $history, -self::MAX_HISTORY_ENTRIES );
        }

        update_post_meta( $post_id, self::HISTORY_META_KEY, $history );
    }

    /**
     * Get the full score history for a post.
     *
     * @param int $post_id Post ID.
     * @return array Array of score entries.
     */
    public function get_post_history( int $post_id ): array {
        $history = get_post_meta( $post_id, self::HISTORY_META_KEY, true );
        return is_array( $history ) ? $history : [];
    }

    /*----------------------------------------------------------------------
     * Per-post chart data
     *--------------------------------------------------------------------*/

    /**
     * Get chart data for a specific post and date range.
     *
     * @param int    $post_id Post ID.
     * @param string $module  Module: 'seo', 'aeo', 'geo', or 'all'.
     * @param int    $days    Number of days to look back.
     * @return array Chart-ready data with 'labels' and 'datasets' keys.
     */
    public function get_chart_data( int $post_id, string $module = 'all', int $days = 30 ): array {
        $history = $this->get_post_history( $post_id );
        $cutoff  = gmdate( 'Y-m-d', time() - ( $days * DAY_IN_SECONDS ) );

        $filtered = array_filter( $history, static function ( $h ) use ( $cutoff ) {
            return ( $h['date'] ?? '' ) >= $cutoff;
        } );
        $filtered = array_values( $filtered );

        // Sort by date ascending.
        usort( $filtered, static function ( $a, $b ) {
            return strcmp( $a['date'], $b['date'] );
        } );

        $labels   = array_column( $filtered, 'date' );
        $datasets = [];

        $modules = $module === 'all' ? [ 'seo', 'aeo', 'geo' ] : [ $module ];

        foreach ( $modules as $mod ) {
            $datasets[ $mod ] = array_map( static function ( $h ) use ( $mod ) {
                return (float) ( $h[ $mod ] ?? 0 );
            }, $filtered );
        }

        return [
            'labels'   => $labels,
            'datasets' => $datasets,
            'post_id'  => $post_id,
            'days'     => $days,
        ];
    }

    /*----------------------------------------------------------------------
     * Site-wide trend dashboard
     *--------------------------------------------------------------------*/

    /**
     * Compute the daily site-wide aggregate score.
     *
     * Averages all published post scores and stores as a daily data point.
     */
    public function compute_site_aggregate(): void {
        $post_ids = get_posts( [
            'post_type'   => 'any',
            'post_status' => 'publish',
            'fields'      => 'ids',
            'numberposts' => -1,
            'meta_key'    => self::HISTORY_META_KEY,
        ] );

        if ( empty( $post_ids ) ) {
            return;
        }

        $totals = [ 'seo' => 0.0, 'aeo' => 0.0, 'geo' => 0.0, 'readability' => 0.0 ];
        $count  = 0;

        foreach ( $post_ids as $pid ) {
            $history = $this->get_post_history( (int) $pid );
            if ( empty( $history ) ) {
                continue;
            }
            $latest = end( $history );
            $totals['seo']         += (float) ( $latest['seo'] ?? 0 );
            $totals['aeo']         += (float) ( $latest['aeo'] ?? 0 );
            $totals['geo']         += (float) ( $latest['geo'] ?? 0 );
            $totals['readability'] += (float) ( $latest['readability'] ?? 0 );
            $count++;
        }

        if ( $count === 0 ) {
            return;
        }

        $aggregate = [
            'date'        => gmdate( 'Y-m-d' ),
            'ts'          => time(),
            'seo'         => round( $totals['seo'] / $count, 1 ),
            'aeo'         => round( $totals['aeo'] / $count, 1 ),
            'geo'         => round( $totals['geo'] / $count, 1 ),
            'readability' => round( $totals['readability'] / $count, 1 ),
            'posts'       => $count,
        ];

        $aggregates = get_option( self::SITE_AGGREGATE_KEY, [] );
        if ( ! is_array( $aggregates ) ) {
            $aggregates = [];
        }

        // Remove existing entry for today.
        $today = $aggregate['date'];
        $aggregates = array_filter( $aggregates, static function ( $a ) use ( $today ) {
            return ( $a['date'] ?? '' ) !== $today;
        } );

        $aggregates[] = $aggregate;

        // Retain last 365 days.
        if ( count( $aggregates ) > self::MAX_HISTORY_ENTRIES ) {
            $aggregates = array_slice( $aggregates, -self::MAX_HISTORY_ENTRIES );
        }

        update_option( self::SITE_AGGREGATE_KEY, $aggregates, false );
    }

    /**
     * Get site-wide trend data for a given period.
     *
     * @param int $days Number of days (30, 60, or 90).
     * @return array Chart-ready data with 'labels' and 'datasets'.
     */
    public function get_site_trends( int $days = 30 ): array {
        $aggregates = get_option( self::SITE_AGGREGATE_KEY, [] );
        if ( ! is_array( $aggregates ) ) {
            $aggregates = [];
        }

        $cutoff = gmdate( 'Y-m-d', time() - ( $days * DAY_IN_SECONDS ) );

        $filtered = array_filter( $aggregates, static function ( $a ) use ( $cutoff ) {
            return ( $a['date'] ?? '' ) >= $cutoff;
        } );
        $filtered = array_values( $filtered );

        usort( $filtered, static function ( $a, $b ) {
            return strcmp( $a['date'], $b['date'] );
        } );

        return [
            'labels'   => array_column( $filtered, 'date' ),
            'datasets' => [
                'seo'         => array_column( $filtered, 'seo' ),
                'aeo'         => array_column( $filtered, 'aeo' ),
                'geo'         => array_column( $filtered, 'geo' ),
                'readability' => array_column( $filtered, 'readability' ),
            ],
            'days' => $days,
        ];
    }

    /*----------------------------------------------------------------------
     * Before / after comparison
     *--------------------------------------------------------------------*/

    /**
     * Get a before/after comparison around a specific date.
     *
     * Returns the closest score entries before and after the target date.
     *
     * @param int    $post_id Post ID.
     * @param string $date    Target date (Y-m-d).
     * @return array With 'before' and 'after' score arrays (or null if not available).
     */
    public function get_before_after( int $post_id, string $date ): array {
        $history = $this->get_post_history( $post_id );

        usort( $history, static function ( $a, $b ) {
            return strcmp( $a['date'], $b['date'] );
        } );

        $before = null;
        $after  = null;

        foreach ( $history as $entry ) {
            $entry_date = $entry['date'] ?? '';
            if ( $entry_date < $date ) {
                $before = $entry;
            } elseif ( $entry_date >= $date && null === $after ) {
                $after = $entry;
            }
        }

        $delta = [];
        if ( $before && $after ) {
            foreach ( [ 'seo', 'aeo', 'geo', 'readability' ] as $mod ) {
                $b = (float) ( $before[ $mod ] ?? 0 );
                $a = (float) ( $after[ $mod ] ?? 0 );
                $delta[ $mod ] = [
                    'change'  => round( $a - $b, 1 ),
                    'percent' => $b > 0 ? round( ( ( $a - $b ) / $b ) * 100, 1 ) : 0.0,
                ];
            }
        }

        return [
            'before' => $before,
            'after'  => $after,
            'delta'  => $delta,
        ];
    }

    /*----------------------------------------------------------------------
     * Score change log
     *--------------------------------------------------------------------*/

    /**
     * Log a score change event for a post.
     *
     * @param int    $post_id Post ID.
     * @param string $action  Description of what changed.
     * @param array  $old     Old scores (seo, aeo, geo, readability).
     * @param array  $new     New scores (seo, aeo, geo, readability).
     * @param int    $user_id User who triggered the change (0 for system/cron).
     */
    public function log_change( int $post_id, string $action, array $old, array $new, int $user_id = 0 ): void {
        $log = get_post_meta( $post_id, self::CHANGE_LOG_META_KEY, true );
        if ( ! is_array( $log ) ) {
            $log = [];
        }

        $entry = [
            'ts'      => time(),
            'date'    => gmdate( 'Y-m-d H:i:s' ),
            'action'  => $action,
            'user_id' => $user_id,
            'old'     => [
                'seo'         => (float) ( $old['seo'] ?? 0 ),
                'aeo'         => (float) ( $old['aeo'] ?? 0 ),
                'geo'         => (float) ( $old['geo'] ?? 0 ),
                'readability' => (float) ( $old['readability'] ?? 0 ),
            ],
            'new'     => [
                'seo'         => (float) ( $new['seo'] ?? 0 ),
                'aeo'         => (float) ( $new['aeo'] ?? 0 ),
                'geo'         => (float) ( $new['geo'] ?? 0 ),
                'readability' => (float) ( $new['readability'] ?? 0 ),
            ],
        ];

        $log[] = $entry;

        // Trim.
        if ( count( $log ) > self::MAX_CHANGE_LOG_ENTRIES ) {
            $log = array_slice( $log, -self::MAX_CHANGE_LOG_ENTRIES );
        }

        update_post_meta( $post_id, self::CHANGE_LOG_META_KEY, $log );
    }

    /**
     * Get the change log for a post.
     *
     * @param int $post_id Post ID.
     * @param int $limit   Max entries to return.
     * @return array Change log entries (newest first).
     */
    public function get_change_log( int $post_id, int $limit = 50 ): array {
        $log = get_post_meta( $post_id, self::CHANGE_LOG_META_KEY, true );
        if ( ! is_array( $log ) ) {
            return [];
        }

        // Newest first.
        $log = array_reverse( $log );

        return array_slice( $log, 0, $limit );
    }

    /**
     * Get a summary of recent changes across all posts.
     *
     * @param int $days  Lookback period.
     * @param int $limit Max entries.
     * @return array Recent changes with post context.
     */
    public function get_recent_changes( int $days = 7, int $limit = 50 ): array {
        $cutoff_ts = time() - ( $days * DAY_IN_SECONDS );

        $post_ids = get_posts( [
            'post_type'   => 'any',
            'post_status' => 'publish',
            'fields'      => 'ids',
            'numberposts' => -1,
            'meta_key'    => self::CHANGE_LOG_META_KEY,
        ] );

        $all = [];
        foreach ( $post_ids as $pid ) {
            $log = get_post_meta( (int) $pid, self::CHANGE_LOG_META_KEY, true );
            if ( ! is_array( $log ) ) {
                continue;
            }
            foreach ( $log as $entry ) {
                if ( ( $entry['ts'] ?? 0 ) >= $cutoff_ts ) {
                    $entry['post_id']    = (int) $pid;
                    $entry['post_title'] = get_the_title( (int) $pid );
                    $all[] = $entry;
                }
            }
        }

        // Sort by timestamp descending.
        usort( $all, static function ( $a, $b ) {
            return ( $b['ts'] ?? 0 ) - ( $a['ts'] ?? 0 );
        } );

        return array_slice( $all, 0, $limit );
    }

    /*----------------------------------------------------------------------
     * Admin page
     *--------------------------------------------------------------------*/

    /**
     * Register the admin menu page.
     */
    public function register_admin_page(): void {
        add_submenu_page(
            'jat-search-intel',
            'Score Analytics',
            'Analytics',
            'manage_options',
            'jat-search-intel-analytics',
            [ $this, 'render_admin_page' ]
        );
    }

    /**
     * Render the analytics dashboard admin page.
     */
    public function render_admin_page(): void {
        $period  = isset( $_GET['period'] ) ? absint( $_GET['period'] ) : 30; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view/pagination parameter.
        $trends  = $this->get_site_trends( $period );
        $changes = $this->get_recent_changes( 7, 20 );
        ?>
        <div class="wrap">
            <h1>Score Analytics</h1>

            <h2>Site-Wide Score Trends (<?php echo esc_html( (string) $period ); ?> days)</h2>

            <p>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=jat-search-intel-analytics&period=30' ) ); ?>" class="button <?php echo $period === 30 ? 'button-primary' : ''; ?>">30 Days</a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=jat-search-intel-analytics&period=60' ) ); ?>" class="button <?php echo $period === 60 ? 'button-primary' : ''; ?>">60 Days</a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=jat-search-intel-analytics&period=90' ) ); ?>" class="button <?php echo $period === 90 ? 'button-primary' : ''; ?>">90 Days</a>
            </p>

            <?php if ( empty( $trends['labels'] ) ) : ?>
                <div class="notice notice-info"><p>No score data available yet. Analytics will populate as posts are analyzed.</p></div>
            <?php else : ?>
                <div id="si-analytics-chart" data-chart="<?php echo esc_attr( (string) wp_json_encode( $trends ) ); ?>"></div>
            <?php endif; ?>

            <h2>Recent Score Changes</h2>

            <?php if ( empty( $changes ) ) : ?>
                <p>No score changes recorded in the last 7 days.</p>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Post</th>
                            <th>Action</th>
                            <th>SEO</th>
                            <th>AEO</th>
                            <th>GEO</th>
                            <th>Readability</th>
                            <th>When</th>
                            <th>By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $changes as $c ) : ?>
                            <tr>
                                <td>
                                    <a href="<?php echo esc_url( get_edit_post_link( $c['post_id'] ) ?? '' ); ?>">
                                        <?php echo esc_html( $c['post_title'] ?? '' ); ?>
                                    </a>
                                </td>
                                <td><?php echo esc_html( $c['action'] ?? '' ); ?></td>
                                <td><?php echo esc_html( (string) ( $c['old']['seo'] ?? 0 ) ); ?> → <?php echo esc_html( (string) ( $c['new']['seo'] ?? 0 ) ); ?></td>
                                <td><?php echo esc_html( (string) ( $c['old']['aeo'] ?? 0 ) ); ?> → <?php echo esc_html( (string) ( $c['new']['aeo'] ?? 0 ) ); ?></td>
                                <td><?php echo esc_html( (string) ( $c['old']['geo'] ?? 0 ) ); ?> → <?php echo esc_html( (string) ( $c['new']['geo'] ?? 0 ) ); ?></td>
                                <td><?php echo esc_html( (string) ( $c['old']['readability'] ?? 0 ) ); ?> → <?php echo esc_html( (string) ( $c['new']['readability'] ?? 0 ) ); ?></td>
                                <td><?php echo esc_html( $c['date'] ?? '' ); ?></td>
                                <td>
                                    <?php
                                    $uid  = (int) ( $c['user_id'] ?? 0 );
                                    $user = $uid > 0 ? get_userdata( $uid ) : false;
                                    echo $user ? esc_html( $user->display_name ?? "User #{$uid}" ) : '<em>System</em>';
                                    ?>
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
