<?php
/**
 * Integration tests for Schema JSON-LD output.
 * Tests full @graph structure, knowledge graph, breadcrumbs, article schema with v1.1 fields.
 */

use PHPUnit\Framework\TestCase;
use Search_Intel\Schema_Manager;
use Search_Intel\Core;

class SchemaOutputTest extends TestCase {

    private function make_schema( array $overrides = [] ): Schema_Manager {
        $settings = array_merge( Core::get_default_settings(), $overrides );
        return new Schema_Manager( $settings );
    }

    protected function setUp(): void {
        global $si_test_state, $wp;
        $si_test_state['post_meta']  = [];
        $si_test_state['user_meta']  = [];
        $si_test_state['options']    = [];
        $si_test_state['actions']    = [];
        $si_test_state['filters']    = [];
        $si_test_state['transients'] = [];
        $si_test_state['posts']      = [
            1 => [
                'ID'                => 1,
                'post_title'        => 'Schema Test Post',
                'post_content'      => '<p>Detailed content for schema generation and testing purposes.</p>',
                'post_excerpt'      => 'Schema excerpt.',
                'post_author'       => 1,
                'post_type'         => 'post',
                'post_status'       => 'publish',
                'post_date_gmt'     => '2026-01-15 10:00:00',
                'post_modified_gmt' => '2026-02-20 14:30:00',
            ],
        ];
        $wp = (object) [ 'request' => '' ];
    }

    // ── JSON-LD structure ────────────────────────────────────────────────

    public function test_render_schema_outputs_script_tag(): void {
        $mgr = $this->make_schema();
        ob_start();
        $mgr->render_schema();
        $output = ob_get_clean();

        $this->assertStringContainsString( '<script type="application/ld+json">', $output );
        $this->assertStringContainsString( '</script>', $output );
    }

    public function test_render_schema_outputs_valid_json(): void {
        $mgr = $this->make_schema();
        ob_start();
        $mgr->render_schema();
        $output = ob_get_clean();

        preg_match( '/<script[^>]*>(.*?)<\/script>/s', $output, $matches );
        $json = json_decode( $matches[1] ?? '', true );

        $this->assertNotNull( $json );
        $this->assertArrayHasKey( '@context', $json );
        $this->assertEquals( 'https://schema.org', $json['@context'] );
    }

    public function test_schema_has_graph_array(): void {
        $mgr = $this->make_schema();
        ob_start();
        $mgr->render_schema();
        $output = ob_get_clean();

        preg_match( '/<script[^>]*>(.*?)<\/script>/s', $output, $matches );
        $json = json_decode( $matches[1], true );

        $this->assertArrayHasKey( '@graph', $json );
        $this->assertIsArray( $json['@graph'] );
    }

    public function test_graph_contains_organization(): void {
        $mgr = $this->make_schema();
        ob_start();
        $mgr->render_schema();
        $output = ob_get_clean();

        preg_match( '/<script[^>]*>(.*?)<\/script>/s', $output, $matches );
        $json  = json_decode( $matches[1], true );
        $types = array_column( $json['@graph'], '@type' );

        $this->assertContains( 'Organization', $types );
    }

    public function test_website_schema_method_returns_correct_type(): void {
        $mgr    = $this->make_schema();
        $method = new ReflectionMethod( $mgr, 'get_website_schema' );
        $method->setAccessible( true );
        $schema = $method->invoke( $mgr );

        $this->assertEquals( 'WebSite', $schema['@type'] );
    }

    public function test_graph_contains_breadcrumb_list(): void {
        $mgr = $this->make_schema();
        ob_start();
        $mgr->render_schema();
        $output = ob_get_clean();

        preg_match( '/<script[^>]*>(.*?)<\/script>/s', $output, $matches );
        $json  = json_decode( $matches[1], true );
        $types = array_column( $json['@graph'], '@type' );

        $this->assertContains( 'BreadcrumbList', $types );
    }

