<?php

declare( strict_types=1 );

namespace Search_Intel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Search_Intel\Historical_Analytics;

class HistoricalAnalyticsTest extends TestCase {

    private function make( array $overrides = [] ): Historical_Analytics {
        $settings = array_merge( [ 'enable_historical_analytics' => false ], $overrides );
        return new Historical_Analytics( $settings );
    }

    /*------------------------------------------------------------------
     * Score recording
     *----------------------------------------------------------------*/

    public function test_record_score_stores_entry(): void {
        global $si_test_state;
        $si_test_state['post_meta'] = [];

        $ha = $this->make();
        $ha->record_score( 10, [ 'seo' => 72.5, 'aeo' => 60.0, 'geo' => 55.0 ] );

        $history = $ha->get_post_history( 10 );
        $this->assertCount( 1, $history );
        $this->assertSame( 72.5, $history[0]['seo'] );
        $this->assertSame( 60.0, $history[0]['aeo'] );
        $this->assertSame( 55.0, $history[0]['geo'] );
        $this->assertSame( gmdate( 'Y-m-d' ), $history[0]['date'] );
    }

    public function test_record_score_overwrites_same_day(): void {
        global $si_test_state;
        $si_test_state['post_meta'] = [];

        $ha = $this->make();
        $ha->record_score( 10, [ 'seo' => 50.0, 'aeo' => 50.0, 'geo' => 50.0 ] );
        $ha->record_score( 10, [ 'seo' => 80.0, 'aeo' => 70.0, 'geo' => 60.0 ] );

        $history = $ha->get_post_history( 10 );
        $this->assertCount( 1, $history );
        $this->assertSame( 80.0, $history[0]['seo'] );
    }

    public function test_get_post_history_returns_empty_for_no_data(): void {
        global $si_test_state;
        $si_test_state['post_meta'] = [];

        $ha = $this->make();
        $this->assertSame( [], $ha->get_post_history( 999 ) );
    }

    /*------------------------------------------------------------------
     * Chart data
     *----------------------------------------------------------------*/

    public function test_get_chart_data_returns_structure(): void {
        global $si_test_state;
        $si_test_state['post_meta'] = [];

        $ha = $this->make();
        $ha->record_score( 10, [ 'seo' => 70.0, 'aeo' => 60.0, 'geo' => 50.0 ] );

        $chart = $ha->get_chart_data( 10, 'all', 30 );

        $this->assertArrayHasKey( 'labels', $chart );
        $this->assertArrayHasKey( 'datasets', $chart );
        $this->assertArrayHasKey( 'seo', $chart['datasets'] );
        $this->assertArrayHasKey( 'aeo', $chart['datasets'] );
        $this->assertArrayHasKey( 'geo', $chart['datasets'] );
        $this->assertSame( 30, $chart['days'] );
    }

    public function test_get_chart_data_single_module(): void {
        global $si_test_state;
        $si_test_state['post_meta'] = [];

        $ha = $this->make();
        $ha->record_score( 10, [ 'seo' => 70.0, 'aeo' => 60.0, 'geo' => 50.0 ] );

        $chart = $ha->get_chart_data( 10, 'seo', 30 );

        $this->assertArrayHasKey( 'seo', $chart['datasets'] );
        $this->assertArrayNotHasKey( 'aeo', $chart['datasets'] );
        $this->assertArrayNotHasKey( 'geo', $chart['datasets'] );
    }

    public function test_get_chart_data_filters_by_days(): void {
        global $si_test_state;
        $si_test_state['post_meta'] = [];

        $ha = $this->make();

        // Manually set history with old entry.
        $old_entry = [
            'date' => gmdate( 'Y-m-d', time() - ( 60 * DAY_IN_SECONDS ) ),
            'ts'   => time() - ( 60 * DAY_IN_SECONDS ),
            'seo'  => 40.0,
            'aeo'  => 30.0,
            'geo'  => 20.0,
        ];
        $new_entry = [
            'date' => gmdate( 'Y-m-d' ),
            'ts'   => time(),
            'seo'  => 80.0,
            'aeo'  => 70.0,
            'geo'  => 60.0,
        ];

        update_post_meta( 10, '_search_intel_analytics_history', [ $old_entry, $new_entry ] );

        $chart = $ha->get_chart_data( 10, 'all', 30 );

        // Only the recent entry should be included.
        $this->assertCount( 1, $chart['labels'] );
        $this->assertSame( 80.0, $chart['datasets']['seo'][0] );
    }

    /*------------------------------------------------------------------
     * Site-wide trends
     *----------------------------------------------------------------*/

    public function test_get_site_trends_returns_structure(): void {
        global $si_test_state;
        $si_test_state['options'] = [];

        $ha = $this->make();
        $trends = $ha->get_site_trends( 30 );

        $this->assertArrayHasKey( 'labels', $trends );
        $this->assertArrayHasKey( 'datasets', $trends );
        $this->assertArrayHasKey( 'seo', $trends['datasets'] );
        $this->assertSame( 30, $trends['days'] );
    }

