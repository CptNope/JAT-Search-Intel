<?php
/**
 * Unit tests for the SEO_Manager class.
 */

use PHPUnit\Framework\TestCase;
use JAT_Search_Intel\SEO_Manager;

class SEOManagerTest extends TestCase {

    private function make_manager( array $overrides = [] ): SEO_Manager {
        $settings = array_merge( [
            'enable_seo'             => true,
            'enable_og'              => true,
            'enable_twitter'         => true,
            'enable_canonical'       => true,
            'site_name'              => 'Test Site',
            'separator'              => '|',
            'twitter_site'           => '@testsite',
            'og_type'                => 'website',
            'description_fallback'   => 'excerpt',
            'description_max_length' => 160,
            'default_og_image'       => '',
            'robots_default'         => 'index',
            'knowledge_logo'         => '',
        ], $overrides );

        return new SEO_Manager( $settings );
    }

    protected function setUp(): void {
        global $si_test_state;
        $si_test_state['post_meta'] = [];
        $si_test_state['posts']     = [
            1 => [
                'ID'           => 1,
                'post_title'   => 'Test Post',
                'post_content' => 'This is the full content of the test post for unit testing.',
                'post_excerpt' => 'This is the excerpt.',
                'post_author'  => 1,
                'post_type'    => 'post',
            ],
        ];
    }

    // ── get_title ────────────────────────────────────────────────────────

    public function test_get_title_returns_custom_seo_title(): void {
        global $si_test_state;
        $si_test_state['post_meta'][1]['_jat_search_intel_title'] = 'Custom SEO Title';

        $mgr = $this->make_manager();
        $this->assertEquals( 'Custom SEO Title', $mgr->get_title( 1 ) );
    }

    public function test_get_title_falls_back_to_post_title(): void {
        $mgr = $this->make_manager();
        $this->assertEquals( 'Test Post', $mgr->get_title( 1 ) );
    }

    public function test_get_title_returns_empty_for_null_id(): void {
        $mgr = $this->make_manager();
        $this->assertEquals( '', $mgr->get_title( null ) );
    }

    // ── get_description ──────────────────────────────────────────────────

    public function test_get_description_returns_custom_meta_desc(): void {
        global $si_test_state;
        $si_test_state['post_meta'][1]['_jat_search_intel_description'] = 'Custom meta description';

        $mgr = $this->make_manager();
        $this->assertEquals( 'Custom meta description', $mgr->get_description( 1 ) );
    }

    public function test_get_description_falls_back_to_excerpt(): void {
        $mgr = $this->make_manager( [ 'description_fallback' => 'excerpt' ] );
        $this->assertEquals( 'This is the excerpt.', $mgr->get_description( 1 ) );
    }

    public function test_get_description_falls_back_to_content_trim(): void {
        global $si_test_state;
        $si_test_state['posts'][1]['post_excerpt'] = '';

        $mgr    = $this->make_manager( [ 'description_fallback' => 'content' ] );
        $result = $mgr->get_description( 1 );

        $this->assertNotEmpty( $result );
        $this->assertStringContainsString( 'content', strtolower( $result ) );
    }

    public function test_get_description_returns_empty_with_empty_fallback(): void {
        $mgr = $this->make_manager( [ 'description_fallback' => 'empty' ] );
        $this->assertEquals( '', $mgr->get_description( 1 ) );
    }

    public function test_get_description_truncates_to_max_length(): void {
        global $si_test_state;
        $si_test_state['post_meta'][1]['_jat_search_intel_description'] = str_repeat( 'A', 200 );

        $mgr    = $this->make_manager( [ 'description_max_length' => 100 ] );
        $result = $mgr->get_description( 1 );

        $this->assertLessThanOrEqual( 100, strlen( $result ) );
        $this->assertStringEndsWith( '...', $result );
    }

    public function test_get_description_returns_bloginfo_for_null_id(): void {
        $mgr = $this->make_manager();
        $this->assertEquals( 'A test site for unit testing', $mgr->get_description( null ) );
    }

