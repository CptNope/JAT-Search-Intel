<?php
/**
 * Google Analytics 4 Integration.
 *
 * Connects to the GA4 Data API to correlate SEO scores with organic traffic
 * per post, identify high-score/low-traffic distribution opportunities, and
 * flag low-score/high-traffic optimization priorities.
 *
 * @package Search_Intel
 * @since   3.3.0
 */

namespace Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GA4_Integration {

    /** @var array Plugin settings. */
    private $settings;

    /** @var string Option key for cached GA4 data. */
    private const CACHE_KEY = 'search_intel_ga4_cache';

    /** @var string Option key for the GA4 access token. */
    private const TOKEN_OPTION = 'search_intel_ga4_token';

    /** @var string Option key for the GA4 refresh token. */
    private const REFRESH_TOKEN_OPTION = 'search_intel_ga4_refresh_token';

    /** @var int Cache TTL in seconds (6 hours). */
    private const CACHE_TTL = 21600;

    /** @var string GA4 Data API base URL. */
    private const API_BASE = 'https://analyticsdata.googleapis.com/v1beta';

    /**
     * Constructor.
     *
     * @param array $settings Plugin settings.
     */
    public function __construct( array $settings ) {
        $this->settings = $settings;

        if ( ! ( $this->settings['enable_ga4'] ?? false ) ) {
            return;
        }

        // Daily fetch cron.
        add_action( 'search_intel_ga4_fetch', [ $this, 'fetch_organic_data' ] );

        if ( ! wp_next_scheduled( 'search_intel_ga4_fetch' ) ) {
            wp_schedule_event( time(), 'daily', 'search_intel_ga4_fetch' );
        }

        if ( is_admin() ) {
            add_action( 'admin_menu', [ $this, 'register_admin_page' ] );
            add_action( 'wp_ajax_si_save_ga4_settings', [ $this, 'ajax_save_settings' ] );
        }
    }

    /**
     * AJAX handler: save GA4 property ID.
     */
    public function ajax_save_settings(): void {
        check_ajax_referer( 'si_ga4_settings', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized.' ] );
        }
        $property_id = sanitize_text_field( wp_unslash( $_POST['ga4_property_id'] ?? '' ) );
        $settings    = get_option( 'search_intel_settings', [] );
        $settings['ga4_property_id'] = $property_id;
        update_option( 'search_intel_settings', $settings );
        $this->settings['ga4_property_id'] = $property_id;
        wp_send_json_success( [ 'message' => $property_id !== '' ? 'Property ID saved.' : 'Property ID cleared.' ] );
    }

    /*----------------------------------------------------------------------
     * Configuration helpers
     *--------------------------------------------------------------------*/

    /**
     * Get the GA4 property ID from settings.
     *
     * @return string Property ID (e.g., "properties/123456789").
     */
    public function get_property_id(): string {
        return (string) ( $this->settings['ga4_property_id'] ?? '' );
    }

    /**
     * Check if GA4 is properly configured with a property ID and access token.
     *
     * @return bool True if configured.
     */
    public function is_configured(): bool {
        return $this->get_property_id() !== '' && $this->get_access_token() !== '';
    }

    /**
     * Get the stored access token.
     *
     * @return string Access token or empty string.
     */
    public function get_access_token(): string {
        return (string) get_option( self::TOKEN_OPTION, '' );
    }

    /**
     * Save OAuth tokens.
     *
     * @param string $access_token  Access token.
     * @param string $refresh_token Refresh token (optional).
     */
    public function save_tokens( string $access_token, string $refresh_token = '' ): void {
        update_option( self::TOKEN_OPTION, $access_token, false );
        if ( $refresh_token !== '' ) {
            update_option( self::REFRESH_TOKEN_OPTION, $refresh_token, false );
        }
    }

    /*----------------------------------------------------------------------
     * GA4 Data API
     *--------------------------------------------------------------------*/

    /**
     * Run a GA4 Data API report query.
     *
     * @param array $body Request body for the runReport endpoint.
     * @return array Parsed response or error array.
     */
    public function run_report( array $body ): array {
        if ( ! $this->is_configured() ) {
            return [ 'success' => false, 'error' => 'GA4 not configured.' ];
        }

        $property = $this->get_property_id();
        $url      = self::API_BASE . "/properties/{$property}:runReport";

        $response = wp_remote_post( $url, [
            'timeout' => 30,
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $this->get_access_token(),
            ],
            'body' => (string) wp_json_encode( $body ),
        ] );

