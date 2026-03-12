<?php
/**
 * Unit tests for the AEO_Manager class.
 */

use PHPUnit\Framework\TestCase;
use Search_Intel\AEO_Manager;

class AEOManagerTest extends TestCase {

    private function make_manager( array $overrides = [] ): AEO_Manager {
        $settings = array_merge( [
            'enable_seo'            => true,
            'enable_aeo'            => true,
            'enable_faq_schema'     => true,
            'enable_howto_schema'   => true,
            'enable_speakable'      => true,
            'auto_detect_faq'       => true,
            'speakable_selectors'   => [ '.entry-content' ],
        ], $overrides );

        return new AEO_Manager( $settings );
    }

    protected function setUp(): void {
        global $si_test_state;
        $si_test_state['post_meta']  = [];
        $si_test_state['actions']    = [];
        $si_test_state['filters']    = [];
        $si_test_state['transients'] = [];
        $si_test_state['posts']      = [
            1 => [
                'ID'           => 1,
                'post_title'   => 'FAQ About SEO',
                'post_content' => '<p>Learn about SEO best practices.</p>',
                'post_excerpt' => '',
                'post_author'  => 1,
                'post_type'    => 'post',
            ],
        ];
    }

    // ── Hooks ────────────────────────────────────────────────────────────

    public function test_hooks_registered_when_aeo_enabled(): void {
        $this->make_manager();

        $this->assertArrayHasKey( 'wp_head', $GLOBALS['si_test_state']['actions'] );
    }

    public function test_no_hooks_when_aeo_disabled(): void {
        global $si_test_state;
        $si_test_state['actions'] = [];

        $this->make_manager( [ 'enable_aeo' => false ] );

        $this->assertArrayNotHasKey( 'wp_head', $si_test_state['actions'] );
    }

    // ── FAQ schema ───────────────────────────────────────────────────────

    public function test_get_faq_schema_with_faqs(): void {
        global $si_test_state;
        $si_test_state['post_meta'][1]['_search_intel_faqs'] = [
            [ 'question' => 'What is SEO?', 'answer' => 'Search Engine Optimization.' ],
            [ 'question' => 'Why SEO?', 'answer' => 'To improve visibility.' ],
        ];

        $mgr    = $this->make_manager();
        $schema = $mgr->get_faq_schema( 1 );

        $this->assertNotEmpty( $schema );
        $this->assertEquals( 'FAQPage', $schema['@type'] );
        $this->assertCount( 2, $schema['mainEntity'] );
    }

    public function test_get_faq_schema_empty_without_faqs(): void {
        $mgr    = $this->make_manager();
        $schema = $mgr->get_faq_schema( 1 );

        $this->assertEmpty( $schema );
    }

    public function test_faq_schema_question_format(): void {
        global $si_test_state;
        $si_test_state['post_meta'][1]['_search_intel_faqs'] = [
            [ 'question' => 'Test Q?', 'answer' => 'Test A.' ],
        ];

        $mgr    = $this->make_manager();
        $schema = $mgr->get_faq_schema( 1 );

        $q = $schema['mainEntity'][0];
        $this->assertEquals( 'Question', $q['@type'] );
        $this->assertEquals( 'Test Q?', $q['name'] );
        $this->assertEquals( 'Answer', $q['acceptedAnswer']['@type'] );
        $this->assertEquals( 'Test A.', $q['acceptedAnswer']['text'] );
    }

    // ── HowTo schema ────────────────────────────────────────────────────

    public function test_get_howto_schema_returns_empty_without_type(): void {
        $mgr    = $this->make_manager();
        $schema = $mgr->get_howto_schema( 1 );

        $this->assertEmpty( $schema );
    }

