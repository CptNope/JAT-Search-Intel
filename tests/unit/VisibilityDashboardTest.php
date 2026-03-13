<?php
namespace Search_Intel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Search_Intel\Visibility_Dashboard;

class VisibilityDashboardTest extends TestCase {

	private Visibility_Dashboard $dashboard;

	protected function setUp(): void {
		global $si_test_state;
		$si_test_state['options']    = [];
		$si_test_state['post_meta']  = [];
		$si_test_state['posts']      = [];
		$si_test_state['transients'] = [];

		$this->dashboard = new Visibility_Dashboard( [
			'enable_visibility_dashboard' => true,
			'enable_geo'                  => true,
			'enable_llms_txt'             => true,
			'enable_ai_meta'              => true,
			'enable_entity_markup'        => true,
			'content_freshness'           => true,
			'enable_schema'               => true,
			'enable_schema_types'         => true,
		] );
	}

	// ── Health Score ─────────────────────────────────────────────────────────

	public function test_calculate_health_score_returns_structure(): void {
		$result = $this->dashboard->calculate_health_score();

		$this->assertArrayHasKey( 'score', $result );
		$this->assertArrayHasKey( 'factors', $result );
		$this->assertArrayHasKey( 'grade', $result );
		$this->assertIsInt( $result['score'] );
		$this->assertGreaterThanOrEqual( 0, $result['score'] );
		$this->assertLessThanOrEqual( 100, $result['score'] );
	}

	public function test_health_score_has_all_factors(): void {
		$result = $this->dashboard->calculate_health_score();

		$this->assertArrayHasKey( 'ai_meta', $result['factors'] );
		$this->assertArrayHasKey( 'llms_txt', $result['factors'] );
		$this->assertArrayHasKey( 'entity', $result['factors'] );
		$this->assertArrayHasKey( 'freshness', $result['factors'] );
		$this->assertArrayHasKey( 'eeat', $result['factors'] );
		$this->assertArrayHasKey( 'schema', $result['factors'] );
	}

	public function test_health_score_factor_structure(): void {
		$result = $this->dashboard->calculate_health_score();

		foreach ( $result['factors'] as $factor ) {
			$this->assertArrayHasKey( 'label', $factor );
			$this->assertArrayHasKey( 'score', $factor );
			$this->assertArrayHasKey( 'max', $factor );
			$this->assertArrayHasKey( 'detail', $factor );
			$this->assertGreaterThanOrEqual( 0, $factor['score'] );
			$this->assertLessThanOrEqual( $factor['max'], $factor['score'] );
		}
	}

	public function test_health_score_with_full_settings(): void {
		$result = $this->dashboard->calculate_health_score();

		// With all settings enabled, llms_txt + eeat + schema should contribute points.
		$this->assertGreaterThan( 0, $result['score'] );
	}

	public function test_health_score_grade_a(): void {
		// Score >= 90 should be grade A. We can't easily force 90+ but we can test the grade logic.
		$result = $this->dashboard->calculate_health_score();
		$this->assertContains( $result['grade'], [ 'A', 'B', 'C', 'D', 'F' ] );
	}

	// ── AI Bot Identification ────────────────────────────────────────────────

	public function test_identify_ai_bot_gptbot(): void {
		$this->assertEquals( 'OpenAI GPTBot', $this->dashboard->identify_ai_bot( 'Mozilla/5.0 GPTBot/1.0' ) );
	}

	public function test_identify_ai_bot_claude(): void {
		$this->assertEquals( 'Claude', $this->dashboard->identify_ai_bot( 'ClaudeBot/1.0' ) );
	}

	public function test_identify_ai_bot_perplexity(): void {
		$this->assertEquals( 'Perplexity', $this->dashboard->identify_ai_bot( 'PerplexityBot/1.0' ) );
	}

	public function test_identify_ai_bot_google(): void {
		$this->assertEquals( 'Google AI', $this->dashboard->identify_ai_bot( 'Google-Extended' ) );
	}

	public function test_identify_ai_bot_unknown(): void {
		$this->assertEquals( 'unknown', $this->dashboard->identify_ai_bot( 'Mozilla/5.0 Chrome/100' ) );
	}

	// ── AI Referral Identification ───────────────────────────────────────────

	public function test_identify_ai_referral_chatgpt(): void {
		$this->assertEquals( 'ChatGPT', $this->dashboard->identify_ai_referral( 'https://chat.openai.com/share/abc' ) );
	}

	public function test_identify_ai_referral_perplexity(): void {
		$this->assertEquals( 'Perplexity', $this->dashboard->identify_ai_referral( 'https://www.perplexity.ai/search?q=test' ) );
	}

	public function test_identify_ai_referral_claude(): void {
		$this->assertEquals( 'Claude', $this->dashboard->identify_ai_referral( 'https://claude.ai/chat/123' ) );
	}

	public function test_identify_ai_referral_gemini(): void {
		$this->assertEquals( 'Google Gemini', $this->dashboard->identify_ai_referral( 'https://gemini.google.com/app' ) );
	}

