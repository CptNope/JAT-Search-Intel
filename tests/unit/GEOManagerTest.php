<?php
/**
 * Unit tests for the GEO_Manager class.
 */

use PHPUnit\Framework\TestCase;
use JAT_Search_Intel\GEO_Manager;

class GEOManagerTest extends TestCase {

    private function make_manager( array $overrides = [] ): GEO_Manager {
        $settings = array_merge( [
            'enable_seo'          => true,
            'enable_geo'          => true,
            'enable_llms_txt'     => true,
            'enable_ai_meta'      => true,
            'enable_entity_markup'=> true,
            'content_freshness'   => true,
            'author_expertise'    => true,
            'site_name'           => 'Test Site',
        ], $overrides );

        return new GEO_Manager( $settings );
    }

    protected function setUp(): void {
        global $si_test_state;
        $si_test_state['post_meta']  = [];
        $si_test_state['actions']    = [];
        $si_test_state['filters']    = [];
        $si_test_state['posts']      = [
            1 => [
                'ID'           => 1,
                'post_title'   => 'GEO Test Post',
                'post_content' => '<p>Content about AI and search optimization.</p>',
                'post_excerpt' => '',
                'post_author'  => 1,
                'post_type'    => 'post',
            ],
        ];
    }

    // ── Hooks ────────────────────────────────────────────────────────────

    public function test_hooks_registered_when_geo_enabled(): void {
        $this->make_manager();

        $this->assertArrayHasKey( 'wp_head', $GLOBALS['si_test_state']['actions'] );
    }

    public function test_no_hooks_when_geo_disabled(): void {
        global $si_test_state;
        $si_test_state['actions'] = [];

        $this->make_manager( [ 'enable_geo' => false ] );

        $this->assertArrayNotHasKey( 'wp_head', $si_test_state['actions'] );
    }

    // ── AI meta output ──────────────────────────────────────────────────

