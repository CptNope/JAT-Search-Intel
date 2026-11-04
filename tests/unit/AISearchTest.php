<?php
namespace JAT_Search_Intel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use JAT_Search_Intel\AI_Search;
use JAT_Search_Intel\Core;
use JAT_Search_Intel\Markdown;

/**
 * Markdown endpoints, AI crawler rules and AI traffic analytics.
 */
class AISearchTest extends TestCase {

	protected function setUp(): void {
		global $si_test_state;
		$si_test_state['post_meta']  = [];
		$si_test_state['options']    = [];
		$si_test_state['actions']    = [];
		$si_test_state['filters']    = [];
		$si_test_state['transients'] = [];
	}

	private function make( array $overrides = [] ): AI_Search {
		return new AI_Search( array_merge( Core::get_default_settings(), $overrides ) );
	}

	// ── Markdown conversion ─────────────────────────────────────────────

	public function test_markdown_converts_common_elements(): void {
		$html = '<h2>Intro</h2><p>Hello <strong>bold</strong> and <em>soft</em> with <a href="https://x.test">a link</a>.</p>'
			. '<ul><li>One</li><li>Two<ul><li>Nested</li></ul></li></ul><ol><li>First</li><li>Second</li></ol>'
			. '<blockquote><p>Quoted</p></blockquote><pre><code>$a = 1;</code></pre>'
			. '<img src="https://x.test/i.jpg" alt="Pic"><script>alert(1)</script>';

		$md = Markdown::from_html( $html );

		$this->assertStringContainsString( '## Intro', $md );
		$this->assertStringContainsString( 'Hello **bold** and *soft* with [a link](https://x.test).', $md );
		$this->assertStringContainsString( "- One\n- Two\n  - Nested", $md );
		$this->assertStringContainsString( "1. First\n2. Second", $md );
		$this->assertStringContainsString( '> Quoted', $md );
		$this->assertStringContainsString( "```\n\$a = 1;\n```", $md );
		$this->assertStringContainsString( '![Pic](https://x.test/i.jpg)', $md );
		$this->assertStringNotContainsString( 'alert', $md );
	}

	public function test_markdown_converts_tables(): void {
		$md = Markdown::from_html( '<table><tr><th>Plan</th><th>Price</th></tr><tr><td>Pro</td><td>$5</td></tr></table>' );
		$this->assertStringContainsString( "| Plan | Price |\n| --- | --- |\n| Pro | \$5 |", $md );
	}

	public function test_markdown_handles_empty_input(): void {
		$this->assertSame( '', Markdown::from_html( '   ' ) );
	}

	// ── Crawler identification and rules ───────────────────────────────

	public function test_match_bot_identifies_ai_crawlers_but_not_browsers(): void {
		$this->assertSame( 'GPTBot', AI_Search::match_bot( 'Mozilla/5.0 AppleWebKit/537.36; compatible; GPTBot/1.2; +https://openai.com/gptbot' ) );
		$this->assertSame( 'Claude-User', AI_Search::match_bot( 'Mozilla/5.0 (compatible; Claude-User/1.0)' ) );
		$this->assertSame( '', AI_Search::match_bot( 'Mozilla/5.0 (Macintosh) Safari/605.1.15' ) );
		$this->assertSame( '', AI_Search::match_bot( 'Google-Extended' ), 'robots.txt-only tokens are never matched' );
	}

	public function test_match_referrer_by_host_and_utm(): void {
		$this->assertSame( 'ChatGPT', AI_Search::match_referrer( 'https://chatgpt.com/' ) );
		$this->assertSame( 'Perplexity', AI_Search::match_referrer( 'https://www.perplexity.ai/search?q=x' ) );
		$this->assertSame( 'ChatGPT', AI_Search::match_referrer( '', 'chatgpt.com' ) );
		$this->assertSame( 'Claude', AI_Search::match_referrer( '', 'claude' ) );
		$this->assertSame( '', AI_Search::match_referrer( 'https://notchatgpt.com.evil.test/' ) );
		$this->assertSame( '', AI_Search::match_referrer( 'https://www.google.com/' ) );
	}

	public function test_robots_txt_gets_disallow_groups_for_blocked_bots(): void {
		$ai  = $this->make( [ 'ai_crawler_rules' => [ 'GPTBot' => 'block', 'OAI-SearchBot' => 'allow', 'CCBot' => 'block' ] ] );
		$out = $ai->filter_robots_txt( "User-agent: *\nDisallow: /wp-admin/\n", true );

		$this->assertStringContainsString( "User-agent: GPTBot\nDisallow: /", $out );
		$this->assertStringContainsString( "User-agent: CCBot\nDisallow: /", $out );
		$this->assertStringNotContainsString( 'OAI-SearchBot', $out );
		$this->assertStringStartsWith( "User-agent: *\nDisallow: /wp-admin/", $out );
	}

	public function test_robots_txt_untouched_for_private_sites_or_no_blocks(): void {
		$ai = $this->make( [ 'ai_crawler_rules' => [ 'GPTBot' => 'block' ] ] );
		$this->assertSame( 'x', $ai->filter_robots_txt( 'x', false ) );

		global $si_test_state;
		$si_test_state['filters'] = [];
		$this->make();
		$this->assertArrayNotHasKey( 'robots_txt', $si_test_state['filters'] );
	}

	// ── Analytics storage ───────────────────────────────────────────────

	public function test_record_and_summary(): void {
		AI_Search::record( 'crawlers', 'GPTBot', '/a/' );
		AI_Search::record( 'crawlers', 'GPTBot', '/a/' );
		AI_Search::record( 'crawlers', 'ClaudeBot', '/b/' );
		AI_Search::record( 'referrals', 'ChatGPT', '/pricing/' );

		$crawlers = AI_Search::summary( 'crawlers' );
		$this->assertSame( 3, $crawlers['total'] );
		$this->assertSame( [ 'GPTBot' => 2, 'ClaudeBot' => 1 ], $crawlers['by_label'] );
		$this->assertSame( [ '/a/' => 2 ], $crawlers['pages']['GPTBot'] );

		$referrals = AI_Search::summary( 'referrals' );
		$this->assertSame( 1, $referrals['total'] );
	}

	public function test_old_days_are_pruned(): void {
		global $si_test_state;
		$si_test_state['options'][ AI_Search::TRAFFIC_OPTION ] = [
			'crawlers' => [ 'days' => [ '2000-01-01' => [ 'GPTBot' => 9 ] ], 'pages' => [] ],
		];
		AI_Search::record( 'crawlers', 'GPTBot', '/' );

		$this->assertArrayNotHasKey( '2000-01-01', $si_test_state['options'][ AI_Search::TRAFFIC_OPTION ]['crawlers']['days'] );
	}

	public function test_tracking_hook_only_when_enabled(): void {
		global $si_test_state;
		$this->make( [ 'enable_ai_crawler_log' => false, 'enable_markdown_endpoints' => false ] );
		$this->assertArrayNotHasKey( 'template_redirect', $si_test_state['actions'] );

		$this->make( [ 'enable_ai_crawler_log' => true ] );
		$this->assertArrayHasKey( 'template_redirect', $si_test_state['actions'] );
	}
}
