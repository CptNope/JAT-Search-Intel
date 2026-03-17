<?php

declare( strict_types=1 );

namespace Search_Intel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Search_Intel\GA4_Integration;

class GA4IntegrationTest extends TestCase {

    private function make( array $overrides = [] ): GA4_Integration {
        $settings = array_merge( [
            'enable_ga4'      => false,
            'ga4_property_id' => '',
        ], $overrides );
        return new GA4_Integration( $settings );
    }

    /*------------------------------------------------------------------
     * Configuration
     *----------------------------------------------------------------*/

    public function test_get_property_id_returns_setting(): void {
        $ga4 = $this->make( [ 'ga4_property_id' => '123456789' ] );
        $this->assertSame( '123456789', $ga4->get_property_id() );
    }

    public function test_get_property_id_empty_by_default(): void {
        $ga4 = $this->make();
        $this->assertSame( '', $ga4->get_property_id() );
    }

    public function test_is_configured_false_without_token(): void {
        global $si_test_state;
        $si_test_state['options'] = [];

        $ga4 = $this->make( [ 'ga4_property_id' => '123456789' ] );
        $this->assertFalse( $ga4->is_configured() );
    }

    public function test_is_configured_true_with_token_and_property(): void {
        global $si_test_state;
        $si_test_state['options'] = [];

        $ga4 = $this->make( [ 'ga4_property_id' => '123456789' ] );
        $ga4->save_tokens( 'test-access-token', 'test-refresh-token' );

        $this->assertTrue( $ga4->is_configured() );
    }

    public function test_save_tokens_stores_access_token(): void {
        global $si_test_state;
        $si_test_state['options'] = [];

        $ga4 = $this->make();
        $ga4->save_tokens( 'my-access-token' );

        $this->assertSame( 'my-access-token', $ga4->get_access_token() );
    }

    /*------------------------------------------------------------------
     * API calls
     *----------------------------------------------------------------*/

    public function test_run_report_fails_when_not_configured(): void {
        global $si_test_state;
        $si_test_state['options'] = [];

        $ga4    = $this->make();
        $result = $ga4->run_report( [] );

        $this->assertFalse( $result['success'] );
        $this->assertStringContainsString( 'not configured', $result['error'] );
    }

    public function test_run_report_makes_api_call(): void {
        global $si_test_state;
        $si_test_state['options'] = [];

        // Configure GA4.
        $ga4 = $this->make( [ 'ga4_property_id' => '123456789' ] );
        $ga4->save_tokens( 'test-access-token' );

        // Mock a successful API response.
        $si_test_state['remote_response'] = [
            'response' => [ 'code' => 200, 'message' => 'OK' ],
            'body'     => wp_json_encode( [ 'rows' => [] ] ),
        ];

        $result = $ga4->run_report( [ 'dateRanges' => [ [ 'startDate' => '30daysAgo', 'endDate' => 'today' ] ] ] );

        $this->assertTrue( $result['success'] );
        $this->assertArrayHasKey( 'data', $result );
    }

    /*------------------------------------------------------------------
     * Post traffic
     *----------------------------------------------------------------*/

    public function test_get_post_traffic_returns_zero_for_unknown(): void {
        global $si_test_state;
        $si_test_state['options']    = [];
        $si_test_state['transients'] = [];

        $ga4     = $this->make();
        $traffic = $ga4->get_post_traffic( 999 );

        $this->assertSame( 0, $traffic['sessions'] );
        $this->assertSame( 0, $traffic['engaged'] );
    }

    /*------------------------------------------------------------------
     * Opportunities & priorities
     *----------------------------------------------------------------*/

    public function test_get_distribution_opportunities_empty_without_data(): void {
        global $si_test_state;
        $si_test_state['options']    = [];
        $si_test_state['transients'] = [];
        $si_test_state['posts']      = [];

        $ga4 = $this->make();
        $this->assertSame( [], $ga4->get_distribution_opportunities() );
    }

    public function test_get_optimization_priorities_empty_without_data(): void {
        global $si_test_state;
        $si_test_state['options']    = [];
        $si_test_state['transients'] = [];
        $si_test_state['posts']      = [];

        $ga4 = $this->make();
        $this->assertSame( [], $ga4->get_optimization_priorities() );
    }

    public function test_get_correlation_report_empty_without_organic_data(): void {
        global $si_test_state;
        $si_test_state['options']    = [];
        $si_test_state['transients'] = [];
        $si_test_state['posts']      = [];

        $ga4 = $this->make();
        $this->assertSame( [], $ga4->get_correlation_report() );
    }
}
