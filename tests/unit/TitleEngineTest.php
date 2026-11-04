<?php
/**
 * Unit tests for the Title_Engine class.
 */

use PHPUnit\Framework\TestCase;
use JAT_Search_Intel\Title_Engine;

class TitleEngineTest extends TestCase {

    private function make_engine( array $overrides = [] ): Title_Engine {
        $settings = array_merge( [
            'enable_seo'      => true,
            'site_name'       => 'My Site',
            'separator'       => '|',
            'title_templates' => Title_Engine::get_default_templates(),
        ], $overrides );

        return new Title_Engine( $settings );
    }

    protected function setUp(): void {
        global $si_test_state;
        $si_test_state['post_meta'] = [];
        $si_test_state['posts']     = [
            1 => [
                'ID'           => 1,
                'post_title'   => 'Hello World',
                'post_content' => 'This is the content of the test post for unit testing purposes.',
                'post_excerpt' => 'This is the excerpt.',
                'post_author'  => 1,
                'post_type'    => 'post',
            ],
        ];
    }

    // ── Default templates ────────────────────────────────────────────────

    public function test_get_default_templates_returns_all_contexts(): void {
        $templates = Title_Engine::get_default_templates();

        $this->assertArrayHasKey( 'single', $templates );
        $this->assertArrayHasKey( 'page', $templates );
        $this->assertArrayHasKey( 'category', $templates );
        $this->assertArrayHasKey( 'tag', $templates );
        $this->assertArrayHasKey( 'author', $templates );
        $this->assertArrayHasKey( 'search', $templates );
        $this->assertArrayHasKey( '404', $templates );
        $this->assertArrayHasKey( 'homepage', $templates );
    }

    public function test_default_templates_contain_variables(): void {
        $templates = Title_Engine::get_default_templates();

        $this->assertStringContainsString( '%title%', $templates['single'] );
        $this->assertStringContainsString( '%sitename%', $templates['single'] );
        $this->assertStringContainsString( '%sep%', $templates['single'] );
    }

    // ── Variable resolution ──────────────────────────────────────────────

    public function test_resolve_variables_replaces_sitename(): void {
        $engine = $this->make_engine();
        $result = $engine->resolve_variables( 'Welcome to %sitename%' );

        $this->assertEquals( 'Welcome to My Site', $result );
    }

    public function test_resolve_variables_replaces_separator(): void {
        $engine = $this->make_engine( [ 'separator' => '-' ] );
        $result = $engine->resolve_variables( 'Hello %sep% World' );

        $this->assertEquals( 'Hello - World', $result );
    }

    public function test_resolve_variables_replaces_title_on_singular(): void {
        $engine = $this->make_engine();
        $result = $engine->resolve_variables( '%title% %sep% %sitename%' );

        $this->assertEquals( 'Hello World | My Site', $result );
    }

    public function test_resolve_variables_replaces_author(): void {
        $engine = $this->make_engine();
        $result = $engine->resolve_variables( 'By %author%' );

        $this->assertEquals( 'By Test Author', $result );
    }

    public function test_resolve_variables_cleans_double_spaces(): void {
        $engine = $this->make_engine();
        $result = $engine->resolve_variables( '%title%  %sep%  %sitename%' );

        $this->assertEquals( 'Hello World | My Site', $result );
    }

    public function test_resolve_variables_trims_trailing_separator(): void {
        $engine = $this->make_engine();
        // %search_query% is empty when not on search page
        $result = $engine->resolve_variables( '%search_query% %sep% %sitename%' );

        $this->assertEquals( 'My Site', $result );
    }

    public function test_resolve_variables_handles_excerpt(): void {
        $engine = $this->make_engine();
        $result = $engine->resolve_variables( '%excerpt%' );

        $this->assertEquals( 'This is the excerpt.', $result );
    }

    public function test_resolve_variables_handles_category(): void {
        $engine = $this->make_engine();
        $result = $engine->resolve_variables( '%category%' );

        $this->assertEquals( 'Uncategorized', $result );
    }

    // ── SEO title override ───────────────────────────────────────────────

    public function test_resolve_title_uses_seo_override_when_set(): void {
        global $si_test_state;
        $si_test_state['post_meta'][1]['_jat_search_intel_title'] = 'Custom SEO Title';

        $engine = $this->make_engine();
        $result = $engine->resolve_title( '' );

        $this->assertEquals( 'Custom SEO Title', $result );
    }

    public function test_resolve_title_uses_template_when_no_override(): void {
        $engine = $this->make_engine();
        $result = $engine->resolve_title( '' );

        $this->assertEquals( 'Hello World | My Site', $result );
    }

    // ── Fallback sitename ────────────────────────────────────────────────

    public function test_sitename_falls_back_to_bloginfo(): void {
        $engine = $this->make_engine( [ 'site_name' => '' ] );
        $result = $engine->resolve_variables( '%sitename%' );

        $this->assertEquals( 'Test Site', $result );
    }

    // ── Custom templates ─────────────────────────────────────────────────

    public function test_custom_template_is_used(): void {
        $engine = $this->make_engine( [
            'title_templates' => [
                'single' => '%title% - %sitename%',
                'page'   => '%title% - %sitename%',
            ],
        ] );
        $result = $engine->resolve_title( '' );

        $this->assertEquals( 'Hello World - My Site', $result );
    }

    // ── SEO disabled ─────────────────────────────────────────────────────

    public function test_no_filter_registered_when_seo_disabled(): void {
        global $si_test_state;
        $si_test_state['filters'] = [];

        $this->make_engine( [ 'enable_seo' => false ] );

        // Should not have registered the title filter
        $this->assertArrayNotHasKey( 'pre_get_document_title', $si_test_state['filters'] );
    }

    public function test_filter_registered_when_seo_enabled(): void {
        global $si_test_state;
        $si_test_state['filters'] = [];

        $this->make_engine( [ 'enable_seo' => true ] );

        $this->assertArrayHasKey( 'pre_get_document_title', $si_test_state['filters'] );
    }
}