    public function test_graph_contains_article_on_singular(): void {
        $mgr = $this->make_schema();
        ob_start();
        $mgr->render_schema();
        $output = ob_get_clean();

        preg_match( '/<script[^>]*>(.*?)<\/script>/s', $output, $matches );
        $json = json_decode( $matches[1], true );

        $found_article = false;
        foreach ( $json['@graph'] as $item ) {
            if ( in_array( $item['@type'] ?? '', [ 'Article', 'BlogPosting', 'WebPage' ], true ) ) {
                $found_article = true;
                break;
            }
        }
        $this->assertTrue( $found_article );
    }

    // ── Knowledge graph detail ──────────────────────────────────────────

    public function test_knowledge_graph_has_name_and_url(): void {
        $mgr = $this->make_schema();
        ob_start();
        $mgr->render_schema();
        $output = ob_get_clean();

        preg_match( '/<script[^>]*>(.*?)<\/script>/s', $output, $matches );
        $json = json_decode( $matches[1], true );

        $org = null;
        foreach ( $json['@graph'] as $item ) {
            if ( ( $item['@type'] ?? '' ) === 'Organization' ) {
                $org = $item;
                break;
            }
        }

        $this->assertNotNull( $org );
        $this->assertArrayHasKey( 'name', $org );
        $this->assertArrayHasKey( 'url', $org );
    }

    public function test_knowledge_graph_logo_when_set(): void {
        $mgr = $this->make_schema( [ 'knowledge_logo' => 'https://example.com/logo.png' ] );
        ob_start();
        $mgr->render_schema();
        $output = ob_get_clean();

        preg_match( '/<script[^>]*>(.*?)<\/script>/s', $output, $matches );
        $json = json_decode( $matches[1], true );

        $org = null;
        foreach ( $json['@graph'] as $item ) {
            if ( ( $item['@type'] ?? '' ) === 'Organization' ) {
                $org = $item;
                break;
            }
        }

        $this->assertArrayHasKey( 'logo', $org );
        $this->assertEquals( 'https://example.com/logo.png', $org['logo']['url'] );
    }

    // ── WebSite schema detail ───────────────────────────────────────────

    public function test_website_has_search_action(): void {
        $mgr    = $this->make_schema();
        $method = new ReflectionMethod( $mgr, 'get_website_schema' );
        $method->setAccessible( true );
        $schema = $method->invoke( $mgr );

        $this->assertArrayHasKey( 'potentialAction', $schema );
        $this->assertEquals( 'SearchAction', $schema['potentialAction']['@type'] );
    }

    public function test_website_has_language(): void {
        $mgr    = $this->make_schema();
        $method = new ReflectionMethod( $mgr, 'get_website_schema' );
        $method->setAccessible( true );
        $schema = $method->invoke( $mgr );

        $this->assertArrayHasKey( 'inLanguage', $schema );
        $this->assertEquals( 'en_US', $schema['inLanguage'] );
    }

    // ── Article schema v1.1 fields ──────────────────────────────────────

    public function test_article_schema_has_headline(): void {
        $mgr    = $this->make_schema();
        $schema = $mgr->get_singular_schema( 1 );

        $this->assertArrayHasKey( 'headline', $schema );
        $this->assertEquals( 'Schema Test Post', $schema['headline'] );
    }

    public function test_article_schema_has_url(): void {
        $mgr    = $this->make_schema();
        $schema = $mgr->get_singular_schema( 1 );

        $this->assertArrayHasKey( 'url', $schema );
        $this->assertStringContainsString( 'example.com', $schema['url'] );
    }

    public function test_article_schema_has_main_entity(): void {
        $mgr    = $this->make_schema();
        $schema = $mgr->get_singular_schema( 1 );

        $this->assertArrayHasKey( 'mainEntityOfPage', $schema );
        $this->assertEquals( 'WebPage', $schema['mainEntityOfPage']['@type'] );
    }

