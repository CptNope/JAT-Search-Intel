<?php
/**
 * Integration tests for GEO Manager output.
 * Tests AI meta tags, entity markup, freshness signals, AI crawl directives, and GEO scoring.
 */

use PHPUnit\Framework\TestCase;
use Search_Intel\GEO_Manager;
use Search_Intel\Core;

class GEOOutputTest extends TestCase {

    private function make_geo( array $overrides = [] ): GEO_Manager {
        $settings = array_merge( Core::get_default_settings(), $overrides );
        return new GEO_Manager( $settings );
    }

    protected function setUp(): void {
        global $si_test_state;
        $si_test_state['post_meta']  = [];
        $si_test_state['user_meta']  = [];
        $si_test_state['options']    = [];
        $si_test_state['actions']    = [];
        $si_test_state['filters']    = [];
        $si_test_state['transients'] = [];
        $si_test_state['posts']      = [
            1 => [
                'ID'                => 1,
                'post_title'        => 'GEO Integration Post',
                'post_content'      => '<p>This is detailed content about generative engine optimization covering AI search and LLM indexing.</p>',
                'post_excerpt'      => '',
                'post_author'       => 1,
                'post_type'         => 'post',
                'post_status'       => 'publish',
                'post_date_gmt'     => '2026-01-15 10:00:00',
                'post_modified_gmt' => '2026-02-20 14:30:00',
            ],
        ];
    }

    // ── AI meta tags output ─────────────────────────────────────────────

    public function test_ai_meta_renders_ai_summary(): void {
        global $si_test_state;
        $si_test_state['post_meta'][1]['_search_intel_ai_summary'] = 'This article covers GEO optimization.';

        $mgr = $this->make_geo();
        ob_start();
        $mgr->render_ai_meta();
        $output = ob_get_clean();

        $this->assertStringContainsString( '<meta name="ai-summary"', $output );
        $this->assertStringContainsString( 'This article covers GEO optimization.', $output );
    }

    public function test_ai_meta_renders_content_type(): void {
        $mgr = $this->make_geo();
        ob_start();
        $mgr->render_ai_meta();
        $output = ob_get_clean();

        $this->assertStringContainsString( '<meta name="content-type"', $output );
    }

