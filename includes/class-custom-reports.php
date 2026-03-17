<?php
/**
 * Custom Reports & Export.
 *
 * Generates PDF site health reports, provides scheduled PDF reports via email,
 * supports CSV/JSON export for all report types, and offers a custom report
 * builder allowing users to pick modules, date range, and post types.
 *
 * @package Search_Intel
 * @since   3.3.0
 */

namespace Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Custom_Reports {

    /** @var array Plugin settings. */
    private $settings;

    /** @var string Option key for scheduled report config. */
    private const SCHEDULE_KEY = 'search_intel_report_schedules';

    /** @var string Transient prefix for generated reports. */
    private const REPORT_TRANSIENT_PREFIX = 'si_report_';

    /**
     * Constructor.
     *
     * @param array $settings Plugin settings.
     */
    public function __construct( array $settings ) {
        $this->settings = $settings;

        if ( ! ( $this->settings['enable_custom_reports'] ?? false ) ) {
            return;
        }

        // Scheduled report cron.
        add_action( 'search_intel_send_scheduled_report', [ $this, 'process_scheduled_reports' ] );

        if ( ! wp_next_scheduled( 'search_intel_send_scheduled_report' ) ) {
            wp_schedule_event( time(), 'daily', 'search_intel_send_scheduled_report' );
        }

        // AJAX endpoints for export.
        add_action( 'wp_ajax_search_intel_export_csv', [ $this, 'ajax_export_csv' ] );
        add_action( 'wp_ajax_search_intel_export_json', [ $this, 'ajax_export_json' ] );
        add_action( 'wp_ajax_search_intel_generate_pdf', [ $this, 'ajax_generate_pdf' ] );

        if ( is_admin() ) {
            add_action( 'admin_menu', [ $this, 'register_admin_page' ] );
        }
    }

    /*----------------------------------------------------------------------
     * Report data builder
     *--------------------------------------------------------------------*/

    /**
     * Build a report dataset based on the given configuration.
     *
     * @param array $config Report configuration with keys:
     *                      - modules: array of module names ('seo', 'aeo', 'geo', 'cwv', 'decay')
     *                      - post_types: array of post types (empty = all)
     *                      - date_from: start date (Y-m-d) or empty
     *                      - date_to: end date (Y-m-d) or empty
     *                      - limit: max posts (default 100)
     * @return array Report data.
     */
    public function build_report( array $config ): array {
        $modules    = $config['modules'] ?? [ 'seo', 'aeo', 'geo' ];
        $post_types = $config['post_types'] ?? [];
        $date_from  = $config['date_from'] ?? '';
        $date_to    = $config['date_to'] ?? '';
        $limit      = (int) ( $config['limit'] ?? 100 );

        $args = [
            'post_type'   => ! empty( $post_types ) ? $post_types : 'any',
            'post_status' => 'publish',
            'fields'      => 'ids',
            'numberposts' => $limit,
            'orderby'     => 'date',
            'order'       => 'DESC',
        ];

        if ( $date_from !== '' ) {
            $args['date_query'][] = [ 'after' => $date_from ];
        }
        if ( $date_to !== '' ) {
            $args['date_query'][] = [ 'before' => $date_to ];
        }

        $post_ids = get_posts( $args );
        $rows     = [];
        $totals   = [];

        foreach ( $modules as $mod ) {
            $totals[ $mod ] = 0.0;
        }

        foreach ( $post_ids as $pid ) {
            $pid = (int) $pid;

            $row = [
                'post_id'    => $pid,
                'title'      => get_the_title( $pid ),
                'post_type'  => get_post_type( $pid ),
                'date'       => get_the_date( 'Y-m-d', $pid ),
                'url'        => get_permalink( $pid ),
            ];

            // Read scores from individual meta keys (set by bulk rescore / content analyzer).
            $meta_map = [
                'seo'         => '_search_intel_seo_score',
                'aeo'         => '_search_intel_aeo_score',
                'geo'         => '_search_intel_geo_score',
                'readability' => '_search_intel_readability_score',
            ];

            foreach ( $modules as $mod ) {
                $meta_key = $meta_map[ $mod ] ?? '';
                $val      = $meta_key !== '' ? (float) get_post_meta( $pid, $meta_key, true ) : 0.0;
                $row[ $mod ] = $val;
                $totals[ $mod ] += $val;
            }

            $rows[] = $row;
        }

        $count    = count( $rows );
        $averages = [];
        foreach ( $modules as $mod ) {
            $averages[ $mod ] = $count > 0 ? round( $totals[ $mod ] / $count, 1 ) : 0.0;
        }

        return [
            'generated_at' => gmdate( 'Y-m-d H:i:s' ),
            'config'       => $config,
            'summary'      => [
                'total_posts' => $count,
                'averages'    => $averages,
            ],
            'rows'         => $rows,
        ];
    }