    public function test_article_schema_with_abstract(): void {
        global $si_test_state;
        $si_test_state['post_meta'][1]['_search_intel_direct_answer'] = 'Schema is structured data markup.';

        $mgr    = $this->make_schema();
        $schema = $mgr->get_singular_schema( 1 );

        $this->assertArrayHasKey( 'abstract', $schema );
        $this->assertEquals( 'Schema is structured data markup.', $schema['abstract'] );
    }

    public function test_article_schema_with_citations(): void {
        global $si_test_state;
        $si_test_state['post_meta'][1]['_search_intel_citations'] = [
            [ 'source' => 'Google Docs', 'url' => 'https://google.com/docs', 'date' => '2024-06-01', 'quote' => 'Important info.' ],
            [ 'source' => 'MDN', 'url' => 'https://developer.mozilla.org', 'date' => '2024-05-15', 'quote' => 'Web standard ref.' ],
        ];

        $mgr    = $this->make_schema();
        $schema = $mgr->get_singular_schema( 1 );

        $this->assertArrayHasKey( 'citation', $schema );
        $this->assertCount( 2, $schema['citation'] );
        $this->assertEquals( 'CreativeWork', $schema['citation'][0]['@type'] );
        $this->assertEquals( 'Google Docs', $schema['citation'][0]['name'] );
    }

    public function test_article_schema_with_audience(): void {
        global $si_test_state;
        $si_test_state['post_meta'][1]['_search_intel_audience'] = 'marketers';

        $mgr    = $this->make_schema();
        $schema = $mgr->get_singular_schema( 1 );

        $this->assertArrayHasKey( 'audience', $schema );
        $this->assertEquals( 'Audience', $schema['audience']['@type'] );
        $this->assertEquals( 'marketers', $schema['audience']['audienceType'] );
    }

    public function test_article_schema_with_content_location(): void {
        global $si_test_state;
        $si_test_state['post_meta'][1]['_search_intel_local_relevance'] = '1';
        $si_test_state['post_meta'][1]['_search_intel_local_location']  = 'New York, NY';

        $mgr    = $this->make_schema();
        $schema = $mgr->get_singular_schema( 1 );

        $this->assertArrayHasKey( 'contentLocation', $schema );
        $this->assertEquals( 'New York, NY', $schema['contentLocation']['name'] );
    }

    public function test_article_schema_custom_type_override(): void {
        global $si_test_state;
        $si_test_state['post_meta'][1]['_search_intel_schema_type'] = 'BlogPosting';

        $mgr    = $this->make_schema();
        $schema = $mgr->get_singular_schema( 1 );

        $this->assertEquals( 'BlogPosting', $schema['@type'] );
    }

    // ── Person knowledge graph ──────────────────────────────────────────

    public function test_person_knowledge_graph_type(): void {
        $mgr = $this->make_schema( [
            'knowledge_type' => 'Person',
            'knowledge_name' => 'Jane Doe',
        ] );
        ob_start();
        $mgr->render_schema();
        $output = ob_get_clean();

        preg_match( '/<script[^>]*>(.*?)<\/script>/s', $output, $matches );
        $json = json_decode( $matches[1], true );

        $person = null;
        foreach ( $json['@graph'] as $item ) {
            if ( ( $item['@type'] ?? '' ) === 'Person' ) {
                $person = $item;
                break;
            }
        }

        $this->assertNotNull( $person );
        $this->assertEquals( 'Jane Doe', $person['name'] );
    }

    // ── Schema disabled ─────────────────────────────────────────────────

    public function test_schema_disabled_registers_no_hooks(): void {
        global $si_test_state;
        $si_test_state['actions'] = [];

        $this->make_schema( [ 'enable_schema' => false ] );

        $this->assertArrayNotHasKey( 'wp_head', $si_test_state['actions'] );
    }
}
