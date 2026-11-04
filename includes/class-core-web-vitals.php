<?php
/**
 * Core Web Vitals Monitoring.
 *
 * Integrates with the PageSpeed Insights API to track per-page CWV scores
 * (LCP, INP, CLS), maintain trend data over time, and correlate CWV
 * performance with SEO scores.
 *
 * @package JAT_Search_Intel
 * @since   3.3.0
 */

namespace JAT_Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Core_Web_Vitals {

    /** @var array Plugin settings. */
    private $settings;

    /** @var string Post meta key for CWV data. */
    private const CWV_META_KEY = '_jat_search_intel_cwv';

    /** @var string Option key for site-wide CWV history. */
    private const SITE_CWV_KEY = 'jat_search_intel_site_cwv_history';

    /** @var string PageSpeed Insights API endpoint. */
    private const PSI_API_URL = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';

    /** @var int Max CWV history entries per post. */
    private const MAX_ENTRIES = 90;

    /**
     * Constructor.
     *
     * @param array $settings Plugin settings.
     */
    public function __construct( array $settings ) {
        $this->settings = $settings;

        if ( ! ( $this->settings['enable_cwv'] ?? false ) ) {
            return;
        }

        // Weekly CWV scan cron.
        add_action( 'jat_search_intel_cwv_scan', [ $this, 'run_scan' ] );

        if ( ! wp_next_scheduled( 'jat_search_intel_cwv_scan' ) ) {
            wp_schedule_event( time(), 'weekly', 'jat_search_intel_cwv_scan' );
        }

        if ( is_admin() ) {
            add_action( 'admin_menu', [ $this, 'register_admin_page' ] );
            add_action( 'wp_ajax_jat_search_intel_save_psi_key', [ $this, 'ajax_save_psi_key' ] );
            add_action( 'wp_ajax_jat_search_intel_run_cwv_scan', [ $this, 'ajax_run_scan' ] );
        }
    }

    /**
     * AJAX handler: save PSI API key.
     */
    public function ajax_save_psi_key(): void {
        check_ajax_referer( 'si_cwv_settings', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized.' ] );
        }
        $key      = sanitize_text_field( wp_unslash( $_POST['psi_api_key'] ?? '' ) );
        $settings = get_option( 'jat_search_intel_settings', [] );
        $settings['psi_api_key'] = $key;
        update_option( 'jat_search_intel_settings', $settings );
        $this->settings['psi_api_key'] = $key;
        wp_send_json_success( [ 'message' => $key !== '' ? 'API key saved.' : 'API key cleared.' ] );
    }

    /**
     * AJAX handler: run a manual CWV scan.
     */
    public function ajax_run_scan(): void {
        check_ajax_referer( 'si_cwv_settings', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized.' ] );
        }
        if ( ! $this->is_configured() ) {
            wp_send_json_error( [ 'message' => 'PSI API key not configured.' ] );
        }
        $stats = $this->run_scan();
        wp_send_json_success( [ 'message' => 'Scanned ' . (int) $stats['scanned'] . ' posts (' . (int) $stats['success'] . ' OK, ' . (int) $stats['failed'] . ' failed). Reload to see results.' ] );
    }

    /*----------------------------------------------------------------------
     * PageSpeed Insights API
     *--------------------------------------------------------------------*/

    /**
     * Get the PSI API key from settings.
     *
     * @return string API key or empty string.
     */
    public function get_api_key(): string {
        return (string) ( $this->settings['psi_api_key'] ?? '' );
    }

    /**
     * Check if CWV monitoring is properly configured.
     *
     * @return bool True if API key is present.
     */
    public function is_configured(): bool {
        return $this->get_api_key() !== '';
    }

    /**
     * Fetch CWV data for a URL via the PageSpeed Insights API.
     *
     * @param string $url      URL to analyze.
     * @param string $strategy 'mobile' or 'desktop'.
     * @return array Parsed CWV metrics or error.
     */
    public function fetch_cwv( string $url, string $strategy = 'mobile' ): array {
        if ( ! $this->is_configured() ) {
            return [ 'success' => false, 'error' => 'PSI API key not configured.' ];
        }

        $api_url = add_query_arg( [
            'url'      => $url,
            'key'      => $this->get_api_key(),
            'strategy' => $strategy,
            'category' => 'performance',
        ], self::PSI_API_URL );

        $response = wp_remote_get( $api_url, [ 'timeout' => 60 ] );

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

        return $this->parse_psi_response( $json, $strategy );
    }

