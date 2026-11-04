<?php

use PHPUnit\Framework\TestCase;
use JAT_Search_Intel\Advanced_Linking;

class AdvancedLinkingTest extends TestCase {

    private function make( array $overrides = [] ): Advanced_Linking {
        $defaults = [
            'enable_advanced_linking' => false, // Disabled to prevent hooks in constructor.
        ];
        return new Advanced_Linking( array_merge( $defaults, $overrides ) );
    }

    protected function setUp(): void {
        global $si_test_state;
        $si_test_state['post_meta'] = [];
        $si_test_state['options']   = [];
        $si_test_state['transients'] = [];
        $si_test_state['posts']     = [];
    }

    // ── Keyword extraction ──────────────────────────────────────────────

    public function test_extract_keywords_basic(): void {
        $al = $this->make();
        $keywords = $al->extract_keywords( 'WordPress SEO plugin for optimizing search engine rankings' );

        $this->assertIsArray( $keywords );
        $this->assertNotEmpty( $keywords );
        $this->assertContains( 'wordpress', $keywords );
        $this->assertContains( 'plugin', $keywords );
    }

    public function test_extract_keywords_removes_stop_words(): void {
        $al = $this->make();
        $keywords = $al->extract_keywords( 'The best way to improve your SEO is with this plugin' );

        $this->assertNotContains( 'the', $keywords );
        $this->assertNotContains( 'to', $keywords );
        $this->assertNotContains( 'is', $keywords );
        $this->assertNotContains( 'with', $keywords );
    }

    public function test_extract_keywords_removes_short_words(): void {
        $al = $this->make();
        $keywords = $al->extract_keywords( 'AI is an ok tool' );

        // Words shorter than 3 chars should be excluded.
        $this->assertNotContains( 'ai', $keywords );
        $this->assertNotContains( 'is', $keywords );
        $this->assertNotContains( 'an', $keywords );
        $this->assertNotContains( 'ok', $keywords );
    }

    public function test_extract_keywords_limits_to_30(): void {
        $al = $this->make();
        $long_text = implode( ' ', array_map(
            fn( $i ) => "keyword{$i} uniqueword{$i} specialterm{$i}",
            range( 1, 20 )
        ) );

        $keywords = $al->extract_keywords( $long_text );
        $this->assertLessThanOrEqual( 30, count( $keywords ) );
    }

    public function test_extract_keywords_empty_text(): void {
        $al = $this->make();
        $keywords = $al->extract_keywords( '' );

        $this->assertIsArray( $keywords );
        $this->assertEmpty( $keywords );
    }

    public function test_extract_keywords_orders_by_frequency(): void {
        $al = $this->make();
        $keywords = $al->extract_keywords( 'wordpress wordpress wordpress seo seo plugin' );

        // 'wordpress' appears 3x so should be first.
        $this->assertSame( 'wordpress', $keywords[0] );
    }

    // ── Orphaned content ────────────────────────────────────────────────

    public function test_get_orphaned_posts_returns_array(): void {
        $al = $this->make();
        $orphans = $al->get_orphaned_posts();

        $this->assertIsArray( $orphans );
    }

    // ── Suggestions ─────────────────────────────────────────────────────

    public function test_get_suggestions_returns_array(): void {
        global $si_test_state;
        $si_test_state['posts'] = [];

        $al = $this->make();
        $suggestions = $al->get_suggestions( 1, 'Some content about WordPress SEO' );

        $this->assertIsArray( $suggestions );
    }

    // ── Equity flow ─────────────────────────────────────────────────────

    public function test_get_equity_flow_structure(): void {
        global $si_test_state;
        $si_test_state['posts'] = [];

        $al   = $this->make();
        $flow = $al->get_equity_flow();

        $this->assertArrayHasKey( 'nodes', $flow );
        $this->assertArrayHasKey( 'edges', $flow );
        $this->assertIsArray( $flow['nodes'] );
        $this->assertIsArray( $flow['edges'] );
    }

    // ── Broken link detection ───────────────────────────────────────────

    public function test_check_internal_links_no_post(): void {
        $al = $this->make();
        $broken = $al->check_internal_links( 999 );

        $this->assertIsArray( $broken );
        $this->assertEmpty( $broken );
    }

    public function test_check_internal_links_clean_post(): void {
        global $si_test_state;
        $si_test_state['posts'][1] = [
            'ID'           => 1,
            'post_title'   => 'Test Post',
            'post_content' => '<p>No links here</p>',
            'post_status'  => 'publish',
            'post_type'    => 'post',
        ];

        $al = $this->make();
        $broken = $al->check_internal_links( 1 );

        $this->assertIsArray( $broken );
        $this->assertEmpty( $broken );
    }

    // ── Run broken link check ───────────────────────────────────────────

    public function test_run_broken_link_check_stats_structure(): void {
        global $si_test_state;
        $si_test_state['posts'] = [];

        $al    = $this->make();
        $stats = $al->run_broken_link_check();

        $this->assertArrayHasKey( 'checked', $stats );
        $this->assertArrayHasKey( 'posts_with_broken', $stats );
        $this->assertArrayHasKey( 'total_broken', $stats );
        $this->assertSame( 0, $stats['checked'] );
    }
}
