<?php
/**
 * Unit tests for the Schema_Manager class.
 */

use PHPUnit\Framework\TestCase;
use JAT_Search_Intel\Schema_Manager;

class SchemaManagerTest extends TestCase {

    private function make_manager( array $overrides = [] ): Schema_Manager {
        $settings = array_merge( [
            'enable_seo'          => true,
            'enable_schema'       => true,
            'site_name'           => 'Test Site',
            'knowledge_type'      => 'Organization',
            'knowledge_name'      => 'Test Org',
            'knowledge_logo'      => 'https://example.com/logo.png',
            'social_profiles'     => [],
            'schema_article_type' => 'Article',
        ], $overrides );

        return new Schema_Manager( $settings );
    }

    protected function setUp(): void {
        global $si_test_state;
        $si_test_state['post_meta'] = [];
        $si_test_state['actions']   = [];
        $si_test_state['posts']     = [
            1 => [
                'ID'           => 1,
                'post_title'   => 'Schema Test Post',
                'post_content' => '<p>Content for schema testing.</p>',
                'post_excerpt' => 'Schema excerpt.',
                'post_author'  => 1,
                'post_type'    => 'post',
            ],
        ];
    }

    // ── Hooks ────────────────────────────────────────────────────────────

    public function test_hooks_registered_when_schema_enabled(): void {
        $this->make_manager();

        $this->assertArrayHasKey( 'wp_head', $GLOBALS['si_test_state']['actions'] );
    }

    public function test_no_hooks_when_schema_disabled(): void {
        global $si_test_state;
        $si_test_state['actions'] = [];

        $this->make_manager( [ 'enable_schema' => false ] );

        $this->assertArrayNotHasKey( 'wp_head', $si_test_state['actions'] );
    }

    // ── Knowledge graph ─────────────────────────────────────────────────

    public function test_knowledge_graph_type(): void {
        $mgr    = $this->make_manager();
        $method = new ReflectionMethod( $mgr, 'get_knowledge_graph' );
        $method->setAccessible( true );
        $kg = $method->invoke( $mgr );

        $this->assertEquals( 'Organization', $kg['@type'] );
        $this->assertEquals( 'Test Org', $kg['name'] );
    }

    public function test_knowledge_graph_logo(): void {
        $mgr    = $this->make_manager();
        $method = new ReflectionMethod( $mgr, 'get_knowledge_graph' );
        $method->setAccessible( true );
        $kg = $method->invoke( $mgr );

        $this->assertArrayHasKey( 'logo', $kg );
        $this->assertIsArray( $kg['logo'] );
        $this->assertStringContainsString( 'logo.png', $kg['logo']['url'] );
    }

    public function test_knowledge_graph_person_type(): void {
        $mgr    = $this->make_manager( [ 'knowledge_type' => 'Person', 'knowledge_name' => 'John Doe' ] );
        $method = new ReflectionMethod( $mgr, 'get_knowledge_graph' );
        $method->setAccessible( true );
        $kg = $method->invoke( $mgr );

        $this->assertEquals( 'Person', $kg['@type'] );
        $this->assertEquals( 'John Doe', $kg['name'] );
    }

    // ── Website schema ──────────────────────────────────────────────────

    public function test_website_schema_has_search_action(): void {
        $mgr    = $this->make_manager();
        $method = new ReflectionMethod( $mgr, 'get_website_schema' );
        $method->setAccessible( true );
        $ws = $method->invoke( $mgr );

        $this->assertEquals( 'WebSite', $ws['@type'] );
        $this->assertArrayHasKey( 'potentialAction', $ws );
        $this->assertEquals( 'SearchAction', $ws['potentialAction']['@type'] );
    }

    // ── Breadcrumbs ─────────────────────────────────────────────────────

    public function test_breadcrumbs_schema_type(): void {
        $mgr    = $this->make_manager();
        $method = new ReflectionMethod( $mgr, 'get_breadcrumbs_schema' );
        $method->setAccessible( true );
        $bc = $method->invoke( $mgr );

        $this->assertEquals( 'BreadcrumbList', $bc['@type'] );
        $this->assertArrayHasKey( 'itemListElement', $bc );
    }

    // ── Singular content schema ─────────────────────────────────────────

    public function test_singular_schema_article_type(): void {
        $mgr    = $this->make_manager();
        $schema = $mgr->get_singular_schema( 1 );

        $this->assertNotEmpty( $schema );
        $this->assertContains( $schema['@type'], [ 'Article', 'WebPage', 'BlogPosting' ] );
    }

    public function test_singular_schema_includes_headline(): void {
        $mgr    = $this->make_manager();
        $schema = $mgr->get_singular_schema( 1 );

        $this->assertArrayHasKey( 'headline', $schema );
    }

    public function test_singular_schema_includes_direct_answer_as_abstract(): void {
        global $si_test_state;
        $si_test_state['post_meta'][1]['_jat_search_intel_direct_answer'] = 'SEO is optimization.';

        $mgr    = $this->make_manager();
        $schema = $mgr->get_singular_schema( 1 );

        $this->assertArrayHasKey( 'abstract', $schema );
        $this->assertEquals( 'SEO is optimization.', $schema['abstract'] );
    }

    public function test_singular_schema_includes_citations(): void {
        global $si_test_state;
        $si_test_state['post_meta'][1]['_jat_search_intel_citations'] = [
            [ 'source' => 'Google Blog', 'url' => 'https://blog.google', 'date' => '2024-01-01', 'quote' => 'Cited text' ],
        ];

        $mgr    = $this->make_manager();
        $schema = $mgr->get_singular_schema( 1 );

        $this->assertArrayHasKey( 'citation', $schema );
        $this->assertCount( 1, $schema['citation'] );
        $this->assertEquals( 'CreativeWork', $schema['citation'][0]['@type'] );
    }

    public function test_singular_schema_includes_audience(): void {
        global $si_test_state;
        $si_test_state['post_meta'][1]['_jat_search_intel_audience'] = 'developers';

        $mgr    = $this->make_manager();
        $schema = $mgr->get_singular_schema( 1 );

        $this->assertArrayHasKey( 'audience', $schema );
        $this->assertEquals( 'Audience', $schema['audience']['@type'] );
    }

    public function test_singular_schema_includes_content_location(): void {
        global $si_test_state;
        $si_test_state['post_meta'][1]['_jat_search_intel_local_relevance'] = '1';
        $si_test_state['post_meta'][1]['_jat_search_intel_local_location']  = 'San Francisco, CA';

        $mgr    = $this->make_manager();
        $schema = $mgr->get_singular_schema( 1 );

        $this->assertArrayHasKey( 'contentLocation', $schema );
        $this->assertEquals( 'Place', $schema['contentLocation']['@type'] );
        $this->assertEquals( 'San Francisco, CA', $schema['contentLocation']['name'] );
    }

    // ── JSON-LD output ──────────────────────────────────────────────────

    public function test_render_schema_outputs_json_ld(): void {
        $mgr = $this->make_manager();
        ob_start();
        $mgr->render_schema();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'application/ld+json', $output );
        $this->assertStringContainsString( '@graph', $output );
    }
}