    /**
     * Parse the PSI API response into a clean CWV data structure.
     *
     * @param array  $json     Full PSI response.
     * @param string $strategy Strategy used.
     * @return array Parsed CWV metrics.
     */
    private function parse_psi_response( array $json, string $strategy ): array {
        $metrics = $json['lighthouseResult']['audits'] ?? [];

        $lcp = $metrics['largest-contentful-paint']['numericValue'] ?? null;
        $cls = $metrics['cumulative-layout-shift']['numericValue'] ?? null;
        $inp = $metrics['interaction-to-next-paint']['numericValue']
            ?? $metrics['max-potential-fid']['numericValue']
            ?? null;

        $perf_score = $json['lighthouseResult']['categories']['performance']['score'] ?? null;

        return [
            'success'    => true,
            'strategy'   => $strategy,
            'lcp'        => $lcp !== null ? round( (float) $lcp, 1 ) : null,
            'cls'        => $cls !== null ? round( (float) $cls, 4 ) : null,
            'inp'        => $inp !== null ? round( (float) $inp, 1 ) : null,
            'perf_score' => $perf_score !== null ? round( (float) $perf_score * 100, 0 ) : null,
            'fetched_at' => gmdate( 'Y-m-d H:i:s' ),
        ];
    }

    /*----------------------------------------------------------------------
     * Per-post CWV tracking
     *--------------------------------------------------------------------*/

    /**
     * Fetch and store CWV data for a specific post.
     *
     * @param int    $post_id  Post ID.
     * @param string $strategy 'mobile' or 'desktop'.
     * @return array CWV result.
     */
    public function check_post( int $post_id, string $strategy = 'mobile' ): array {
        $url = get_permalink( $post_id );
        if ( ! $url ) {
            return [ 'success' => false, 'error' => 'Invalid post.' ];
        }

        $result = $this->fetch_cwv( $url, $strategy );

        if ( $result['success'] ?? false ) {
            $this->store_cwv( $post_id, $result );
        }

        return $result;
    }

    /**
     * Store a CWV data point for a post.
     *
     * @param int   $post_id Post ID.
     * @param array $cwv     CWV metrics.
     */
    public function store_cwv( int $post_id, array $cwv ): void {
        $history = $this->get_cwv_history( $post_id );

        $entry = [
            'date'       => gmdate( 'Y-m-d' ),
            'strategy'   => $cwv['strategy'] ?? 'mobile',
            'lcp'        => $cwv['lcp'] ?? null,
            'cls'        => $cwv['cls'] ?? null,
            'inp'        => $cwv['inp'] ?? null,
            'perf_score' => $cwv['perf_score'] ?? null,
        ];

        $history[] = $entry;

        // Trim to max entries.
        if ( count( $history ) > self::MAX_ENTRIES ) {
            $history = array_slice( $history, -self::MAX_ENTRIES );
        }

        update_post_meta( $post_id, self::CWV_META_KEY, $history );
    }

    /**
     * Get the CWV history for a post.
     *
     * @param int $post_id Post ID.
     * @return array Array of CWV entries.
     */
    public function get_cwv_history( int $post_id ): array {
        $history = get_post_meta( $post_id, self::CWV_META_KEY, true );
        return is_array( $history ) ? $history : [];
    }

    /**
     * Get the latest CWV metrics for a post.
     *
     * @param int $post_id Post ID.
     * @return array Latest CWV entry or empty array.
     */
    public function get_latest_cwv( int $post_id ): array {
        $history = $this->get_cwv_history( $post_id );
        return ! empty( $history ) ? end( $history ) : [];
    }

    /**
     * Classify a CWV metric value as 'good', 'needs-improvement', or 'poor'.
     *
     * @param string     $metric Metric name: 'lcp', 'cls', or 'inp'.
     * @param float|null $value  Metric value.
     * @return string Classification or 'unknown'.
     */
    public function classify_metric( string $metric, ?float $value ): string {
        if ( $value === null ) {
            return 'unknown';
        }

        $thresholds = [
            'lcp' => [ 'good' => 2500, 'poor' => 4000 ],    // milliseconds
            'cls' => [ 'good' => 0.1,  'poor' => 0.25 ],    // unitless
            'inp' => [ 'good' => 200,  'poor' => 500 ],      // milliseconds
        ];

        if ( ! isset( $thresholds[ $metric ] ) ) {
            return 'unknown';
        }

        $t = $thresholds[ $metric ];

        if ( $value <= $t['good'] ) {
            return 'good';
        }
        if ( $value <= $t['poor'] ) {
            return 'needs-improvement';
        }
        return 'poor';
    }

    /*----------------------------------------------------------------------
     * CWV trend tracking
     *--------------------------------------------------------------------*/

