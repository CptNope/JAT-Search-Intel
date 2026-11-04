<?php

declare( strict_types=1 );

namespace JAT_Search_Intel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use JAT_Search_Intel\Core_Web_Vitals;

class CoreWebVitalsTest extends TestCase {

    private function make( array $overrides = [] ): Core_Web_Vitals {
        $settings = array_merge( [
            'enable_cwv'  => false,
            'psi_api_key' => '',
        ], $overrides );
        return new Core_Web_Vitals( $settings );
    }

    /*------------------------------------------------------------------
     * Configuration
     *----------------------------------------------------------------*/

    public function test_get_api_key_returns_setting(): void {
        $cwv = $this->make( [ 'psi_api_key' => 'my-api-key' ] );
        $this->assertSame( 'my-api-key', $cwv->get_api_key() );
    }

    public function test_get_api_key_empty_by_default(): void {
        $cwv = $this->make();
        $this->assertSame( '', $cwv->get_api_key() );
    }

    public function test_is_configured_false_without_key(): void {
        $cwv = $this->make();
        $this->assertFalse( $cwv->is_configured() );
    }

    public function test_is_configured_true_with_key(): void {
        $cwv = $this->make( [ 'psi_api_key' => 'my-key' ] );
        $this->assertTrue( $cwv->is_configured() );
    }

    /*------------------------------------------------------------------
     * CWV storage
     *----------------------------------------------------------------*/

    public function test_store_cwv_saves_entry(): void {
        global $si_test_state;
        $si_test_state['post_meta'] = [];

        $cwv = $this->make();
        $cwv->store_cwv( 10, [
            'strategy'   => 'mobile',
            'lcp'        => 2100.5,
            'cls'        => 0.08,
            'inp'        => 150.0,
            'perf_score' => 85,
        ] );

        $history = $cwv->get_cwv_history( 10 );
        $this->assertCount( 1, $history );
        $this->assertSame( 'mobile', $history[0]['strategy'] );
        $this->assertSame( 2100.5, $history[0]['lcp'] );
        $this->assertSame( 0.08, $history[0]['cls'] );
        $this->assertSame( 150.0, $history[0]['inp'] );
    }

    public function test_get_cwv_history_empty_for_no_data(): void {
        global $si_test_state;
        $si_test_state['post_meta'] = [];

        $cwv = $this->make();
        $this->assertSame( [], $cwv->get_cwv_history( 999 ) );
    }

    public function test_get_latest_cwv_returns_last_entry(): void {
        global $si_test_state;
        $si_test_state['post_meta'] = [];

        $cwv = $this->make();
        $cwv->store_cwv( 10, [ 'lcp' => 2000.0, 'cls' => 0.1, 'inp' => 200.0, 'perf_score' => 70 ] );
        $cwv->store_cwv( 10, [ 'lcp' => 1800.0, 'cls' => 0.05, 'inp' => 100.0, 'perf_score' => 90 ] );

        $latest = $cwv->get_latest_cwv( 10 );
        $this->assertSame( 1800.0, $latest['lcp'] );
    }

    public function test_get_latest_cwv_empty_for_no_data(): void {
        global $si_test_state;
        $si_test_state['post_meta'] = [];

        $cwv = $this->make();
        $this->assertSame( [], $cwv->get_latest_cwv( 999 ) );
    }

    /*------------------------------------------------------------------
     * Metric classification
     *----------------------------------------------------------------*/

    public function test_classify_lcp_good(): void {
        $cwv = $this->make();
        $this->assertSame( 'good', $cwv->classify_metric( 'lcp', 2000.0 ) );
    }

    public function test_classify_lcp_needs_improvement(): void {
        $cwv = $this->make();
        $this->assertSame( 'needs-improvement', $cwv->classify_metric( 'lcp', 3000.0 ) );
    }

    public function test_classify_lcp_poor(): void {
        $cwv = $this->make();
        $this->assertSame( 'poor', $cwv->classify_metric( 'lcp', 5000.0 ) );
    }

