<?php

use PHPUnit\Framework\TestCase;
use Search_Intel\Content_Decay;

class ContentDecayTest extends TestCase {

    private function make( array $overrides = [] ): Content_Decay {
        $defaults = [
            'enable_content_decay' => false, // Disabled to prevent hooks in constructor.
            'decay_threshold'      => 10.0,
            'decay_lookback_days'  => 30,
            'decay_auto_queue'     => true,
        ];
        return new Content_Decay( array_merge( $defaults, $overrides ) );
    }

    protected function setUp(): void {
        global $si_test_state;
        $si_test_state['post_meta'] = [];
        $si_test_state['options']   = [];
        $si_test_state['posts']     = [];
    }

    // ── Snapshot storage ────────────────────────────────────────────────

    public function test_store_snapshot_creates_history(): void {
        global $si_test_state;
        $decay = $this->make();

        $decay->store_snapshot( 1, [ 'seo' => 80, 'aeo' => 70, 'geo' => 60, 'readability' => 75 ] );

        $history = $decay->get_score_history( 1 );
        $this->assertCount( 1, $history );
        $this->assertSame( 80, $history[0]['seo'] );
        $this->assertSame( 70, $history[0]['aeo'] );
        $this->assertSame( 60, $history[0]['geo'] );
    }

    public function test_store_snapshot_replaces_same_day(): void {
        $decay = $this->make();

        $decay->store_snapshot( 1, [ 'seo' => 80, 'aeo' => 70, 'geo' => 60, 'readability' => 75 ] );
        $decay->store_snapshot( 1, [ 'seo' => 85, 'aeo' => 72, 'geo' => 65, 'readability' => 78 ] );

        $history = $decay->get_score_history( 1 );
        $this->assertCount( 1, $history );
        $this->assertSame( 85, $history[0]['seo'] );
    }

    public function test_get_latest_snapshot(): void {
        global $si_test_state;
        $si_test_state['post_meta'][1]['_search_intel_score_history'] = [
            [ 'date' => '2026-01-01', 'seo' => 80, 'aeo' => 70, 'geo' => 60, 'readability' => 75 ],
            [ 'date' => '2026-02-01', 'seo' => 70, 'aeo' => 65, 'geo' => 55, 'readability' => 72 ],
        ];

        $decay  = $this->make();
        $latest = $decay->get_latest_snapshot( 1 );

        $this->assertNotNull( $latest );
        $this->assertSame( '2026-02-01', $latest['date'] );
        $this->assertSame( 70, $latest['seo'] );
    }

    public function test_get_latest_snapshot_null_when_empty(): void {
        $decay = $this->make();
        $this->assertNull( $decay->get_latest_snapshot( 999 ) );
    }

    public function test_get_snapshot_days_ago(): void {
        global $si_test_state;
        $thirty_days_ago = gmdate( 'Y-m-d', strtotime( '-30 days' ) );
        $today           = gmdate( 'Y-m-d' );

        $si_test_state['post_meta'][1]['_search_intel_score_history'] = [
            [ 'date' => $thirty_days_ago, 'seo' => 90, 'aeo' => 85, 'geo' => 80, 'readability' => 88 ],
            [ 'date' => $today, 'seo' => 70, 'aeo' => 65, 'geo' => 55, 'readability' => 72 ],
        ];

        $decay = $this->make();
        $snap  = $decay->get_snapshot_days_ago( 1, 30 );

        $this->assertNotNull( $snap );
        $this->assertSame( $thirty_days_ago, $snap['date'] );
        $this->assertSame( 90, $snap['seo'] );
    }

    // ── Decay detection ─────────────────────────────────────────────────

    public function test_detect_decay_finds_drop(): void {
        global $si_test_state;
        $thirty_days_ago = gmdate( 'Y-m-d', strtotime( '-31 days' ) );
        $today           = gmdate( 'Y-m-d' );

        $si_test_state['post_meta'][1]['_search_intel_score_history'] = [
            [ 'date' => $thirty_days_ago, 'seo' => 80, 'aeo' => 70, 'geo' => 60, 'readability' => 75 ],
            [ 'date' => $today, 'seo' => 60, 'aeo' => 70, 'geo' => 60, 'readability' => 75 ],
        ];

        $decay  = $this->make();
        $result = $decay->detect_decay( 1 );

        $this->assertTrue( $result['decaying'] );
        $this->assertCount( 1, $result['drops'] );
        $this->assertSame( 'SEO', $result['drops'][0]['module'] );
        $this->assertSame( 80, $result['drops'][0]['old'] );
        $this->assertSame( 60, $result['drops'][0]['new'] );
        $this->assertSame( 25.0, $result['drops'][0]['drop_pct'] );
    }

    public function test_detect_decay_no_drop_when_stable(): void {
        global $si_test_state;
        $thirty_days_ago = gmdate( 'Y-m-d', strtotime( '-31 days' ) );
        $today           = gmdate( 'Y-m-d' );

        $si_test_state['post_meta'][1]['_search_intel_score_history'] = [
            [ 'date' => $thirty_days_ago, 'seo' => 80, 'aeo' => 70, 'geo' => 60, 'readability' => 75 ],
            [ 'date' => $today, 'seo' => 78, 'aeo' => 70, 'geo' => 58, 'readability' => 75 ],
        ];

        $decay  = $this->make();
        $result = $decay->detect_decay( 1 );

        $this->assertFalse( $result['decaying'] );
        $this->assertEmpty( $result['drops'] );
    }