    /**
     * Get CWV trend data for a post.
     *
     * @param int $post_id Post ID.
     * @param int $days    Lookback period.
     * @return array Chart-ready data.
     */
    public function get_cwv_trends( int $post_id, int $days = 30 ): array {
        $history = $this->get_cwv_history( $post_id );
        $cutoff  = gmdate( 'Y-m-d', time() - ( $days * DAY_IN_SECONDS ) );

        $filtered = array_filter( $history, static function ( $h ) use ( $cutoff ) {
            return ( $h['date'] ?? '' ) >= $cutoff;
        } );
        $filtered = array_values( $filtered );

        usort( $filtered, static function ( $a, $b ) {
            return strcmp( $a['date'], $b['date'] );
        } );

        return [
            'labels' => array_column( $filtered, 'date' ),
            'lcp'    => array_column( $filtered, 'lcp' ),
            'cls'    => array_column( $filtered, 'cls' ),
            'inp'    => array_column( $filtered, 'inp' ),
            'perf'   => array_column( $filtered, 'perf_score' ),
        ];
    }

    /*----------------------------------------------------------------------
     * Correlation with SEO scores
     *--------------------------------------------------------------------*/

    /**
     * Build a CWV-vs-SEO correlation report.
     *
     * @param int $limit Max posts.
     * @return array Posts with CWV and SEO data side by side.
     */
    public function get_correlation_report( int $limit = 50 ): array {
        $post_ids = get_posts( [
            'post_type'   => 'any',
            'post_status' => 'publish',
            'fields'      => 'ids',
            'numberposts' => $limit,
            'meta_key'    => self::CWV_META_KEY,
        ] );

        $report = [];
        foreach ( $post_ids as $pid ) {
            $pid    = (int) $pid;
            $cwv    = $this->get_latest_cwv( $pid );
            $scores = get_post_meta( $pid, '_jat_search_intel_scores', true );

            if ( empty( $cwv ) ) {
                continue;
            }

            $report[] = [
                'post_id'    => $pid,
                'title'      => get_the_title( $pid ),
                'lcp'        => $cwv['lcp'] ?? null,
                'cls'        => $cwv['cls'] ?? null,
                'inp'        => $cwv['inp'] ?? null,
                'perf_score' => $cwv['perf_score'] ?? null,
                'lcp_class'  => $this->classify_metric( 'lcp', $cwv['lcp'] ?? null ),
                'cls_class'  => $this->classify_metric( 'cls', $cwv['cls'] ?? null ),
                'inp_class'  => $this->classify_metric( 'inp', $cwv['inp'] ?? null ),
                'seo_score'  => is_array( $scores ) ? (float) ( $scores['seo'] ?? 0 ) : 0.0,
                'aeo_score'  => is_array( $scores ) ? (float) ( $scores['aeo'] ?? 0 ) : 0.0,
                'geo_score'  => is_array( $scores ) ? (float) ( $scores['geo'] ?? 0 ) : 0.0,
            ];
        }

        return $report;
    }

    /*----------------------------------------------------------------------
     * Batch scan
     *--------------------------------------------------------------------*/