    public function test_render_ai_meta_outputs_ai_summary(): void {
        global $si_test_state;
        $si_test_state['post_meta'][1]['_jat_search_intel_ai_summary'] = 'AI-friendly summary.';

        $mgr = $this->make_manager();
        ob_start();
        $mgr->render_ai_meta();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'name="ai-summary"', $output );
        $this->assertStringContainsString( 'AI-friendly summary.', $output );
    }

    public function test_render_ai_meta_outputs_content_type(): void {
        global $si_test_state;
        $si_test_state['post_meta'][1]['_jat_search_intel_content_type'] = 'guide';

        $mgr = $this->make_manager();
        ob_start();
        $mgr->render_ai_meta();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'name="content-type"', $output );
    }

    public function test_render_ai_meta_outputs_content_intent(): void {
        global $si_test_state;
        $si_test_state['post_meta'][1]['_jat_search_intel_content_intent'] = 'informational';

        $mgr = $this->make_manager();
        ob_start();
        $mgr->render_ai_meta();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'name="content-intent"', $output );
        $this->assertStringContainsString( 'informational', $output );
    }

    public function test_render_ai_meta_outputs_audience(): void {
        global $si_test_state;
        $si_test_state['post_meta'][1]['_jat_search_intel_audience'] = 'developers';

        $mgr = $this->make_manager();
        ob_start();
        $mgr->render_ai_meta();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'name="target-audience"', $output );
        $this->assertStringContainsString( 'developers', $output );
    }

    public function test_render_ai_meta_outputs_author_expertise(): void {
        global $si_test_state;
        $si_test_state['post_meta'][1]['_jat_search_intel_author_expertise_notes'] = '10 years in SEO';

        $mgr = $this->make_manager();
        ob_start();
        $mgr->render_ai_meta();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'name="author-expertise"', $output );
        $this->assertStringContainsString( '10 years in SEO', $output );
    }

    public function test_render_ai_meta_outputs_local_relevance(): void {
        global $si_test_state;
        $si_test_state['post_meta'][1]['_jat_search_intel_local_relevance'] = '1';
        $si_test_state['post_meta'][1]['_jat_search_intel_local_location']  = 'New York, NY';

        $mgr = $this->make_manager();
        ob_start();
        $mgr->render_ai_meta();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'name="geo.placename"', $output );
        $this->assertStringContainsString( 'New York, NY', $output );
    }

    public function test_render_ai_meta_no_local_without_toggle(): void {
        global $si_test_state;
        $si_test_state['post_meta'][1]['_jat_search_intel_local_relevance'] = '';
        $si_test_state['post_meta'][1]['_jat_search_intel_local_location']  = 'New York, NY';

        $mgr = $this->make_manager();
        ob_start();
        $mgr->render_ai_meta();
        $output = ob_get_clean();

        $this->assertStringNotContainsString( 'geo.placename', $output );
    }

    // ── Entity markup ───────────────────────────────────────────────────

    public function test_enhance_content_entities_wraps_entity(): void {
        global $si_test_state;
        $si_test_state['post_meta'][1]['_jat_search_intel_entities'] = [
            [ 'name' => 'Google', 'type' => 'Organization', 'url' => '' ],
        ];

        $mgr    = $this->make_manager();
        $result = $mgr->enhance_content_entities( '<p>Google is a search engine.</p>' );

        $this->assertStringContainsString( 'itemscope', $result );
        $this->assertStringContainsString( 'schema.org/Organization', $result );
    }

    public function test_enhance_content_entities_keeps_text_intact(): void {
        global $si_test_state;
        $si_test_state['post_meta'][1]['_jat_search_intel_entities'] = [
            [ 'name' => 'Google', 'type' => 'Organization', 'url' => '' ],
        ];

        $mgr    = $this->make_manager();
        $result = $mgr->enhance_content_entities( '<p>Try Googleplex. Google is a search engine.</p>' );

        $this->assertSame(
            '<p>Try Googleplex. <span itemscope itemtype="https://schema.org/Organization"><span itemprop="name">Google</span></span> is a search engine.</p>',
            $result
        );
        $this->assertSame( 'Try Googleplex. Google is a search engine.', strip_tags( $result ) );
    }

    public function test_enhance_content_entities_uses_link_for_url(): void {
        global $si_test_state;
        $si_test_state['post_meta'][1]['_jat_search_intel_entities'] = [
            [ 'name' => 'Google', 'type' => 'Organization', 'url' => 'https://google.com' ],
        ];

        $mgr    = $this->make_manager();
        $result = $mgr->enhance_content_entities( '<p>Google is great.</p>' );

        $this->assertStringContainsString( 'href="https://google.com"', $result );
        $this->assertStringContainsString( 'itemprop="url"', $result );
    }

    public function test_enhance_content_entities_fallback_type(): void {
        global $si_test_state;
        $si_test_state['post_meta'][1]['_jat_search_intel_entities'] = [
            [ 'name' => 'Widget', 'type' => 'InvalidType', 'url' => '' ],
        ];

        $mgr    = $this->make_manager();
        $result = $mgr->enhance_content_entities( '<p>Widget is useful.</p>' );

        $this->assertStringContainsString( 'schema.org/Thing', $result );
    }

    public function test_enhance_content_entities_no_change_without_entities(): void {
        $mgr    = $this->make_manager();
        $input  = '<p>No entities here.</p>';
        $result = $mgr->enhance_content_entities( $input );

        $this->assertEquals( $input, $result );
    }

    // ── Freshness meta ──────────────────────────────────────────────────

    public function test_render_freshness_meta_outputs_tags(): void {
        $mgr = $this->make_manager();
        ob_start();
        $mgr->render_freshness_meta();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'name="content-freshness"', $output );
    }

    // ── AI crawl directives ─────────────────────────────────────────────

    public function test_render_ai_crawl_outputs_noindex_when_set(): void {
        global $si_test_state;
        $si_test_state['post_meta'][1]['_jat_search_intel_ai_noindex'] = '1';

        $mgr = $this->make_manager();
        ob_start();
        $mgr->render_ai_crawl_directives();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'GPTBot', $output );
        $this->assertStringContainsString( 'noindex', $output );
    }

    public function test_render_ai_crawl_no_output_when_not_set(): void {
        $mgr = $this->make_manager();
        ob_start();
        $mgr->render_ai_crawl_directives();
        $output = ob_get_clean();

        $this->assertStringNotContainsString( 'GPTBot', $output );
    }
}
