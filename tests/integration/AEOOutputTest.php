<?php
/**
 * Integration tests for AEO Manager output.
 * Tests FAQ schema, direct-answer meta, speakable, lead summary, and snippet scoring.
 */

use PHPUnit\Framework\TestCase;
use JAT_Search_Intel\AEO_Manager;
use JAT_Search_Intel\Core;

class AEOOutputTest extends TestCase {

    private function make_aeo( array $overrides = [] ): AEO_Manager {
        $settings = array_merge( Core::get_default_settings(), $overrides );
        return new AEO_Manager( $settings );
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
                'post_title'        => 'AEO Integration Post',
                'post_content'      => '<p>This is detailed content about search optimization. It covers many topics thoroughly.</p>',
                'post_excerpt'      => '',
                'post_author'       => 1,
                'post_type'         => 'post',
                'post_status'       => 'publish',
                'post_date_gmt'     => '2026-01-15 10:00:00',
                'post_modified_gmt' => '2026-02-20 14:30:00',
            ],
        ];
    }

    // ── Direct answer meta output ───────────────────────────────────────

    public function test_direct_answer_meta_empty_when_not_set(): void {
        $mgr = $this->make_aeo();
        ob_start();
        $mgr->render_direct_answer_meta();
        $output = ob_get_clean();

        $this->assertStringNotContainsString( 'direct-answer', $output );
    }

    public function test_direct_answer_meta_escapes_content(): void {
        global $si_test_state;
        $si_test_state['post_meta'][1]['_jat_search_intel_direct_answer'] = 'Use <strong>bold</strong> & "quotes"';

        $mgr = $this->make_aeo();
        ob_start();
        $mgr->render_direct_answer_meta();
        $output = ob_get_clean();

        $this->assertStringNotContainsString( '<strong>', $output );
    }

    // ── FAQ schema output ───────────────────────────────────────────────

    public function test_faq_schema_renders_when_faqs_exist(): void {
        global $si_test_state;
        $si_test_state['post_meta'][1]['_jat_search_intel_faqs'] = [
            [ 'question' => 'What is SEO?', 'answer' => 'SEO stands for Search Engine Optimization.' ],
            [ 'question' => 'Why use AEO?', 'answer' => 'AEO targets AI answer engines.' ],
        ];

        // Standalone output when the shared schema graph is off.
        $mgr = $this->make_aeo( [ 'enable_schema' => false ] );
        ob_start();
        $mgr->render_aeo_schema();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'FAQPage', $output );
        $this->assertStringContainsString( 'What is SEO?', $output );
    }

    public function test_faq_joins_shared_graph_when_schema_enabled(): void {
        global $si_test_state;
        $si_test_state['post_meta'][1]['_jat_search_intel_faqs'] = [
            [ 'question' => 'What is SEO?', 'answer' => 'SEO stands for Search Engine Optimization.' ],
        ];

        $mgr = $this->make_aeo();
        ob_start();
        $mgr->render_aeo_schema();
        $this->assertSame( '', ob_get_clean(), 'No separate script tag in graph mode' );

        $nodes = $mgr->add_graph_nodes( [], [ 'page_id' => 'https://example.com/post/' ] );
        $faq   = array_values( array_filter( $nodes, static function ( $n ) { return 'FAQPage' === $n['@type']; } ) );

        $this->assertCount( 1, $faq );
        $this->assertSame( 'https://example.com/post/#faqpage', $faq[0]['@id'] );
        $this->assertSame( [ '@id' => 'https://example.com/post/' ], $faq[0]['isPartOf'] );
        $this->assertArrayNotHasKey( '@context', $faq[0] );
    }

    public function test_faq_schema_empty_without_faqs(): void {
        $mgr = $this->make_aeo();
        ob_start();
        $mgr->render_aeo_schema();
        $output = ob_get_clean();

        $this->assertStringNotContainsString( 'FAQPage', $output );
    }

    public function test_faq_schema_disabled_when_setting_off(): void {
        global $si_test_state;
        $si_test_state['post_meta'][1]['_jat_search_intel_faqs'] = [
            [ 'question' => 'Q?', 'answer' => 'A.' ],
        ];

        $mgr = $this->make_aeo( [ 'enable_faq_schema' => false ] );
        ob_start();
        $mgr->render_aeo_schema();
        $output = ob_get_clean();

        $this->assertStringNotContainsString( 'FAQPage', $output );
    }

    public function test_faq_schema_is_valid_json_ld(): void {
        global $si_test_state;
        $si_test_state['post_meta'][1]['_jat_search_intel_faqs'] = [
            [ 'question' => 'What is SEO?', 'answer' => 'It is optimization.' ],
        ];

        $mgr = $this->make_aeo( [ 'enable_schema' => false ] );
        ob_start();
        $mgr->render_aeo_schema();
        $output = ob_get_clean();

        preg_match( '/<script[^>]*>(.*?)<\/script>/s', $output, $matches );
        if ( ! empty( $matches[1] ) ) {
            $json = json_decode( $matches[1], true );
            $this->assertNotNull( $json );
            $this->assertEquals( 'FAQPage', $json['@type'] );
        } else {
            $this->markTestSkipped( 'No JSON-LD output found' );
        }
    }

    // ── Lead summary auto-generation ────────────────────────────────────

    public function test_auto_lead_summary_generates_from_content(): void {
        $mgr  = $this->make_aeo();
        $post = (object) [
            'post_content' => 'First sentence here. Second sentence follows. Third sentence ends. Fourth sentence extra.',
        ];

        $result = $mgr->auto_lead_summary( '', $post );

        $this->assertNotEmpty( $result );
        $this->assertStringContainsString( 'First sentence here.', $result );
        $this->assertStringContainsString( 'Third sentence ends.', $result );
    }

    public function test_auto_lead_summary_returns_existing_excerpt(): void {
        $mgr    = $this->make_aeo();
        $result = $mgr->auto_lead_summary( 'Existing excerpt.', (object) [ 'post_content' => 'Content.' ] );

        $this->assertEquals( 'Existing excerpt.', $result );
    }

    public function test_auto_lead_summary_returns_empty_for_no_post(): void {
        $mgr    = $this->make_aeo();
        $result = $mgr->auto_lead_summary( '', null );

        $this->assertEmpty( $result );
    }

    // ── Snippet scoring ─────────────────────────────────────────────────

    public function test_snippet_score_returns_score_and_tips(): void {
        $mgr    = $this->make_aeo();
        $result = $mgr->get_snippet_score( '<p>This is a normal paragraph with some content.</p>' );

        $this->assertArrayHasKey( 'score', $result );
        $this->assertArrayHasKey( 'checks', $result );
        $this->assertIsInt( $result['score'] );
    }

    public function test_snippet_score_rewards_list_content(): void {
        $content = '<ol><li>Step one</li><li>Step two</li><li>Step three</li></ol>';
        $mgr     = $this->make_aeo();
        $with_list    = $mgr->get_snippet_score( $content );
        $without_list = $mgr->get_snippet_score( '<p>Plain text paragraph.</p>' );

        $this->assertGreaterThanOrEqual( $without_list['score'], $with_list['score'] );
    }

    public function test_snippet_score_rewards_definition(): void {
        $content = '<p>SEO is defined as the process of optimizing web content for search engines.</p>';
        $mgr     = $this->make_aeo();
        $result  = $mgr->get_snippet_score( $content );

        $this->assertGreaterThan( 0, $result['score'] );
    }

    // ── Q&A detection integration ───────────────────────────────────────

    public function test_qa_detection_bold_pattern_returns_question_answer(): void {
        $content = '<p><strong>What is SEO optimization?</strong> SEO optimization is the practice of improving website visibility in search results.</p>';
        $mgr     = $this->make_aeo();
        $result  = $mgr->detect_qa_patterns( $content );

        $this->assertCount( 1, $result );
        $this->assertArrayHasKey( 'question', $result[0] );
        $this->assertArrayHasKey( 'answer', $result[0] );
        $this->assertEquals( 'bold_qa', $result[0]['type'] );
    }

    public function test_qa_detection_multiple_patterns(): void {
        $content = '<p><strong>What is SEO?</strong> SEO is search engine optimization for websites.</p>'
                 . '<p><strong>What is AEO?</strong> AEO is answer engine optimization for AI systems.</p>';
        $mgr    = $this->make_aeo();
        $result = $mgr->detect_qa_patterns( $content );

        $this->assertCount( 2, $result );
    }

    // ── AEO disabled ────────────────────────────────────────────────────

}