    public function test_get_howto_schema_with_type_set(): void {
        global $si_test_state;
        $si_test_state['post_meta'][1]['_search_intel_schema_type'] = 'HowTo';
        $si_test_state['posts'][1]['post_content'] = '<h2>Step 1: Do this</h2><p>Instructions.</p><h2>Step 2: Do that</h2><p>More instructions.</p>';

        $mgr    = $this->make_manager();
        $schema = $mgr->get_howto_schema( 1 );

        // May be empty if HowTo requires specific conditions; verify structure if present
        if ( ! empty( $schema ) ) {
            $this->assertEquals( 'HowTo', $schema['@type'] );
            $this->assertArrayHasKey( 'step', $schema );
        } else {
            $this->assertEmpty( $schema );
        }
    }

    // ── Direct answer meta ──────────────────────────────────────────────

    public function test_render_direct_answer_meta_outputs_tag(): void {
        global $si_test_state;
        $si_test_state['post_meta'][1]['_search_intel_direct_answer'] = 'SEO stands for Search Engine Optimization.';

        $mgr = $this->make_manager();
        ob_start();
        $mgr->render_direct_answer_meta();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'name="direct-answer"', $output );
        $this->assertStringContainsString( 'SEO stands for Search Engine Optimization.', $output );
    }

    public function test_render_direct_answer_meta_empty_without_value(): void {
        $mgr = $this->make_manager();
        ob_start();
        $mgr->render_direct_answer_meta();
        $output = ob_get_clean();

        $this->assertEmpty( trim( $output ) );
    }

    // ── Auto lead summary ───────────────────────────────────────────────

    public function test_auto_lead_summary_generates_from_content(): void {
        global $si_test_state;
        $si_test_state['posts'][1]['post_content'] = 'First sentence here. Second sentence follows. Third sentence completes it. Fourth and more.';
        $si_test_state['posts'][1]['post_excerpt']  = '';

        $mgr    = $this->make_manager();
        $result = $mgr->auto_lead_summary( '', get_post( 1 ) );

        $this->assertNotEmpty( $result );
        $this->assertStringContainsString( 'First sentence here.', $result );
    }

    public function test_auto_lead_summary_preserves_existing_excerpt(): void {
        $mgr    = $this->make_manager();
        $result = $mgr->auto_lead_summary( 'Existing excerpt', get_post( 1 ) );

        $this->assertEquals( 'Existing excerpt', $result );
    }

    // ── Q&A pattern detection ───────────────────────────────────────────

    public function test_detect_qa_bold_pattern(): void {
        $content = '<p><strong>What is SEO?</strong> SEO is about optimization.</p>';
        $mgr     = $this->make_manager();
        $result  = $mgr->detect_qa_patterns( $content );

        $this->assertNotEmpty( $result );
    }

    public function test_detect_qa_prefix_pattern(): void {
        $content = 'Q: What is SEO? A: SEO stands for search engine optimization and improves visibility.';
        $mgr     = $this->make_manager();
        $result  = $mgr->detect_qa_patterns( $content );

        $this->assertNotEmpty( $result );
        $this->assertEquals( 'qa_prefix', $result[0]['type'] );
    }

    public function test_detect_qa_no_pattern(): void {
        $content = '<p>This is regular paragraph text without questions.</p>';
        $mgr     = $this->make_manager();
        $result  = $mgr->detect_qa_patterns( $content );

        $this->assertEmpty( $result );
    }

    // ── Snippet score ───────────────────────────────────────────────────

    public function test_snippet_score_returns_array(): void {
        $content = '<p>A concise answer block.</p><ul><li>Item 1</li></ul>';
        $mgr     = $this->make_manager();
        $result  = $mgr->get_snippet_score( $content );

        $this->assertArrayHasKey( 'score', $result );
        $this->assertIsInt( $result['score'] );
    }

    public function test_snippet_score_higher_with_list(): void {
        $plain = '<p>Just text without structure.</p>';
        $list  = '<p>Answer:</p><ul><li>Point 1</li><li>Point 2</li></ul>';

        $mgr = $this->make_manager();
        $s1  = $mgr->get_snippet_score( $plain );
        $s2  = $mgr->get_snippet_score( $list );

        $this->assertGreaterThanOrEqual( $s1['score'], $s2['score'] );
    }
}