    // ── filter_separator ─────────────────────────────────────────────────

    public function test_filter_separator_returns_setting(): void {
        $mgr = $this->make_manager( [ 'separator' => '-' ] );
        $this->assertEquals( '-', $mgr->filter_separator( '|' ) );
    }

    public function test_filter_separator_falls_back_to_passed_value(): void {
        $mgr = $this->make_manager( [ 'separator' => '' ] );
        $this->assertEquals( '|', $mgr->filter_separator( '|' ) );
    }

    // ── filter_title ─────────────────────────────────────────────────────

    public function test_filter_title_returns_custom_on_singular(): void {
        global $si_test_state;
        $si_test_state['post_meta'][1]['_jat_search_intel_title'] = 'Override Title';

        $mgr = $this->make_manager();
        $this->assertEquals( 'Override Title', $mgr->filter_title( 'Original' ) );
    }

    public function test_filter_title_returns_post_title_when_no_custom(): void {
        $mgr = $this->make_manager();
        $this->assertEquals( 'Test Post', $mgr->filter_title( 'Original' ) );
    }

    // ── Hooks registration ───────────────────────────────────────────────

    public function test_hooks_registered_when_seo_enabled(): void {
        global $si_test_state;
        $si_test_state['actions'] = [];
        $si_test_state['filters'] = [];

        $this->make_manager( [ 'enable_seo' => true ] );

        $this->assertArrayHasKey( 'wp_head', $si_test_state['actions'] );
        $this->assertArrayHasKey( 'pre_get_document_title', $si_test_state['filters'] );
    }

    public function test_hooks_not_registered_when_seo_disabled(): void {
        global $si_test_state;
        $si_test_state['actions'] = [];
        $si_test_state['filters'] = [];

        $this->make_manager( [ 'enable_seo' => false ] );

        $this->assertArrayNotHasKey( 'wp_head', $si_test_state['actions'] );
    }

    public function test_og_hooks_when_enabled(): void {
        global $si_test_state;
        $si_test_state['actions'] = [];

        $this->make_manager( [ 'enable_seo' => true, 'enable_og' => true ] );

        $hooks = array_column( $si_test_state['actions']['wp_head'], 'priority' );
        $this->assertContains( 4, $hooks ); // OG at priority 4
    }

    public function test_twitter_hooks_when_enabled(): void {
        global $si_test_state;
        $si_test_state['actions'] = [];

        $this->make_manager( [ 'enable_seo' => true, 'enable_twitter' => true ] );

        $hooks = array_column( $si_test_state['actions']['wp_head'], 'priority' );
        $this->assertContains( 5, $hooks ); // Twitter at priority 5
    }

    // ── Render output ────────────────────────────────────────────────────

    public function test_render_meta_tags_outputs_description(): void {
        global $si_test_state;
        $si_test_state['post_meta'][1]['_jat_search_intel_description'] = 'A test description.';

        $mgr = $this->make_manager();
        ob_start();
        $mgr->render_meta_tags();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'name="description"', $output );
        $this->assertStringContainsString( 'A test description.', $output );
    }

    public function test_render_meta_tags_outputs_version_comment(): void {
        $mgr = $this->make_manager();
        ob_start();
        $mgr->render_meta_tags();
        $output = ob_get_clean();

        $this->assertStringContainsString( '<!-- JAT Search Intel v', $output );
    }

    public function test_render_canonical_outputs_link(): void {
        $mgr = $this->make_manager();
        ob_start();
        $mgr->render_canonical();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'rel="canonical"', $output );
        $this->assertStringContainsString( 'example.com', $output );
    }

    public function test_render_canonical_uses_custom_url(): void {
        global $si_test_state;
        $si_test_state['post_meta'][1]['_jat_search_intel_canonical'] = 'https://canonical.example.com/page';

        $mgr = $this->make_manager();
        ob_start();
        $mgr->render_canonical();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'canonical.example.com/page', $output );
    }
}