    public function test_classify_cls_good(): void {
        $cwv = $this->make();
        $this->assertSame( 'good', $cwv->classify_metric( 'cls', 0.05 ) );
    }

    public function test_classify_cls_needs_improvement(): void {
        $cwv = $this->make();
        $this->assertSame( 'needs-improvement', $cwv->classify_metric( 'cls', 0.15 ) );
    }

    public function test_classify_cls_poor(): void {
        $cwv = $this->make();
        $this->assertSame( 'poor', $cwv->classify_metric( 'cls', 0.30 ) );
    }

    public function test_classify_inp_good(): void {
        $cwv = $this->make();
        $this->assertSame( 'good', $cwv->classify_metric( 'inp', 150.0 ) );
    }

    public function test_classify_inp_needs_improvement(): void {
        $cwv = $this->make();
        $this->assertSame( 'needs-improvement', $cwv->classify_metric( 'inp', 300.0 ) );
    }

    public function test_classify_inp_poor(): void {
        $cwv = $this->make();
        $this->assertSame( 'poor', $cwv->classify_metric( 'inp', 600.0 ) );
    }

    public function test_classify_null_returns_unknown(): void {
        $cwv = $this->make();
        $this->assertSame( 'unknown', $cwv->classify_metric( 'lcp', null ) );
    }

    public function test_classify_unknown_metric_returns_unknown(): void {
        $cwv = $this->make();
        $this->assertSame( 'unknown', $cwv->classify_metric( 'foobar', 123.0 ) );
    }

    /*------------------------------------------------------------------
     * CWV trends
     *----------------------------------------------------------------*/

    public function test_get_cwv_trends_returns_structure(): void {
        global $si_test_state;
        $si_test_state['post_meta'] = [];

        $cwv = $this->make();
        $cwv->store_cwv( 10, [ 'lcp' => 2000.0, 'cls' => 0.08, 'inp' => 150.0, 'perf_score' => 85 ] );

        $trends = $cwv->get_cwv_trends( 10, 30 );

        $this->assertArrayHasKey( 'labels', $trends );
        $this->assertArrayHasKey( 'lcp', $trends );
        $this->assertArrayHasKey( 'cls', $trends );
        $this->assertArrayHasKey( 'inp', $trends );
        $this->assertArrayHasKey( 'perf', $trends );
        $this->assertCount( 1, $trends['labels'] );
    }

    public function test_get_cwv_trends_empty_for_no_data(): void {
        global $si_test_state;
        $si_test_state['post_meta'] = [];

        $cwv    = $this->make();
        $trends = $cwv->get_cwv_trends( 999, 30 );

        $this->assertEmpty( $trends['labels'] );
    }

    /*------------------------------------------------------------------
     * Fetch / scan
     *----------------------------------------------------------------*/

    public function test_fetch_cwv_fails_without_api_key(): void {
        $cwv    = $this->make();
        $result = $cwv->fetch_cwv( 'https://example.com' );

        $this->assertFalse( $result['success'] );
        $this->assertStringContainsString( 'not configured', $result['error'] );
    }

    public function test_run_scan_returns_stats_structure(): void {
        $cwv   = $this->make();
        $stats = $cwv->run_scan( 5 );

        $this->assertArrayHasKey( 'scanned', $stats );
        $this->assertArrayHasKey( 'success', $stats );
        $this->assertArrayHasKey( 'failed', $stats );
        // Without API key, all should be 0.
        $this->assertSame( 0, $stats['scanned'] );
    }

    /*------------------------------------------------------------------
     * Correlation report
     *----------------------------------------------------------------*/

    public function test_get_correlation_report_empty_without_cwv_data(): void {
        global $si_test_state;
        $si_test_state['post_meta'] = [];
        $si_test_state['posts']     = [];

        $cwv = $this->make();
        $this->assertSame( [], $cwv->get_correlation_report() );
    }
}
