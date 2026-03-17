<?php

declare( strict_types=1 );

namespace Search_Intel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Search_Intel\Custom_Reports;

class CustomReportsTest extends TestCase {

    private function make( array $overrides = [] ): Custom_Reports {
        $settings = array_merge( [ 'enable_custom_reports' => false ], $overrides );
        return new Custom_Reports( $settings );
    }

    /*------------------------------------------------------------------
     * Report builder
     *----------------------------------------------------------------*/

    public function test_build_report_returns_structure(): void {
        global $si_test_state;
        $si_test_state['post_meta'] = [];
        $si_test_state['posts']     = [];

        $cr     = $this->make();
        $report = $cr->build_report( [
            'modules'    => [ 'seo', 'aeo', 'geo' ],
            'post_types' => [],
            'date_from'  => '',
            'date_to'    => '',
            'limit'      => 10,
        ] );

        $this->assertArrayHasKey( 'generated_at', $report );
        $this->assertArrayHasKey( 'config', $report );
        $this->assertArrayHasKey( 'summary', $report );
        $this->assertArrayHasKey( 'rows', $report );
        $this->assertArrayHasKey( 'total_posts', $report['summary'] );
        $this->assertArrayHasKey( 'averages', $report['summary'] );
    }

    public function test_build_report_uses_selected_modules(): void {
        global $si_test_state;
        $si_test_state['post_meta'] = [];
        $si_test_state['posts']     = [];

        $cr     = $this->make();
        $report = $cr->build_report( [
            'modules' => [ 'seo' ],
        ] );

        $this->assertArrayHasKey( 'seo', $report['summary']['averages'] );
        $this->assertArrayNotHasKey( 'aeo', $report['summary']['averages'] );
        $this->assertArrayNotHasKey( 'geo', $report['summary']['averages'] );
    }

    public function test_build_report_empty_when_no_posts(): void {
        global $si_test_state;
        $si_test_state['post_meta'] = [];
        $si_test_state['posts']     = [];

        $cr     = $this->make();
        $report = $cr->build_report( [] );

        $this->assertSame( 0, $report['summary']['total_posts'] );
        $this->assertEmpty( $report['rows'] );
    }

    /*------------------------------------------------------------------
     * CSV export
     *----------------------------------------------------------------*/

    public function test_to_csv_returns_empty_for_no_rows(): void {
        $cr  = $this->make();
        $csv = $cr->to_csv( [ 'rows' => [], 'config' => [ 'modules' => [ 'seo' ] ] ] );

        $this->assertSame( '', $csv );
    }

    public function test_to_csv_includes_headers_and_data(): void {
        $cr = $this->make();

        $report = [
            'config' => [ 'modules' => [ 'seo', 'aeo' ] ],
            'rows'   => [
                [
                    'post_id'   => 1,
                    'title'     => 'Test Post',
                    'post_type' => 'post',
                    'date'      => '2026-01-01',
                    'url'       => 'https://example.com/test',
                    'seo'       => 75.0,
                    'aeo'       => 60.0,
                ],
            ],
        ];

        $csv = $cr->to_csv( $report );

        $this->assertStringContainsString( 'Post ID', $csv );
        $this->assertStringContainsString( 'SEO Score', $csv );
        $this->assertStringContainsString( 'AEO Score', $csv );
        $this->assertStringContainsString( 'Test Post', $csv );
        $this->assertStringContainsString( '75', $csv );
    }

    public function test_to_csv_multiple_rows(): void {
        $cr = $this->make();

        $report = [
            'config' => [ 'modules' => [ 'seo' ] ],
            'rows'   => [
                [ 'post_id' => 1, 'title' => 'First', 'post_type' => 'post', 'date' => '2026-01-01', 'url' => 'https://example.com/1', 'seo' => 80.0 ],
                [ 'post_id' => 2, 'title' => 'Second', 'post_type' => 'post', 'date' => '2026-01-02', 'url' => 'https://example.com/2', 'seo' => 60.0 ],
            ],
        ];

        $csv   = $cr->to_csv( $report );
        $lines = explode( "\n", trim( $csv ) );

        $this->assertCount( 3, $lines ); // header + 2 data rows
    }

    /*------------------------------------------------------------------
     * JSON export
     *----------------------------------------------------------------*/

    public function test_to_json_returns_valid_json(): void {
        $cr = $this->make();

        $report = [
            'generated_at' => '2026-01-01 00:00:00',
            'config'       => [ 'modules' => [ 'seo' ] ],
            'summary'      => [ 'total_posts' => 1, 'averages' => [ 'seo' => 75.0 ] ],
            'rows'         => [
                [ 'post_id' => 1, 'title' => 'Test', 'seo' => 75.0 ],
            ],
        ];

        $json   = $cr->to_json( $report );
        $parsed = json_decode( $json, true );

        $this->assertIsArray( $parsed );
        $this->assertSame( 1, $parsed['summary']['total_posts'] );
    }