    public function test_ai_meta_renders_modified_time(): void {
        $mgr = $this->make_geo();
        ob_start();
        $mgr->render_ai_meta();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'article:modified_time', $output );
    }

    public function test_ai_meta_renders_content_intent(): void {
        global $si_test_state;
        $si_test_state['post_meta'][1]['_search_intel_content_intent'] = 'educational';

        $mgr = $this->make_geo();
        ob_start();
        $mgr->render_ai_meta();
        $output = ob_get_clean();

        $this->assertStringContainsString( '<meta name="content-intent"', $output );
        $this->assertStringContainsString( 'educational', $output );
    }

    public function test_ai_meta_renders_audience(): void {
        global $si_test_state;
        $si_test_state['post_meta'][1]['_search_intel_audience'] = 'developers';

        $mgr = $this->make_geo();
        ob_start();
        $mgr->render_ai_meta();
        $output = ob_get_clean();

        $this->assertStringContainsString( '<meta name="target-audience"', $output );
        $this->assertStringContainsString( 'developers', $output );
    }

    public function test_ai_meta_renders_geo_placename(): void {
        global $si_test_state;
        $si_test_state['post_meta'][1]['_search_intel_local_relevance'] = '1';
        $si_test_state['post_meta'][1]['_search_intel_local_location']  = 'Austin, TX';

        $mgr = $this->make_geo();
        ob_start();
        $mgr->render_ai_meta();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'geo.placename', $output );
        $this->assertStringContainsString( 'Austin, TX', $output );
    }

    public function test_ai_meta_renders_keywords(): void {
        global $si_test_state;
        $si_test_state['post_meta'][1]['_search_intel_topics'] = [ 'SEO', 'AEO', 'GEO' ];

        $mgr = $this->make_geo();
        ob_start();
        $mgr->render_ai_meta();
        $output = ob_get_clean();

        $this->assertStringContainsString( '<meta name="keywords"', $output );
        $this->assertStringContainsString( 'SEO', $output );
    }

    // ── Freshness signals ───────────────────────────────────────────────

    public function test_freshness_meta_renders(): void {
        $mgr = $this->make_geo();
        ob_start();
        $mgr->render_freshness_meta();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'content-freshness', $output );
    }

    public function test_freshness_includes_last_modified(): void {
        $mgr = $this->make_geo();
        ob_start();
        $mgr->render_freshness_meta();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'data-last-modified', $output );
    }

    public function test_freshness_includes_fact_check_date(): void {
        global $si_test_state;
        $si_test_state['post_meta'][1]['_search_intel_fact_checked'] = '2026-03-01';

        $mgr = $this->make_geo();
        ob_start();
        $mgr->render_freshness_meta();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'fact-check-date', $output );
        $this->assertStringContainsString( '2026-03-01', $output );
    }

    // ── Entity markup ───────────────────────────────────────────────────

    public function test_entity_markup_wraps_entity_name(): void {
        global $si_test_state;
        $si_test_state['post_meta'][1]['_search_intel_entities'] = [
            [ 'name' => 'Google', 'type' => 'Organization', 'url' => 'https://google.com' ],
        ];

        $mgr     = $this->make_geo();
        $content = '<p>Google is a major search engine company.</p>';
        $result  = $mgr->enhance_content_entities( $content );

        $this->assertStringContainsString( 'itemtype="https://schema.org/Organization"', $result );
        $this->assertStringContainsString( 'Google', $result );
    }

    public function test_entity_markup_adds_link_when_url_provided(): void {
        global $si_test_state;
        $si_test_state['post_meta'][1]['_search_intel_entities'] = [
            [ 'name' => 'Google', 'type' => 'Organization', 'url' => 'https://google.com' ],
        ];

        $mgr     = $this->make_geo();
        $content = '<p>Google is a major search engine company.</p>';
        $result  = $mgr->enhance_content_entities( $content );

        $this->assertStringContainsString( 'https://google.com', $result );
    }

    public function test_entity_invalid_type_falls_back_to_thing(): void {
        global $si_test_state;
        $si_test_state['post_meta'][1]['_search_intel_entities'] = [
            [ 'name' => 'Test', 'type' => 'EvilType', 'url' => '' ],
        ];

        $mgr     = $this->make_geo();
        $content = '<p>Test entity here.</p>';
        $result  = $mgr->enhance_content_entities( $content );

        $this->assertStringContainsString( 'schema.org/Thing', $result );
    }

    public function test_entity_markup_only_wraps_first_occurrence(): void {
        global $si_test_state;
        $si_test_state['post_meta'][1]['_search_intel_entities'] = [
            [ 'name' => 'Google', 'type' => 'Organization', 'url' => '' ],
        ];

        $mgr     = $this->make_geo();
        $content = '<p>Google is big. Google is also innovative.</p>';
        $result  = $mgr->enhance_content_entities( $content );

        $this->assertEquals( 1, substr_count( $result, 'itemscope' ) );
    }

    // ── GEO scoring ─────────────────────────────────────────────────────

    public function test_geo_score_returns_score_and_checks(): void {
        $mgr    = $this->make_geo();
        $result = $mgr->get_geo_score( 1 );

        $this->assertArrayHasKey( 'score', $result );
        $this->assertArrayHasKey( 'max', $result );
        $this->assertArrayHasKey( 'checks', $result );
        $this->assertEquals( 100, $result['max'] );
    }

    public function test_geo_score_increases_with_ai_summary(): void {
        global $si_test_state;

        $mgr      = $this->make_geo();
        $baseline = $mgr->get_geo_score( 1 );

        $si_test_state['post_meta'][1]['_search_intel_ai_summary'] = 'Summary of the article.';
        $with_summary = $mgr->get_geo_score( 1 );

        $this->assertGreaterThan( $baseline['score'], $with_summary['score'] );
    }

    public function test_geo_score_increases_with_topics(): void {
        global $si_test_state;

        $mgr      = $this->make_geo();
        $baseline = $mgr->get_geo_score( 1 );

        $si_test_state['post_meta'][1]['_search_intel_topics'] = [ 'SEO', 'AEO', 'GEO' ];
        $with_topics = $mgr->get_geo_score( 1 );

        $this->assertGreaterThan( $baseline['score'], $with_topics['score'] );
    }

    public function test_geo_score_zero_for_nonexistent_post(): void {
        $mgr    = $this->make_geo();
        $result = $mgr->get_geo_score( 999 );

        $this->assertEquals( 0, $result['score'] );
    }

    // ── AI crawl directives ─────────────────────────────────────────────

    public function test_ai_crawl_directives_renders_when_noindex_set(): void {
        global $si_test_state;
        $si_test_state['post_meta'][1]['_search_intel_ai_noindex'] = '1';

        $mgr = $this->make_geo();
        ob_start();
        $mgr->render_ai_crawl_directives();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'GPTBot', $output );
        $this->assertStringContainsString( 'noindex', $output );
    }

    public function test_ai_crawl_directives_empty_without_noindex(): void {
        $mgr = $this->make_geo();
        ob_start();
        $mgr->render_ai_crawl_directives();
        $output = ob_get_clean();

        $this->assertStringNotContainsString( 'GPTBot', $output );
    }

    // ── GEO disabled ────────────────────────────────────────────────────

    public function test_geo_disabled_registers_no_hooks(): void {
        global $si_test_state;
        $si_test_state['actions'] = [];
        $si_test_state['filters'] = [];

        $this->make_geo( [ 'enable_geo' => false ] );

        $this->assertArrayNotHasKey( 'wp_head', $si_test_state['actions'] );
    }
}