    public function test_detect_decay_returns_false_without_history(): void {
        $decay  = $this->make();
        $result = $decay->detect_decay( 999 );

        $this->assertFalse( $result['decaying'] );
    }

    public function test_detect_decay_multiple_modules_dropping(): void {
        global $si_test_state;
        $thirty_days_ago = gmdate( 'Y-m-d', strtotime( '-31 days' ) );
        $today           = gmdate( 'Y-m-d' );

        $si_test_state['post_meta'][1]['_search_intel_score_history'] = [
            [ 'date' => $thirty_days_ago, 'seo' => 90, 'aeo' => 80, 'geo' => 70, 'readability' => 75 ],
            [ 'date' => $today, 'seo' => 60, 'aeo' => 50, 'geo' => 40, 'readability' => 75 ],
        ];

        $decay  = $this->make();
        $result = $decay->detect_decay( 1 );

        $this->assertTrue( $result['decaying'] );
        $this->assertCount( 3, $result['drops'] );
    }

    // ── Settings ────────────────────────────────────────────────────────

    public function test_get_decay_threshold_default(): void {
        $decay = $this->make();
        $this->assertSame( 10.0, $decay->get_decay_threshold() );
    }

    public function test_get_decay_threshold_custom(): void {
        $decay = $this->make( [ 'decay_threshold' => 15.0 ] );
        $this->assertSame( 15.0, $decay->get_decay_threshold() );
    }

    public function test_get_lookback_days_default(): void {
        $decay = $this->make();
        $this->assertSame( 30, $decay->get_lookback_days() );
    }

    public function test_get_lookback_days_minimum_7(): void {
        $decay = $this->make( [ 'decay_lookback_days' => 3 ] );
        $this->assertSame( 7, $decay->get_lookback_days() );
    }

    // ── Trend data ──────────────────────────────────────────────────────

    public function test_get_trend_data_filters_by_period(): void {
        global $si_test_state;
        $old   = gmdate( 'Y-m-d', strtotime( '-60 days' ) );
        $mid   = gmdate( 'Y-m-d', strtotime( '-15 days' ) );
        $today = gmdate( 'Y-m-d' );

        $si_test_state['post_meta'][1]['_search_intel_score_history'] = [
            [ 'date' => $old, 'seo' => 90, 'aeo' => 85, 'geo' => 80, 'readability' => 88 ],
            [ 'date' => $mid, 'seo' => 75, 'aeo' => 70, 'geo' => 65, 'readability' => 75 ],
            [ 'date' => $today, 'seo' => 70, 'aeo' => 65, 'geo' => 55, 'readability' => 72 ],
        ];

        $decay = $this->make();
        $trend = $decay->get_trend_data( 1, 'seo', 30 );

        $this->assertCount( 2, $trend ); // Only mid + today within 30 days.
        $this->assertSame( 75, $trend[0]['score'] );
        $this->assertSame( 70, $trend[1]['score'] );
    }

    public function test_get_score_change(): void {
        global $si_test_state;
        $thirty_days_ago = gmdate( 'Y-m-d', strtotime( '-31 days' ) );
        $today           = gmdate( 'Y-m-d' );

        $si_test_state['post_meta'][1]['_search_intel_score_history'] = [
            [ 'date' => $thirty_days_ago, 'seo' => 80, 'aeo' => 70, 'geo' => 60, 'readability' => 75 ],
            [ 'date' => $today, 'seo' => 70, 'aeo' => 65, 'geo' => 55, 'readability' => 72 ],
        ];

        $decay  = $this->make();
        $change = $decay->get_score_change( 1, 'seo', 30 );

        $this->assertSame( 70, $change['current'] );
        $this->assertSame( 80, $change['previous'] );
        $this->assertSame( -10, $change['change'] );
        $this->assertSame( -12.5, $change['change_pct'] );
    }

    // ── Refresh suggestions ─────────────────────────────────────────────

    public function test_get_refresh_suggestions_for_decaying_post(): void {
        global $si_test_state;
        $thirty_days_ago = gmdate( 'Y-m-d', strtotime( '-31 days' ) );
        $today           = gmdate( 'Y-m-d' );

        $si_test_state['post_meta'][1]['_search_intel_score_history'] = [
            [ 'date' => $thirty_days_ago, 'seo' => 80, 'aeo' => 80, 'geo' => 80, 'readability' => 75 ],
            [ 'date' => $today, 'seo' => 40, 'aeo' => 40, 'geo' => 40, 'readability' => 75 ],
        ];

        $decay       = $this->make();
        $suggestions = $decay->get_refresh_suggestions( 1 );

        // Should have suggestions for SEO, AEO, GEO drops + below-50 warnings + general tip.
        $this->assertGreaterThan( 3, count( $suggestions ) );
        $this->assertStringContainsString( 'SEO score dropped', $suggestions[0] );
    }

    public function test_get_refresh_suggestions_non_decaying(): void {
        $decay       = $this->make();
        $suggestions = $decay->get_refresh_suggestions( 999 );

        $this->assertCount( 1, $suggestions );
        $this->assertStringContainsString( 'not currently flagged', $suggestions[0] );
    }
}