    /*------------------------------------------------------------------
     * PDF HTML generation
     *----------------------------------------------------------------*/

    public function test_generate_pdf_html_contains_report_data(): void {
        $cr = $this->make();

        $report = [
            'generated_at' => '2026-01-01 00:00:00',
            'config'       => [ 'modules' => [ 'seo', 'aeo' ] ],
            'summary'      => [ 'total_posts' => 2, 'averages' => [ 'seo' => 70.0, 'aeo' => 55.0 ] ],
            'rows'         => [
                [ 'post_id' => 1, 'title' => 'Alpha Post', 'post_type' => 'post', 'date' => '2026-01-01', 'seo' => 80.0, 'aeo' => 60.0 ],
                [ 'post_id' => 2, 'title' => 'Beta Post', 'post_type' => 'page', 'date' => '2026-01-02', 'seo' => 60.0, 'aeo' => 50.0 ],
            ],
        ];

        $html = $cr->generate_pdf_html( $report );

        $this->assertStringContainsString( '<!DOCTYPE html>', $html );
        $this->assertStringContainsString( 'Alpha Post', $html );
        $this->assertStringContainsString( 'Beta Post', $html );
        $this->assertStringContainsString( 'SEO', $html );
        $this->assertStringContainsString( 'AEO', $html );
        $this->assertStringContainsString( 'Total Posts', $html );
    }

    public function test_generate_pdf_html_handles_empty_report(): void {
        $cr = $this->make();

        $report = [
            'generated_at' => '2026-01-01 00:00:00',
            'config'       => [ 'modules' => [ 'seo' ] ],
            'summary'      => [ 'total_posts' => 0, 'averages' => [ 'seo' => 0.0 ] ],
            'rows'         => [],
        ];

        $html = $cr->generate_pdf_html( $report );

        $this->assertStringContainsString( '<!DOCTYPE html>', $html );
        $this->assertStringContainsString( 'Total Posts', $html );
    }

    /*------------------------------------------------------------------
     * Scheduled reports
     *----------------------------------------------------------------*/

    public function test_get_schedules_empty_by_default(): void {
        global $si_test_state;
        $si_test_state['options'] = [];

        $cr = $this->make();
        $this->assertSame( [], $cr->get_schedules() );
    }

    public function test_save_schedule_stores_config(): void {
        global $si_test_state;
        $si_test_state['options'] = [];

        $cr    = $this->make();
        $index = $cr->save_schedule( [
            'email'     => 'admin@example.com',
            'frequency' => 'weekly',
            'config'    => [ 'modules' => [ 'seo' ] ],
        ] );

        $this->assertSame( 0, $index );

        $schedules = $cr->get_schedules();
        $this->assertCount( 1, $schedules );
        $this->assertSame( 'admin@example.com', $schedules[0]['email'] );
        $this->assertSame( 'weekly', $schedules[0]['frequency'] );
    }

    public function test_save_multiple_schedules(): void {
        global $si_test_state;
        $si_test_state['options'] = [];

        $cr = $this->make();
        $cr->save_schedule( [ 'email' => 'a@test.com', 'frequency' => 'daily' ] );
        $cr->save_schedule( [ 'email' => 'b@test.com', 'frequency' => 'monthly' ] );

        $schedules = $cr->get_schedules();
        $this->assertCount( 2, $schedules );
    }

    public function test_delete_schedule_removes_by_index(): void {
        global $si_test_state;
        $si_test_state['options'] = [];

        $cr = $this->make();
        $cr->save_schedule( [ 'email' => 'a@test.com', 'frequency' => 'daily' ] );
        $cr->save_schedule( [ 'email' => 'b@test.com', 'frequency' => 'weekly' ] );
        $cr->delete_schedule( 0 );

        $schedules = $cr->get_schedules();
        $this->assertCount( 1, $schedules );
        $this->assertSame( 'b@test.com', $schedules[0]['email'] );
    }

    public function test_process_scheduled_reports_sends_email(): void {
        global $si_test_state;
        $si_test_state['options']   = [];
        $si_test_state['post_meta'] = [];
        $si_test_state['posts']     = [];

        $cr = $this->make();
        $cr->save_schedule( [
            'email'     => 'test@example.com',
            'frequency' => 'daily',
            'config'    => [ 'modules' => [ 'seo' ], 'limit' => 5 ],
        ] );

        $cr->process_scheduled_reports();

        $this->assertArrayHasKey( 'last_email', $si_test_state );
        $this->assertSame( 'test@example.com', $si_test_state['last_email']['to'] );
        $this->assertStringContainsString( 'Search Intel Report', $si_test_state['last_email']['subject'] );
    }
}