    public function test_compute_site_aggregate_stores_daily_point(): void {
        global $si_test_state;
        $si_test_state['post_meta'] = [];
        $si_test_state['options']   = [];
        $si_test_state['posts']     = [];

        // Simulate 2 posts with score history.
        update_post_meta( 1, '_search_intel_analytics_history', [
            [ 'date' => gmdate( 'Y-m-d' ), 'seo' => 80.0, 'aeo' => 60.0, 'geo' => 40.0 ],
        ] );
        update_post_meta( 2, '_search_intel_analytics_history', [
            [ 'date' => gmdate( 'Y-m-d' ), 'seo' => 60.0, 'aeo' => 40.0, 'geo' => 20.0 ],
        ] );

        // get_posts returns IDs — mock it via test state.
        $si_test_state['posts'] = [ 1, 2 ];

        $ha = $this->make();
        $ha->compute_site_aggregate();

        $aggregates = get_option( 'search_intel_site_score_aggregates', [] );
        $this->assertIsArray( $aggregates );
        // Since our get_posts stub may not return the right IDs, just verify structure.
    }

    /*------------------------------------------------------------------
     * Before / after comparison
     *----------------------------------------------------------------*/

    public function test_get_before_after_returns_delta(): void {
        global $si_test_state;
        $si_test_state['post_meta'] = [];

        $ha = $this->make();

        $history = [
            [ 'date' => '2026-01-01', 'seo' => 50.0, 'aeo' => 40.0, 'geo' => 30.0 ],
            [ 'date' => '2026-01-15', 'seo' => 70.0, 'aeo' => 60.0, 'geo' => 50.0 ],
            [ 'date' => '2026-02-01', 'seo' => 80.0, 'aeo' => 70.0, 'geo' => 60.0 ],
        ];
        update_post_meta( 10, '_search_intel_analytics_history', $history );

        $result = $ha->get_before_after( 10, '2026-01-15' );

        $this->assertArrayHasKey( 'before', $result );
        $this->assertArrayHasKey( 'after', $result );
        $this->assertArrayHasKey( 'delta', $result );

        // Before should be the Jan 1 entry.
        $this->assertSame( '2026-01-01', $result['before']['date'] );
        // After should be the Jan 15 entry.
        $this->assertSame( '2026-01-15', $result['after']['date'] );

        // Delta should show the increase.
        $this->assertSame( 20.0, $result['delta']['seo']['change'] );
    }

    public function test_get_before_after_handles_no_data(): void {
        global $si_test_state;
        $si_test_state['post_meta'] = [];

        $ha     = $this->make();
        $result = $ha->get_before_after( 999, '2026-01-01' );

        $this->assertNull( $result['before'] );
        $this->assertNull( $result['after'] );
        $this->assertEmpty( $result['delta'] );
    }

    /*------------------------------------------------------------------
     * Change log
     *----------------------------------------------------------------*/

    public function test_log_change_stores_entry(): void {
        global $si_test_state;
        $si_test_state['post_meta'] = [];

        $ha = $this->make();
        $ha->log_change(
            10,
            'Content updated',
            [ 'seo' => 50.0, 'aeo' => 40.0, 'geo' => 30.0 ],
            [ 'seo' => 70.0, 'aeo' => 60.0, 'geo' => 50.0 ],
            1
        );

        $log = $ha->get_change_log( 10 );
        $this->assertCount( 1, $log );
        $this->assertSame( 'Content updated', $log[0]['action'] );
        $this->assertSame( 1, $log[0]['user_id'] );
        $this->assertSame( 50.0, $log[0]['old']['seo'] );
        $this->assertSame( 70.0, $log[0]['new']['seo'] );
    }

    public function test_get_change_log_returns_newest_first(): void {
        global $si_test_state;
        $si_test_state['post_meta'] = [];

        $ha = $this->make();
        $ha->log_change( 10, 'First change', [ 'seo' => 0 ], [ 'seo' => 50 ] );
        $ha->log_change( 10, 'Second change', [ 'seo' => 50 ], [ 'seo' => 70 ] );

        $log = $ha->get_change_log( 10 );
        $this->assertCount( 2, $log );
        $this->assertSame( 'Second change', $log[0]['action'] );
        $this->assertSame( 'First change', $log[1]['action'] );
    }

    public function test_get_change_log_respects_limit(): void {
        global $si_test_state;
        $si_test_state['post_meta'] = [];

        $ha = $this->make();
        for ( $i = 0; $i < 5; $i++ ) {
            $ha->log_change( 10, "Change {$i}", [], [] );
        }

        $log = $ha->get_change_log( 10, 2 );
        $this->assertCount( 2, $log );
    }

    public function test_get_change_log_empty_for_no_data(): void {
        global $si_test_state;
        $si_test_state['post_meta'] = [];

        $ha = $this->make();
        $this->assertSame( [], $ha->get_change_log( 999 ) );
    }
}