        if ( is_wp_error( $response ) ) {
            return [ 'success' => false, 'error' => $response->get_error_message() ];
        }

        $code = wp_remote_retrieve_response_code( $response );
        $json = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code !== 200 ) {
            return [
                'success' => false,
                'error'   => $json['error']['message'] ?? "HTTP {$code}",
                'code'    => $code,
            ];
        }

        return [ 'success' => true, 'data' => $json ];
    }

    /**
     * Fetch organic traffic data for the last 30 days.
     *
     * Stores per-URL session counts in a transient cache.
     *
     * @return array Per-path organic sessions.
     */
    public function fetch_organic_data(): array {
        $body = [
            'dateRanges' => [
                [ 'startDate' => '30daysAgo', 'endDate' => 'today' ],
            ],
            'dimensions' => [
                [ 'name' => 'pagePath' ],
            ],
            'metrics' => [
                [ 'name' => 'sessions' ],
                [ 'name' => 'engagedSessions' ],
            ],
            'dimensionFilter' => [
                'filter' => [
                    'fieldName'    => 'sessionDefaultChannelGroup',
                    'stringFilter' => [
                        'matchType' => 'EXACT',
                        'value'     => 'Organic Search',
                    ],
                ],
            ],
            'limit' => 1000,
        ];

        $result = $this->run_report( $body );

        if ( ! ( $result['success'] ?? false ) ) {
            return [];
        }

        $rows = $result['data']['rows'] ?? [];
        $data = [];

        foreach ( $rows as $row ) {
            $path     = $row['dimensionValues'][0]['value'] ?? '';
            $sessions = (int) ( $row['metricValues'][0]['value'] ?? 0 );
            $engaged  = (int) ( $row['metricValues'][1]['value'] ?? 0 );

            if ( $path !== '' ) {
                $data[ $path ] = [
                    'sessions' => $sessions,
                    'engaged'  => $engaged,
                ];
            }
        }

        // Cache the data.
        set_transient( self::CACHE_KEY, $data, self::CACHE_TTL );

        return $data;
    }

    /**
     * Get cached organic traffic data.
     *
     * @return array Per-path organic sessions (from cache or fresh fetch).
     */
    public function get_organic_data(): array {
        $cached = get_transient( self::CACHE_KEY );
        if ( is_array( $cached ) ) {
            return $cached;
        }

        return $this->fetch_organic_data();
    }

    /*----------------------------------------------------------------------
     * Score-traffic correlation
     *--------------------------------------------------------------------*/

    /**
     * Get the organic traffic for a specific post.
     *
     * @param int $post_id Post ID.
     * @return array With 'sessions' and 'engaged' keys (0 if not found).
     */
    public function get_post_traffic( int $post_id ): array {
        $permalink = get_permalink( $post_id );
        if ( ! $permalink ) {
            return [ 'sessions' => 0, 'engaged' => 0 ];
        }

        $path = wp_parse_url( $permalink, PHP_URL_PATH );
        if ( ! $path ) {
            return [ 'sessions' => 0, 'engaged' => 0 ];
        }

        $data = $this->get_organic_data();

        // Try exact match, then trailing-slash variants.
        if ( isset( $data[ $path ] ) ) {
            return $data[ $path ];
        }

        $alt = rtrim( $path, '/' );
        if ( isset( $data[ $alt ] ) ) {
            return $data[ $alt ];
        }

        $alt_slash = $alt . '/';
        if ( isset( $data[ $alt_slash ] ) ) {
            return $data[ $alt_slash ];
        }

        return [ 'sessions' => 0, 'engaged' => 0 ];
    }

    /**
     * Build a correlation report: post scores alongside organic traffic.
     *
     * @param int $limit Max posts to include.
     * @return array Sorted by sessions descending.
     */
    public function get_correlation_report( int $limit = 100 ): array {
        $data = $this->get_organic_data();
        if ( empty( $data ) ) {
            return [];
        }

        $post_ids = get_posts( [
            'post_type'   => 'any',
            'post_status' => 'publish',
            'fields'      => 'ids',
            'numberposts' => $limit,
            'orderby'     => 'date',
            'order'       => 'DESC',
        ] );

        $report = [];
        foreach ( $post_ids as $pid ) {
            $pid     = (int) $pid;
            $traffic = $this->get_post_traffic( $pid );
            $scores  = $this->get_post_scores( $pid );

            $report[] = [
                'post_id'   => $pid,
                'title'     => get_the_title( $pid ),
                'url'       => get_permalink( $pid ),
                'sessions'  => $traffic['sessions'],
                'engaged'   => $traffic['engaged'],
                'seo_score' => $scores['seo'],
                'aeo_score' => $scores['aeo'],
                'geo_score' => $scores['geo'],
            ];
        }

        // Sort by sessions descending.
        usort( $report, static function ( $a, $b ) {
            return $b['sessions'] - $a['sessions'];
        } );

        return array_slice( $report, 0, $limit );
    }

    /**
     * Identify high-score / low-traffic posts (distribution opportunities).
     *
     * Posts scoring above the score threshold but receiving below the traffic threshold.
     *
     * @param float $score_min   Minimum SEO score to qualify as "high score" (default 70).
     * @param int   $traffic_max Maximum sessions to qualify as "low traffic" (default 10).
     * @param int   $limit       Max results.
     * @return array Array of opportunity posts.
     */
    public function get_distribution_opportunities( float $score_min = 70.0, int $traffic_max = 10, int $limit = 50 ): array {
        $report = $this->get_correlation_report( 500 );

        $opportunities = array_filter( $report, static function ( $r ) use ( $score_min, $traffic_max ) {
            return $r['seo_score'] >= $score_min && $r['sessions'] <= $traffic_max;
        } );

        // Sort by score descending (best content first).
        usort( $opportunities, static function ( $a, $b ) {
            return (int) ( ( $b['seo_score'] - $a['seo_score'] ) * 10 );
        } );

        return array_slice( $opportunities, 0, $limit );
    }

    /**
     * Identify low-score / high-traffic posts (optimization priorities).
     *
     * Posts receiving significant organic traffic but with low SEO scores.
     *
     * @param float $score_max    Maximum SEO score to qualify as "low score" (default 50).
     * @param int   $traffic_min  Minimum sessions to qualify as "high traffic" (default 50).
     * @param int   $limit        Max results.
     * @return array Array of priority posts.
     */
    public function get_optimization_priorities( float $score_max = 50.0, int $traffic_min = 50, int $limit = 50 ): array {
        $report = $this->get_correlation_report( 500 );

        $priorities = array_filter( $report, static function ( $r ) use ( $score_max, $traffic_min ) {
            return $r['seo_score'] <= $score_max && $r['sessions'] >= $traffic_min;
        } );

        // Sort by traffic descending (most impactful first).
        usort( $priorities, static function ( $a, $b ) {
            return $b['sessions'] - $a['sessions'];
        } );

        return array_slice( $priorities, 0, $limit );
    }

    /*----------------------------------------------------------------------
     * Helpers
     *--------------------------------------------------------------------*/

    /**
     * Get the latest SEO/AEO/GEO scores for a post.
     *
     * @param int $post_id Post ID.
     * @return array With 'seo', 'aeo', 'geo' keys.
     */
    private function get_post_scores( int $post_id ): array {
        $scores = get_post_meta( $post_id, '_search_intel_scores', true );
        if ( is_array( $scores ) ) {
            return [
                'seo' => (float) ( $scores['seo'] ?? 0 ),
                'aeo' => (float) ( $scores['aeo'] ?? 0 ),
                'geo' => (float) ( $scores['geo'] ?? 0 ),
            ];
        }
        return [ 'seo' => 0.0, 'aeo' => 0.0, 'geo' => 0.0 ];
    }

    /*----------------------------------------------------------------------
     * Admin page
     *--------------------------------------------------------------------*/

    /**
     * Register the admin menu page.
     */
    public function register_admin_page(): void {
        add_submenu_page(
            'search-intel',
            'GA4 Insights',
            'GA4 Insights',
            'manage_options',
            'search-intel-ga4',
            [ $this, 'render_admin_page' ]
        );
    }

    /**
     * Render the GA4 insights admin page.
     */
    public function render_admin_page(): void {
        $property_id = $this->get_property_id();
        $nonce       = wp_create_nonce( 'si_ga4_settings' );
        ?>
        <div class="wrap">
            <h1>GA4 Insights</h1>

            <div class="si-settings-card" style="max-width:700px;margin-bottom:20px;padding:15px 20px;background:#fff;border:1px solid #c3c4c7;box-shadow:0 1px 1px rgba(0,0,0,.04);">
                <h2 style="margin-top:0;">Configuration</h2>
                <table class="form-table" style="margin-top:0;">
                    <tr>
                        <th><label for="si_ga4_property_id">GA4 Property ID</label></th>
                        <td>
                            <div style="display:flex;gap:8px;align-items:center;">
                                <input type="text" id="si_ga4_property_id" class="regular-text" value="<?php echo esc_attr( $property_id ); ?>" placeholder="properties/123456789" />
                                <button type="button" id="si-save-ga4" class="button button-primary">Save</button>
                                <span id="si-ga4-status"><?php if ( $property_id !== '' ) : ?><span style="color:#00a32a;font-weight:600;">&#10003; Configured</span><?php endif; ?></span>
                            </div>
                            <p class="description">Find your Property ID in <a href="https://analytics.google.com/" target="_blank">Google Analytics</a> → Admin → Property Settings. Format: <code>properties/123456789</code></p>
                        </td>
                    </tr>
                </table>
                <p class="description" style="margin-top:8px;">GA4 data is fetched automatically via daily cron. Ensure your site has a valid GA4 Data API service account or OAuth token configured.</p>
            </div>

            <?php if ( ! $this->is_configured() ) : ?>
                <div class="notice notice-warning"><p>Enter your GA4 Property ID above to start correlating organic traffic with SEO scores.</p></div>
            <?php else : ?>

            <h2>Distribution Opportunities</h2>
            <p>High-score posts with low organic traffic — promote these!</p>
            <?php $this->render_post_table( $this->get_distribution_opportunities() ); ?>

            <h2>Optimization Priorities</h2>
            <p>High-traffic posts with low scores — optimize these for quick wins!</p>
            <?php $this->render_post_table( $this->get_optimization_priorities() ); ?>

            <?php endif; ?>
        </div>
        <script>
        jQuery(function($){
            var ajaxUrl = '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>';
            var nonce   = '<?php echo esc_js( $nonce ); ?>';

            $('#si-save-ga4').on('click', function(){
                var btn = $(this), val = $('#si_ga4_property_id').val();
                btn.prop('disabled', true);
                $('#si-ga4-status').html('<span style="color:#dba617;">Saving...</span>');
                $.post(ajaxUrl, { action: 'si_save_ga4_settings', nonce: nonce, ga4_property_id: val }, function(r){
                    btn.prop('disabled', false);
                    if (r.success) {
                        $('#si-ga4-status').html('<span style="color:#00a32a;">&#10003; ' + r.data.message + '</span>');
                    } else {
                        $('#si-ga4-status').html('<span style="color:#d63638;">' + r.data.message + '</span>');
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Render a table of posts with scores and traffic.
     *
     * @param array $posts Post data array.
     */
    private function render_post_table( array $posts ): void {
        if ( empty( $posts ) ) {
            echo '<p><em>No posts match these criteria.</em></p>';
            return;
        }
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Post</th>
                    <th>SEO</th>
                    <th>AEO</th>
                    <th>GEO</th>
                    <th>Sessions (30d)</th>
                    <th>Engaged</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $posts as $p ) : ?>
                    <tr>
                        <td><a href="<?php echo esc_url( get_edit_post_link( $p['post_id'] ) ?? '' ); ?>"><?php echo esc_html( $p['title'] ); ?></a></td>
                        <td><?php echo esc_html( (string) $p['seo_score'] ); ?></td>
                        <td><?php echo esc_html( (string) $p['aeo_score'] ); ?></td>
                        <td><?php echo esc_html( (string) $p['geo_score'] ); ?></td>
                        <td><?php echo esc_html( (string) $p['sessions'] ); ?></td>
                        <td><?php echo esc_html( (string) $p['engaged'] ); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
}