    /**
     * Run a CWV scan across published posts.
     *
     * @param int $limit Max posts to scan per run.
     * @return array Scan stats.
     */
    public function run_scan( int $limit = 20 ): array {
        $stats = [ 'scanned' => 0, 'success' => 0, 'failed' => 0 ];

        if ( ! $this->is_configured() ) {
            return $stats;
        }

        $post_ids = get_posts( [
            'post_type'   => 'any',
            'post_status' => 'publish',
            'fields'      => 'ids',
            'numberposts' => $limit,
            'orderby'     => 'rand',
        ] );

        foreach ( $post_ids as $pid ) {
            $stats['scanned']++;
            $result = $this->check_post( (int) $pid );

            if ( $result['success'] ?? false ) {
                $stats['success']++;
            } else {
                $stats['failed']++;
            }
        }

        return $stats;
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
            'Core Web Vitals',
            'Web Vitals',
            'manage_options',
            'jat-search-intel-cwv',
            [ $this, 'render_admin_page' ]
        );
    }

    /**
     * Render the CWV dashboard admin page.
     */
    public function render_admin_page(): void {
        $report  = $this->get_correlation_report( 50 );
        $api_key = $this->get_api_key();
        $nonce   = wp_create_nonce( 'si_cwv_settings' );
        ?>
        <div class="wrap">
            <h1>Core Web Vitals</h1>

            <div class="si-settings-card" style="max-width:700px;margin-bottom:20px;padding:15px 20px;background:#fff;border:1px solid #c3c4c7;box-shadow:0 1px 1px rgba(0,0,0,.04);">
                <h2 style="margin-top:0;">Configuration</h2>
                <table class="form-table" style="margin-top:0;">
                    <tr>
                        <th><label for="si_psi_api_key">PageSpeed Insights API Key</label></th>
                        <td>
                            <div style="display:flex;gap:8px;align-items:center;">
                                <input type="password" id="si_psi_api_key" class="regular-text" value="<?php echo $api_key !== '' ? '••••••••••••••••' : ''; ?>" placeholder="Enter your Google PSI API key" />
                                <button type="button" id="si-save-psi-key" class="button button-primary">Save Key</button>
                                <span id="si-psi-status"><?php if ( $api_key !== '' ) : ?><span style="color:#00a32a;font-weight:600;">&#10003; Configured</span><?php endif; ?></span>
                            </div>
                            <p class="description"><a href="https://developers.google.com/speed/docs/insights/v5/get-started" target="_blank">Get a free PSI API key from Google →</a></p>
                        </td>
                    </tr>
                    <tr>
                        <th>Manual Scan</th>
                        <td>
                            <button type="button" id="si-run-cwv-scan" class="button" <?php echo $api_key === '' ? 'disabled' : ''; ?>>Run CWV Scan Now</button>
                            <span id="si-scan-status"></span>
                            <p class="description">Scans all published posts via the PageSpeed Insights API. Weekly scans also run automatically.</p>
                        </td>
                    </tr>
                </table>
            </div>

            <?php if ( ! $this->is_configured() ) : ?>
                <div class="notice notice-warning"><p>Enter your PageSpeed Insights API key above to start tracking Core Web Vitals.</p></div>
            <?php elseif ( empty( $report ) ) : ?>
                <div class="notice notice-info"><p>No CWV data yet. Click "Run CWV Scan Now" above, or wait for the weekly automatic scan.</p></div>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Post</th>
                            <th>LCP (ms)</th>
                            <th>CLS</th>
                            <th>INP (ms)</th>
                            <th>Perf Score</th>
                            <th>SEO</th>
                            <th>AEO</th>
                            <th>GEO</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $report as $r ) : ?>
                            <tr>
                                <td><a href="<?php echo esc_url( get_edit_post_link( $r['post_id'] ) ?? '' ); ?>"><?php echo esc_html( $r['title'] ); ?></a></td>
                                <td class="cwv-<?php echo esc_attr( $r['lcp_class'] ); ?>"><?php echo esc_html( $r['lcp'] !== null ? (string) $r['lcp'] : '—' ); ?></td>
                                <td class="cwv-<?php echo esc_attr( $r['cls_class'] ); ?>"><?php echo esc_html( $r['cls'] !== null ? (string) $r['cls'] : '—' ); ?></td>
                                <td class="cwv-<?php echo esc_attr( $r['inp_class'] ); ?>"><?php echo esc_html( $r['inp'] !== null ? (string) $r['inp'] : '—' ); ?></td>
                                <td><?php echo esc_html( $r['perf_score'] !== null ? (string) $r['perf_score'] : '—' ); ?></td>
                                <td><?php echo esc_html( (string) $r['seo_score'] ); ?></td>
                                <td><?php echo esc_html( (string) $r['aeo_score'] ); ?></td>
                                <td><?php echo esc_html( (string) $r['geo_score'] ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <script>
        jQuery(function($){
            var ajaxUrl = '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>';
            var nonce   = '<?php echo esc_js( $nonce ); ?>';

            $('#si-save-psi-key').on('click', function(){
                var btn = $(this), val = $('#si_psi_api_key').val();
                if (!val || val === '••••••••••••••••') { $('#si-psi-status').html('<span style="color:#d63638;">Enter a key first.</span>'); return; }
                btn.prop('disabled', true);
                $('#si-psi-status').html('<span style="color:#dba617;">Saving...</span>');
                $.post(ajaxUrl, { action: 'jat_search_intel_save_psi_key', nonce: nonce, psi_api_key: val }, function(r){
                    btn.prop('disabled', false);
                    if (r.success) {
                        $('#si-psi-status').html('<span style="color:#00a32a;">&#10003; ' + r.data.message + '</span>');
                        $('#si_psi_api_key').val('••••••••••••••••');
                        $('#si-run-cwv-scan').prop('disabled', false);
                    } else {
                        $('#si-psi-status').html('<span style="color:#d63638;">' + r.data.message + '</span>');
                    }
                });
            });

            $('#si_psi_api_key').on('focus', function(){ if ($(this).val() === '••••••••••••••••') $(this).val('').attr('type','text'); }).on('blur', function(){ $(this).attr('type','password'); });

            $('#si-run-cwv-scan').on('click', function(){
                var btn = $(this);
                btn.prop('disabled', true);
                $('#si-scan-status').html('<span style="color:#dba617;">Scanning... this may take a minute.</span>');
                $.post(ajaxUrl, { action: 'jat_search_intel_run_cwv_scan', nonce: nonce }, function(r){
                    btn.prop('disabled', false);
                    if (r.success) {
                        $('#si-scan-status').html('<span style="color:#00a32a;">&#10003; ' + r.data.message + '</span>');
                    } else {
                        $('#si-scan-status').html('<span style="color:#d63638;">' + r.data.message + '</span>');
                    }
                });
            });
        });
        </script>
        <?php
    }
}
