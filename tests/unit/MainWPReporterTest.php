<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Search_Intel\MainWP_Reporter;

/**
 * Unit tests for the MainWP Child Reporter class.
 */
class MainWPReporterTest extends TestCase {

	private MainWP_Reporter $reporter;

	protected function setUp(): void {
		global $si_test_state;
		$si_test_state['options'] = [];
		$si_test_state['posts']   = [];
		$si_test_state['meta']    = [];

		$this->reporter = new MainWP_Reporter( [
			'enable_seo'    => true,
			'enable_aeo'    => true,
			'enable_geo'    => false,
			'enable_schema' => true,
		] );
	}

	// ── Detection ────────────────────────────────────────────────────────────

	public function test_is_mainwp_child_active_returns_false_without_constant(): void {
		$this->assertFalse( $this->reporter->is_mainwp_child_active() );
	}

	// ── Feature Status ───────────────────────────────────────────────────────

	public function test_get_feature_status_reflects_settings(): void {
		$status = $this->reporter->get_feature_status();

		$this->assertTrue( $status['seo'] );
		$this->assertTrue( $status['aeo'] );
		$this->assertFalse( $status['geo'] );
		$this->assertTrue( $status['schema'] );
		$this->assertFalse( $status['sitemap'] );
	}

	public function test_get_feature_status_contains_all_keys(): void {
		$status = $this->reporter->get_feature_status();

		$expected_keys = [
			'seo', 'aeo', 'geo', 'schema', 'sitemap', 'redirects',
			'indexnow', 'woocommerce', 'rbac', 'white_label', 'scale', 'multilang',
		];

		foreach ( $expected_keys as $key ) {
			$this->assertArrayHasKey( $key, $status, "Missing key: {$key}" );
		}
	}

	// ── Aggregate Scores ─────────────────────────────────────────────────────

	public function test_get_aggregate_scores_returns_zeros_when_no_posts(): void {
		$scores = $this->reporter->get_aggregate_scores();

		$this->assertEquals( 0.0, $scores['avg_seo'] );
		$this->assertEquals( 0.0, $scores['avg_aeo'] );
		$this->assertEquals( 0.0, $scores['avg_geo'] );
		$this->assertEquals( 0.0, $scores['avg_readability'] );
	}

	public function test_get_aggregate_scores_averages_correctly(): void {
		global $si_test_state;

		// Create two published posts with scores.
		$si_test_state['posts'][1] = [
			'ID'           => 1,
			'post_title'   => 'Post One',
			'post_content' => 'Content one',
			'post_status'  => 'publish',
			'post_type'    => 'post',
		];
		$si_test_state['posts'][2] = [
			'ID'           => 2,
			'post_title'   => 'Post Two',
			'post_content' => 'Content two',
			'post_status'  => 'publish',
			'post_type'    => 'post',
		];

		$si_test_state['post_meta'][1] = [
			'_search_intel_seo_score'         => '80',
			'_search_intel_aeo_score'         => '60',
			'_search_intel_geo_score'         => '70',
			'_search_intel_readability_score'  => '90',
		];
		$si_test_state['post_meta'][2] = [
			'_search_intel_seo_score'         => '40',
			'_search_intel_aeo_score'         => '20',
			'_search_intel_geo_score'         => '30',
			'_search_intel_readability_score'  => '50',
		];

		$scores = $this->reporter->get_aggregate_scores();

		$this->assertEquals( 60.0, $scores['avg_seo'] );
		$this->assertEquals( 40.0, $scores['avg_aeo'] );
		$this->assertEquals( 50.0, $scores['avg_geo'] );
		$this->assertEquals( 70.0, $scores['avg_readability'] );
	}

	// ── Site Summary ─────────────────────────────────────────────────────────

	public function test_get_site_summary_structure(): void {
		$summary = $this->reporter->get_site_summary();

		$this->assertArrayHasKey( 'version', $summary );
		$this->assertArrayHasKey( 'features', $summary );
		$this->assertArrayHasKey( 'scores', $summary );
		$this->assertArrayHasKey( 'post_count', $summary );
		$this->assertArrayHasKey( 'last_analysis', $summary );
		$this->assertArrayHasKey( 'queue_pending', $summary );
	}

	// ── Pending Queue ────────────────────────────────────────────────────────

	public function test_get_pending_queue_count_returns_zero_when_empty(): void {
		$this->assertEquals( 0, $this->reporter->get_pending_queue_count() );
	}

	public function test_get_pending_queue_count_counts_pending_only(): void {
		global $si_test_state;
		$si_test_state['options']['search_intel_bg_queue'] = [
			[ 'id' => 'a', 'status' => 'pending' ],
			[ 'id' => 'b', 'status' => 'completed' ],
			[ 'id' => 'c', 'status' => 'pending' ],
			[ 'id' => 'd', 'status' => 'failed' ],
		];

		$this->assertEquals( 2, $this->reporter->get_pending_queue_count() );
	}

	// ── Callback Handler ─────────────────────────────────────────────────────

	public function test_handle_callback_ignores_non_search_intel_actions(): void {
		$result  = [ 'data' => null ];
		$message = [ 'action' => 'other_plugin_action' ];

		$out = $this->reporter->handle_callback( $result, $message );

		$this->assertNull( $out['data'] );
	}

	public function test_handle_callback_returns_summary(): void {
		$result  = [];
		$message = [ 'action' => 'search_intel_get_summary' ];

		$out = $this->reporter->handle_callback( $result, $message );

		$this->assertEquals( 'success', $out['result'] );
		$this->assertArrayHasKey( 'version', $out['data'] );
		$this->assertArrayHasKey( 'features', $out['data'] );
	}

	// ── Sync Data ────────────────────────────────────────────────────────────

	public function test_inject_sync_data_adds_search_intel_key(): void {
		$data = [ 'existing_key' => 'value' ];
		$out  = $this->reporter->inject_sync_data( $data );

		$this->assertArrayHasKey( 'existing_key', $out );
		$this->assertArrayHasKey( 'search_intel', $out );
		$this->assertArrayHasKey( 'version', $out['search_intel'] );
	}
}
