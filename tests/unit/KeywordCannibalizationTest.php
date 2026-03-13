<?php

use PHPUnit\Framework\TestCase;
use Search_Intel\Keyword_Cannibalization;

class KeywordCannibalizationTest extends TestCase {

    private function make( array $overrides = [] ): Keyword_Cannibalization {
        $defaults = [
            'enable_cannibalization' => false, // Disabled to prevent hooks in constructor.
        ];
        return new Keyword_Cannibalization( array_merge( $defaults, $overrides ) );
    }

    protected function setUp(): void {
        global $si_test_state;
        $si_test_state['post_meta'] = [];
        $si_test_state['options']   = [];
        $si_test_state['transients'] = [];
        $si_test_state['posts']     = [];
    }

    // ── Keyword normalization ───────────────────────────────────────────

    public function test_normalize_keyword_lowercases(): void {
        $kc = $this->make();
        $this->assertSame( 'wordpress seo', $kc->normalize_keyword( 'WordPress SEO' ) );
    }

    public function test_normalize_keyword_trims(): void {
        $kc = $this->make();
        $this->assertSame( 'seo plugin', $kc->normalize_keyword( '  seo plugin  ' ) );
    }

    public function test_normalize_keyword_collapses_spaces(): void {
        $kc = $this->make();
        $this->assertSame( 'seo plugin', $kc->normalize_keyword( 'seo   plugin' ) );
    }

    public function test_normalize_keyword_empty(): void {
        $kc = $this->make();
        $this->assertSame( '', $kc->normalize_keyword( '' ) );
    }

    // ── Action suggestions ──────────────────────────────────────────────

    public function test_suggest_action_redirect_clear_winner(): void {
        $kc    = $this->make();
        $posts = [
            [ 'id' => 1, 'title' => 'Best SEO Guide', 'url' => '/seo-guide', 'seo_score' => 90 ],
            [ 'id' => 2, 'title' => 'SEO Tips', 'url' => '/seo-tips', 'seo_score' => 60 ],
        ];

        $result = $kc->suggest_action( $posts );
        $this->assertSame( 'redirect', $result['action'] );
        $this->assertSame( 1, $result['primary_post_id'] );
    }

    public function test_suggest_action_differentiate_close_scores(): void {
        $kc    = $this->make();
        $posts = [
            [ 'id' => 1, 'title' => 'SEO Guide A', 'url' => '/a', 'seo_score' => 75 ],
            [ 'id' => 2, 'title' => 'SEO Guide B', 'url' => '/b', 'seo_score' => 70 ],
        ];

        $result = $kc->suggest_action( $posts );
        $this->assertSame( 'differentiate', $result['action'] );
    }

    public function test_suggest_action_merge_many_posts(): void {
        $kc    = $this->make();
        $posts = [
            [ 'id' => 1, 'title' => 'SEO Guide A', 'url' => '/a', 'seo_score' => 80 ],
            [ 'id' => 2, 'title' => 'SEO Guide B', 'url' => '/b', 'seo_score' => 75 ],
            [ 'id' => 3, 'title' => 'SEO Guide C', 'url' => '/c', 'seo_score' => 72 ],
        ];

        $result = $kc->suggest_action( $posts );
        $this->assertSame( 'merge', $result['action'] );
        $this->assertSame( 1, $result['primary_post_id'] );
    }

    public function test_suggest_action_deoptimize_moderate_gap(): void {
        $kc    = $this->make();
        $posts = [
            [ 'id' => 1, 'title' => 'SEO Guide A', 'url' => '/a', 'seo_score' => 80 ],
            [ 'id' => 2, 'title' => 'SEO Guide B', 'url' => '/b', 'seo_score' => 65 ],
        ];

        $result = $kc->suggest_action( $posts );
        $this->assertSame( 'de-optimize', $result['action'] );
    }

    public function test_suggest_action_none_single_post(): void {
        $kc    = $this->make();
        $posts = [
            [ 'id' => 1, 'title' => 'Only Post', 'url' => '/only', 'seo_score' => 80 ],
        ];

        $result = $kc->suggest_action( $posts );
        $this->assertSame( 'none', $result['action'] );
    }

    // ── Report generation ───────────────────────────────────────────────

    public function test_generate_report_empty_when_no_duplicates(): void {
        global $si_test_state;
        $si_test_state['posts'] = [];

        $kc     = $this->make();
        $report = $kc->generate_report( false );

        $this->assertIsArray( $report );
        $this->assertEmpty( $report );
    }

    public function test_get_summary_structure(): void {
        global $si_test_state;
        $si_test_state['posts'] = [];

        $kc      = $this->make();
        $summary = $kc->get_summary();

        $this->assertArrayHasKey( 'total_keywords', $summary );
        $this->assertArrayHasKey( 'total_posts_affected', $summary );
        $this->assertArrayHasKey( 'groups', $summary );
        $this->assertSame( 0, $summary['total_keywords'] );
    }

    // ── Link relationships ──────────────────────────────────────────────

    public function test_get_link_relationships_empty_without_citation_graph(): void {
        $kc    = $this->make();
        $links = $kc->get_link_relationships( [ 1, 2, 3 ] );

        $this->assertIsArray( $links );
        // May be empty if Citation Graph not available — just verify it doesn't crash.
    }

    // ── Cache ───────────────────────────────────────────────────────────

    public function test_invalidate_cache_deletes_transient(): void {
        global $si_test_state;
        $si_test_state['transients'] = [
            'search_intel_cannibalization_report' => [ 'cached' => true ],
        ];

        $kc = $this->make();
        $kc->invalidate_cache();

        // Transient should be removed.
        $this->assertArrayNotHasKey(
            'search_intel_cannibalization_report',
            $si_test_state['transients'] ?? []
        );
    }
}