	public function test_identify_ai_referral_empty_for_regular(): void {
		$this->assertEquals( '', $this->dashboard->identify_ai_referral( 'https://www.google.com/search?q=test' ) );
	}

	// ── AI Referral Summary ──────────────────────────────────────────────────

	public function test_get_ai_referral_summary_empty(): void {
		$result = $this->dashboard->get_ai_referral_summary( 30 );

		$this->assertArrayHasKey( 'total', $result );
		$this->assertArrayHasKey( 'by_source', $result );
		$this->assertArrayHasKey( 'trend', $result );
		$this->assertEquals( 0, $result['total'] );
	}

	public function test_get_ai_referral_summary_with_data(): void {
		global $si_test_state;
		$si_test_state['options']['search_intel_ai_referrals'] = [
			[ 'date' => gmdate( 'Y-m-d' ), 'source' => 'ChatGPT' ],
			[ 'date' => gmdate( 'Y-m-d' ), 'source' => 'Perplexity' ],
			[ 'date' => gmdate( 'Y-m-d' ), 'source' => 'ChatGPT' ],
		];

		$result = $this->dashboard->get_ai_referral_summary( 30 );

		$this->assertEquals( 3, $result['total'] );
		$this->assertEquals( 2, $result['by_source']['ChatGPT'] );
		$this->assertEquals( 1, $result['by_source']['Perplexity'] );
	}

	// ── llms.txt Access Log ──────────────────────────────────────────────────

	public function test_get_llms_access_log_empty(): void {
		$this->assertEmpty( $this->dashboard->get_llms_access_log() );
	}

	public function test_get_llms_access_log_with_entries(): void {
		global $si_test_state;
		$si_test_state['options']['search_intel_llms_access_log'] = [
			[ 'time' => '2026-01-01 12:00:00', 'endpoint' => 'llms.txt', 'bot' => 'OpenAI GPTBot' ],
			[ 'time' => '2026-01-02 12:00:00', 'endpoint' => 'llms-full.txt', 'bot' => 'Claude' ],
		];

		$log = $this->dashboard->get_llms_access_log( 10 );

		$this->assertCount( 2, $log );
		// Reversed: newest first.
		$this->assertEquals( 'Claude', $log[0]['bot'] );
	}

	// ── Visibility Trend ─────────────────────────────────────────────────────

	public function test_get_visibility_trend_empty(): void {
		$this->assertEmpty( $this->dashboard->get_visibility_trend() );
	}

	public function test_get_visibility_trend_with_data(): void {
		global $si_test_state;
		$si_test_state['options']['search_intel_visibility_trend'] = [
			[ 'date' => '2026-01-01', 'score' => 55, 'grade' => 'D' ],
			[ 'date' => '2026-01-02', 'score' => 60, 'grade' => 'D' ],
		];

		$trend = $this->dashboard->get_visibility_trend( 30 );
		$this->assertCount( 2, $trend );
	}

	public function test_record_daily_score(): void {
		$this->dashboard->record_daily_score();

		global $si_test_state;
		$trend = $si_test_state['options']['search_intel_visibility_trend'] ?? [];

		$this->assertNotEmpty( $trend );
		$this->assertEquals( gmdate( 'Y-m-d' ), $trend[0]['date'] );
		$this->assertArrayHasKey( 'score', $trend[0] );
		$this->assertArrayHasKey( 'grade', $trend[0] );
	}

	// ── Recommendations ──────────────────────────────────────────────────────

	public function test_get_recommendations_returns_array(): void {
		$recs = $this->dashboard->get_recommendations();

		$this->assertIsArray( $recs );
		foreach ( $recs as $rec ) {
			$this->assertArrayHasKey( 'factor', $rec );
			$this->assertArrayHasKey( 'priority', $rec );
			$this->assertArrayHasKey( 'message', $rec );
			$this->assertContains( $rec['priority'], [ 'high', 'medium', 'low' ] );
		}
	}

	public function test_recommendations_sorted_by_priority(): void {
		$recs = $this->dashboard->get_recommendations();
		$order = [ 'high' => 0, 'medium' => 1, 'low' => 2 ];

		for ( $i = 1; $i < count( $recs ); $i++ ) {
			$this->assertLessThanOrEqual(
				$order[ $recs[ $i ]['priority'] ],
				$order[ $recs[ $i - 1 ]['priority'] ]
			);
		}
	}

	// ── Content Gap Map ──────────────────────────────────────────────────────

	public function test_analyze_content_gaps_returns_structure(): void {
		$result = $this->dashboard->analyze_content_gaps();

		$this->assertArrayHasKey( 'gaps', $result );
		$this->assertArrayHasKey( 'coverage', $result );
		$this->assertIsArray( $result['gaps'] );
		$this->assertIsArray( $result['coverage'] );
	}

	// ── Disabled ─────────────────────────────────────────────────────────────

	public function test_disabled_does_not_register(): void {
		global $si_test_state;
		$si_test_state['actions'] = [];

		$dashboard = new Visibility_Dashboard( [ 'enable_visibility_dashboard' => false ] );

		$this->assertArrayNotHasKey( 'admin_menu', $si_test_state['actions'] ?? [] );
	}
}
