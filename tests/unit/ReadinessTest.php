<?php
namespace JAT_Search_Intel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use JAT_Search_Intel\Core;
use JAT_Search_Intel\Readiness;

/**
 * AI Search Readiness audit: robots parsing, scoring and one-click fixes.
 */
class ReadinessTest extends TestCase {

	protected function setUp(): void {
		global $si_test_state;
		$si_test_state['options'] = [];
		$si_test_state['filters'] = [];
		$si_test_state['actions'] = [];
	}

	private function make( array $overrides = [] ): Readiness {
		return new Readiness( array_merge( Core::get_default_settings(), $overrides ) );
	}

	public function test_parse_robots_groups_agents(): void {
		$groups = Readiness::parse_robots( "User-agent: GPTBot\nUser-agent: CCBot\nDisallow: /\n\nUser-agent: *\nDisallow: /wp-admin/ # admin\nAllow: /wp-admin/admin-ajax.php\n" );

		$this->assertSame( [ '/' ], $groups['gptbot'] );
		$this->assertSame( [ '/' ], $groups['ccbot'] );
		$this->assertSame( [ '/wp-admin/' ], $groups['*'] );
	}

	public function test_blocking_training_bots_does_not_flag_search_bots(): void {
		$blocked = $this->make()->blocked_search_bots( "User-agent: GPTBot\nDisallow: /\n\nUser-agent: *\nDisallow: /wp-admin/\n" );
		$this->assertSame( [], $blocked );
	}

	public function test_wildcard_block_flags_every_search_bot_without_own_group(): void {
		$blocked = $this->make()->blocked_search_bots( "User-agent: Googlebot\nAllow: /\n\nUser-agent: *\nDisallow: /\n" );

		$this->assertNotContains( 'Googlebot', $blocked, 'A specific group overrides the wildcard' );
		$this->assertContains( 'Bingbot', $blocked );
		$this->assertContains( 'OAI-SearchBot', $blocked );
	}

	public function test_specific_block_is_detected(): void {
		$this->assertSame( [ 'PerplexityBot' ], $this->make()->blocked_search_bots( "User-agent: PerplexityBot\nDisallow: /\n" ) );
	}

	public function test_score_weights_levels(): void {
		$checks = [
			[ 'level' => 'critical', 'status' => 'pass' ],
			[ 'level' => 'critical', 'status' => 'fail' ],
			[ 'level' => 'info', 'status' => 'fail' ],
		];
		$this->assertSame( 50, Readiness::score( $checks ) );

		$checks[] = [ 'level' => 'helpful', 'status' => 'warn' ]; // 1.5 of 3.
		$this->assertSame( 50, Readiness::score( $checks ) ); // (10+1.5)/23 = 50.
		$this->assertSame( 100, Readiness::score( [] ) );
	}

	public function test_fix_enables_indexnow_with_key(): void {
		global $si_test_state;
		$si_test_state['options']['jat_search_intel_settings'] = [ 'enable_indexnow' => false, 'indexnow_api_key' => '' ];

		$this->assertTrue( Readiness::apply_fix( 'enable-indexnow' ) );

		$saved = $si_test_state['options']['jat_search_intel_settings'];
		$this->assertTrue( $saved['enable_indexnow'] );
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{32}$/', $saved['indexnow_api_key'] );
	}

	public function test_fix_allow_ai_search_only_touches_search_and_user_bots(): void {
		global $si_test_state;
		$si_test_state['options']['jat_search_intel_settings'] = [
			'ai_crawler_rules' => [ 'OAI-SearchBot' => 'block', 'ChatGPT-User' => 'block', 'GPTBot' => 'block' ],
		];

		Readiness::apply_fix( 'allow-ai-search' );

		$rules = $si_test_state['options']['jat_search_intel_settings']['ai_crawler_rules'];
		$this->assertSame( 'allow', $rules['OAI-SearchBot'] );
		$this->assertSame( 'allow', $rules['ChatGPT-User'] );
		$this->assertSame( 'block', $rules['GPTBot'], 'Training opt-outs are kept' );
	}

	public function test_unknown_fix_changes_nothing(): void {
		global $si_test_state;
		$si_test_state['options']['jat_search_intel_settings'] = [ 'x' => 1 ];
		$this->assertFalse( Readiness::apply_fix( 'drop-tables' ) );
		$this->assertSame( [ 'x' => 1 ], $si_test_state['options']['jat_search_intel_settings'] );
	}
}