    /*----------------------------------------------------------------------
     * CSV export
     *--------------------------------------------------------------------*/

    /**
     * Convert report data to CSV string.
     *
     * @param array $report Report data from build_report().
     * @return string CSV content.
     */
    public function to_csv( array $report ): string {
        $rows    = $report['rows'] ?? [];
        $modules = $report['config']['modules'] ?? [ 'seo', 'aeo', 'geo' ];

        if ( empty( $rows ) ) {
            return '';
        }

        $output = fopen( 'php://temp', 'r+' );
        if ( $output === false ) {
            return '';
        }

        // Header row.
        $headers = [ 'Post ID', 'Title', 'Post Type', 'Date', 'URL' ];
        foreach ( $modules as $mod ) {
            $headers[] = strtoupper( $mod ) . ' Score';
        }
        fputcsv( $output, $headers );

        // Data rows.
        foreach ( $rows as $row ) {
            $line = [
                $row['post_id'],
                $row['title'],
                $row['post_type'],
                $row['date'],
                $row['url'],
            ];
            foreach ( $modules as $mod ) {
                $line[] = $row[ $mod ] ?? 0;
            }
            fputcsv( $output, $line );
        }

        rewind( $output );
        $csv = stream_get_contents( $output );
        fclose( $output );

        return $csv !== false ? $csv : '';
    }

    /**
     * AJAX handler for CSV export.
     */
    public function ajax_export_csv(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized', 403 );
        }

        check_ajax_referer( 'search_intel_export', 'nonce' );

        $config = $this->get_config_from_request();
        $report = $this->build_report( $config );
        $csv    = $this->to_csv( $report );

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="search-intel-report-' . gmdate( 'Y-m-d' ) . '.csv"' );
        echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV binary.
        wp_die();
    }

    /*----------------------------------------------------------------------
     * JSON export
     *--------------------------------------------------------------------*/

    /**
     * Convert report data to JSON string.
     *
     * @param array $report Report data from build_report().
     * @return string JSON string.
     */
    public function to_json( array $report ): string {
        return (string) wp_json_encode( $report, JSON_PRETTY_PRINT );
    }

    /**
     * AJAX handler for JSON export.
     */
    public function ajax_export_json(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized', 403 );
        }

        check_ajax_referer( 'search_intel_export', 'nonce' );

        $config = $this->get_config_from_request();
        $report = $this->build_report( $config );
        $json   = $this->to_json( $report );

        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="search-intel-report-' . gmdate( 'Y-m-d' ) . '.json"' );
        echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON binary.
        wp_die();
    }

    /*----------------------------------------------------------------------
     * PDF generation
     *--------------------------------------------------------------------*/

    /**
     * Generate an HTML-based PDF report.
     *
     * Returns HTML that can be rendered to PDF by a client-side library
     * (e.g., jsPDF, html2pdf) or printed via the browser.
     *
     * @param array $report Report data from build_report().
     * @return string HTML content suitable for PDF rendering.
     */
    public function generate_pdf_html( array $report ): string {
        $summary = $report['summary'] ?? [];
        $rows    = $report['rows'] ?? [];
        $modules = $report['config']['modules'] ?? [ 'seo', 'aeo', 'geo' ];
        $date    = $report['generated_at'] ?? gmdate( 'Y-m-d H:i:s' );
        $site    = get_bloginfo( 'name' );

        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <title>Search Intel Report — <?php echo esc_html( $site ); ?></title>
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 40px; color: #1e1e1e; }
                h1 { border-bottom: 2px solid #2271b1; padding-bottom: 10px; }
                .summary { background: #f0f0f1; padding: 15px; border-radius: 4px; margin-bottom: 20px; }
                table { width: 100%; border-collapse: collapse; margin-top: 10px; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; font-size: 13px; }
                th { background: #2271b1; color: #fff; }
                tr:nth-child(even) { background: #f9f9f9; }
                .footer { margin-top: 30px; font-size: 11px; color: #666; border-top: 1px solid #ddd; padding-top: 10px; }
            </style>
        </head>
        <body>
            <h1><?php echo esc_html( $site ); ?> — SEO Report</h1>
            <p>Generated: <?php echo esc_html( $date ); ?></p>

            <div class="summary">
                <strong>Total Posts:</strong> <?php echo esc_html( (string) ( $summary['total_posts'] ?? 0 ) ); ?><br>
                <?php foreach ( $modules as $mod ) : ?>
                    <strong>Avg <?php echo esc_html( strtoupper( $mod ) ); ?>:</strong>
                    <?php echo esc_html( (string) ( $summary['averages'][ $mod ] ?? 0 ) ); ?>&nbsp;&nbsp;
                <?php endforeach; ?>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Post</th>
                        <th>Type</th>
                        <th>Date</th>
                        <?php foreach ( $modules as $mod ) : ?>
                            <th><?php echo esc_html( strtoupper( $mod ) ); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $rows as $i => $row ) : ?>
                        <tr>
                            <td><?php echo esc_html( (string) ( $i + 1 ) ); ?></td>
                            <td><?php echo esc_html( $row['title'] ?? '' ); ?></td>
                            <td><?php echo esc_html( $row['post_type'] ?? '' ); ?></td>
                            <td><?php echo esc_html( $row['date'] ?? '' ); ?></td>
                            <?php foreach ( $modules as $mod ) : ?>
                                <td><?php echo esc_html( (string) ( $row[ $mod ] ?? 0 ) ); ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="footer">
                Report generated by Search Intel v<?php echo defined( 'SEARCH_INTEL_VERSION' ) ? esc_html( SEARCH_INTEL_VERSION ) : '3.3.0'; ?>
            </div>
        </body>
        </html>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * AJAX handler for PDF generation (returns print-ready HTML).
     */
    public function ajax_generate_pdf(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized', 403 );
        }

        check_ajax_referer( 'search_intel_export', 'nonce' );

        $config = $this->get_config_from_request();
        $report = $this->build_report( $config );
        $html   = $this->generate_pdf_html( $report );

        header( 'Content-Type: text/html; charset=utf-8' );
        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Full HTML page.
        wp_die();
    }

    /*----------------------------------------------------------------------
     * Scheduled reports
     *--------------------------------------------------------------------*/

    /**
     * Get all scheduled report configurations.
     *
     * @return array Array of schedule configs.
     */
    public function get_schedules(): array {
        $schedules = get_option( self::SCHEDULE_KEY, [] );
        return is_array( $schedules ) ? $schedules : [];
    }

    /**
     * Save a scheduled report configuration.
     *
     * @param array $schedule Schedule config with 'email', 'frequency', 'config' keys.
     * @return int Index of the saved schedule.
     */
    public function save_schedule( array $schedule ): int {
        $schedules   = $this->get_schedules();
        $allowed_frequencies = [ 'daily', 'weekly', 'monthly' ];
        $frequency = $schedule['frequency'] ?? 'weekly';

        $schedules[] = [
            'email'     => sanitize_email( $schedule['email'] ?? '' ),
            'frequency' => in_array( $frequency, $allowed_frequencies, true ) ? $frequency : 'weekly',
            'config'    => $schedule['config'] ?? [],
            'last_sent' => 0,
            'created'   => time(),
        ];

        update_option( self::SCHEDULE_KEY, $schedules, false );

        return count( $schedules ) - 1;
    }

    /**
     * Delete a scheduled report by index.
     *
     * @param int $index Schedule index.
     */
    public function delete_schedule( int $index ): void {
        $schedules = $this->get_schedules();
        if ( isset( $schedules[ $index ] ) ) {
            array_splice( $schedules, $index, 1 );
            update_option( self::SCHEDULE_KEY, $schedules, false );
        }
    }

    /**
     * Process and send all due scheduled reports.
     */
    public function process_scheduled_reports(): void {
        $schedules = $this->get_schedules();
        $updated   = false;

        foreach ( $schedules as &$sched ) {
            if ( ! $this->is_schedule_due( $sched ) ) {
                continue;
            }

            $config = $sched['config'] ?? [];
            $report = $this->build_report( $config );
            $html   = $this->generate_pdf_html( $report );
            $email  = $sched['email'] ?? '';

            if ( $email === '' ) {
                continue;
            }

            $subject = 'Search Intel Report — ' . get_bloginfo( 'name' ) . ' — ' . gmdate( 'Y-m-d' );

            wp_mail( $email, $subject, $html, [ 'Content-Type: text/html; charset=UTF-8' ] );

            $sched['last_sent'] = time();
            $updated = true;
        }
        unset( $sched );

        if ( $updated ) {
            update_option( self::SCHEDULE_KEY, $schedules, false );
        }
    }

    /**
     * Check if a scheduled report is due to be sent.
     *
     * @param array $schedule Schedule config.
     * @return bool True if due.
     */
    private function is_schedule_due( array $schedule ): bool {
        $last_sent = (int) ( $schedule['last_sent'] ?? 0 );
        $frequency = $schedule['frequency'] ?? 'weekly';

        $intervals = [
            'daily'   => DAY_IN_SECONDS,
            'weekly'  => WEEK_IN_SECONDS,
            'monthly' => 30 * DAY_IN_SECONDS,
        ];

        $interval = $intervals[ $frequency ] ?? WEEK_IN_SECONDS;

        return ( time() - $last_sent ) >= $interval;
    }

    /*----------------------------------------------------------------------
     * Request helpers
     *--------------------------------------------------------------------*/

    /**
     * Extract report configuration from the current AJAX/GET request.
     *
     * @return array Report config.
     */
    private function get_config_from_request(): array {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Nonce verified in calling AJAX handler.
        return [
            'modules'    => isset( $_REQUEST['modules'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_REQUEST['modules'] ) ) : [ 'seo', 'aeo', 'geo' ],
            'post_types' => isset( $_REQUEST['post_types'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_REQUEST['post_types'] ) ) : [],
            'date_from'  => isset( $_REQUEST['date_from'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['date_from'] ) ) : '',
            'date_to'    => isset( $_REQUEST['date_to'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['date_to'] ) ) : '',
            'limit'      => absint( $_REQUEST['limit'] ?? 100 ),
        ];
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
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
            'Reports & Export',
            'Reports',
            'manage_options',
            'search-intel-reports',
            [ $this, 'render_admin_page' ]
        );
    }

    /**
     * Render the reports admin page.
     */
    public function render_admin_page(): void {
        $schedules = $this->get_schedules();
        $nonce     = wp_create_nonce( 'search_intel_export' );
        ?>
        <div class="wrap">
            <h1>Reports &amp; Export</h1>

            <h2>Generate Report</h2>
            <form method="get" action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" target="_blank">
                <input type="hidden" name="nonce" value="<?php echo esc_attr( $nonce ); ?>">

                <table class="form-table">
                    <tr>
                        <th>Modules</th>
                        <td>
                            <label><input type="checkbox" name="modules[]" value="seo" checked> SEO</label>
                            <label><input type="checkbox" name="modules[]" value="aeo" checked> AEO</label>
                            <label><input type="checkbox" name="modules[]" value="geo" checked> GEO</label>
                            <label><input type="checkbox" name="modules[]" value="readability"> Readability</label>
                        </td>
                    </tr>
                    <tr>
                        <th>Date Range</th>
                        <td>
                            <input type="date" name="date_from" placeholder="From"> —
                            <input type="date" name="date_to" placeholder="To">
                        </td>
                    </tr>
                    <tr>
                        <th>Max Posts</th>
                        <td><input type="number" name="limit" value="100" min="1" max="1000"></td>
                    </tr>
                    <tr>
                        <th>Format</th>
                        <td>
                            <button type="submit" name="action" value="search_intel_export_csv" class="button">Download CSV</button>
                            <button type="submit" name="action" value="search_intel_export_json" class="button">Download JSON</button>
                            <button type="submit" name="action" value="search_intel_generate_pdf" class="button button-primary">Generate PDF</button>
                        </td>
                    </tr>
                </table>
            </form>

            <h2>Scheduled Reports</h2>
            <?php if ( empty( $schedules ) ) : ?>
                <p>No scheduled reports configured. Add one in settings to receive automated email reports.</p>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Email</th>
                            <th>Frequency</th>
                            <th>Last Sent</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $schedules as $s ) : ?>
                            <tr>
                                <td><?php echo esc_html( $s['email'] ?? '' ); ?></td>
                                <td><?php echo esc_html( ucfirst( $s['frequency'] ?? 'weekly' ) ); ?></td>
                                <td>
                                    <?php
                                    $last = (int) ( $s['last_sent'] ?? 0 );
                                    echo $last > 0 ? esc_html( gmdate( 'Y-m-d H:i', $last ) ) : '<em>Never</em>';
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
