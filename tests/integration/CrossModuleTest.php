<?php
/**
 * Integration tests for cross-module interactions.
 * Tests settings/metabox save/load cycles, Core module wiring, and full render pipelines.
 */

use PHPUnit\Framework\TestCase;
use JAT_Search_Intel\Admin;
use JAT_Search_Intel\Core;
use JAT_Search_Intel\Content_Analyzer;

class CrossModuleTest extends TestCase {

    private function make_admin( array $overrides = [] ): Admin {
        $settings = array_merge( Core::get_default_settings(), $overrides );
        $ref  = new ReflectionClass( Core::class );
        $core = $ref->newInstanceWithoutConstructor();
        return new Admin( $settings, $core );
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
                'post_title'        => 'Cross Module Test',
                'post_content'      => '<p>Content for cross-module testing of the JAT Search Intel plugin with enough words to test analysis.</p>',
                'post_excerpt'      => 'Cross module excerpt.',
                'post_author'       => 1,
                'post_type'         => 'post',
                'post_status'       => 'publish',
                'post_date_gmt'     => '2026-01-15 10:00:00',
                'post_modified_gmt' => '2026-02-20 14:30:00',
            ],
        ];
    }

    // ── Metabox save → SEO Manager read cycle ───────────────────────────

    public function test_metabox_save_title_read_by_seo(): void {
        $admin = $this->make_admin();
        $_POST = [
            'jat_search_intel_metabox_nonce' => 'test_nonce',
            'si_title' => 'Saved SEO Title',
        ];
        $admin->save_metabox_data( 1, get_post( 1 ) );

        $seo = new \JAT_Search_Intel\SEO_Manager( Core::get_default_settings() );
        $title = $seo->get_title( 1 );

        $this->assertEquals( 'Saved SEO Title', $title );
    }

    public function test_metabox_save_description_read_by_seo(): void {
        $admin = $this->make_admin();
        $_POST = [
            'jat_search_intel_metabox_nonce' => 'test_nonce',
            'si_description' => 'Saved meta description for SEO.',
        ];
        $admin->save_metabox_data( 1, get_post( 1 ) );

        $seo  = new \JAT_Search_Intel\SEO_Manager( Core::get_default_settings() );
        $desc = $seo->get_description( 1 );

        $this->assertEquals( 'Saved meta description for SEO.', $desc );
    }

    public function test_metabox_save_canonical_read_by_seo(): void {
        $admin = $this->make_admin();
        $_POST = [
            'jat_search_intel_metabox_nonce' => 'test_nonce',
            'si_canonical' => 'https://example.com/custom-canonical',
        ];
        $admin->save_metabox_data( 1, get_post( 1 ) );

        $canonical = get_post_meta( 1, '_jat_search_intel_canonical', true );
        $this->assertEquals( 'https://example.com/custom-canonical', $canonical );
    }

    // ── Metabox save → AEO Manager read cycle ───────────────────────────

    public function test_metabox_save_faqs_read_by_aeo(): void {
        $admin = $this->make_admin();
        $_POST = [
            'jat_search_intel_metabox_nonce' => 'test_nonce',
            'si_faqs' => json_encode( [
                [ 'question' => 'What is GEO?', 'answer' => 'Generative Engine Optimization.' ],
            ] ),
        ];
        $admin->save_metabox_data( 1, get_post( 1 ) );

        $aeo  = new \JAT_Search_Intel\AEO_Manager( Core::get_default_settings() );
        $faqs = $aeo->get_faq_schema( 1 );

        $this->assertNotEmpty( $faqs );
    }

    public function test_metabox_save_direct_answer_rendered_by_aeo(): void {
        $admin = $this->make_admin();
        $_POST = [
            'jat_search_intel_metabox_nonce' => 'test_nonce',
            'si_direct_answer' => 'GEO optimizes for AI engines.',
        ];
        $admin->save_metabox_data( 1, get_post( 1 ) );

        $aeo = new \JAT_Search_Intel\AEO_Manager( Core::get_default_settings() );
        ob_start();
        $aeo->render_direct_answer_meta();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'GEO optimizes for AI engines.', $output );
    }

    // ── Metabox save → Schema Manager read cycle ────────────────────────

    public function test_metabox_save_schema_type_read_by_schema(): void {
        $admin = $this->make_admin();
        $_POST = [
            'jat_search_intel_metabox_nonce' => 'test_nonce',
            'si_schema_type' => 'BlogPosting',
        ];
        $admin->save_metabox_data( 1, get_post( 1 ) );

        $schema = new \JAT_Search_Intel\Schema_Manager( Core::get_default_settings() );
        $result = $schema->get_singular_schema( 1 );

        $this->assertEquals( 'BlogPosting', $result['@type'] );
    }

    public function test_metabox_save_citations_in_schema(): void {
        $admin = $this->make_admin();
        $_POST = [
            'jat_search_intel_metabox_nonce' => 'test_nonce',
            'si_citations' => json_encode( [
                [ 'source' => 'Research Paper', 'url' => 'https://example.com/paper', 'date' => '2025-06-01', 'quote' => 'Key finding.' ],
            ] ),
        ];
        $admin->save_metabox_data( 1, get_post( 1 ) );

        $schema = new \JAT_Search_Intel\Schema_Manager( Core::get_default_settings() );
        $result = $schema->get_singular_schema( 1 );

        $this->assertArrayHasKey( 'citation', $result );
        $this->assertEquals( 'Research Paper', $result['citation'][0]['name'] );
    }

    // ── Metabox save → GEO Manager read cycle ───────────────────────────

    public function test_metabox_save_entities_used_by_geo(): void {
        $admin = $this->make_admin();
        $_POST = [
            'jat_search_intel_metabox_nonce' => 'test_nonce',
            'si_entities' => json_encode( [
                [ 'name' => 'WordPress', 'type' => 'SoftwareApplication', 'url' => 'https://wordpress.org' ],
            ] ),
        ];
        $admin->save_metabox_data( 1, get_post( 1 ) );

        $geo    = new \JAT_Search_Intel\GEO_Manager( Core::get_default_settings() );
        $result = $geo->enhance_content_entities( '<p>WordPress powers the web.</p>' );

        $this->assertStringContainsString( 'schema.org/SoftwareApplication', $result );
    }

    // ── Settings sanitization round-trip ─────────────────────────────────

    public function test_settings_sanitize_round_trip_booleans(): void {
        $admin = $this->make_admin();

        $input     = [ 'enable_seo' => '1', 'enable_og' => '', 'enable_sitemap' => '1' ];
        $sanitized = $admin->sanitize_settings( $input );

        $this->assertTrue( $sanitized['enable_seo'] );
        $this->assertFalse( $sanitized['enable_og'] );
        $this->assertTrue( $sanitized['enable_sitemap'] );
    }

    public function test_settings_sanitize_round_trip_arrays(): void {
        $admin = $this->make_admin();

        $input     = [ 'enabled_post_types' => [ 'post', 'page', 'product' ] ];
        $sanitized = $admin->sanitize_settings( $input );

        $this->assertEquals( [ 'post', 'page', 'product' ], $sanitized['enabled_post_types'] );
    }

    public function test_settings_sanitize_preserves_defaults(): void {
        $admin    = $this->make_admin();
        $defaults = Core::get_default_settings();

        $sanitized = $admin->sanitize_settings( [] );

        $this->assertEquals( $defaults['enabled_post_types'], $sanitized['enabled_post_types'] );
        $this->assertEquals( $defaults['sitemap_post_types'], $sanitized['sitemap_post_types'] );
    }

    // ── Content analyzer + SEO score integration ────────────────────────

    public function test_analyzer_produces_results_for_post(): void {
        $analyzer = new Content_Analyzer( Core::get_default_settings() );
        $results  = $analyzer->analyze(
            1,
            '<p>Test content with enough words for analysis.</p>',
            'Test Title',
            'test keyword',
            'Test description'
        );

        $this->assertIsArray( $results );
        $this->assertArrayHasKey( 'seo_score', $results );
        $this->assertArrayHasKey( 'aeo_score', $results );
        $this->assertArrayHasKey( 'geo_score', $results );
        $this->assertArrayHasKey( 'readability', $results );
        $this->assertArrayHasKey( 'word_count', $results );
    }

    public function test_analyzer_seo_score_structure(): void {
        $analyzer = new Content_Analyzer( Core::get_default_settings() );
        $results  = $analyzer->analyze(
            1,
            '<h1>Main Heading</h1><p>Detailed content about the test keyword for analysis purposes.</p>',
            'Test Title',
            'test keyword',
            'Test description'
        );

        $this->assertArrayHasKey( 'score', $results['seo_score'] );
        $this->assertArrayHasKey( 'checks', $results['seo_score'] );
    }

    // ── User E-E-A-T save → GEO score integration ──────────────────────

    public function test_user_eeat_credentials_affect_geo_score(): void {
        global $si_test_state;

        $geo      = new \JAT_Search_Intel\GEO_Manager( Core::get_default_settings() );
        $baseline = $geo->get_geo_score( 1 );

        // Add credentials
        $si_test_state['user_meta'][1]['jat_search_intel_credentials'] = [ 'PhD in CS', 'AWS Certified' ];
        $with_creds = $geo->get_geo_score( 1 );

        $this->assertGreaterThanOrEqual( $baseline['score'], $with_creds['score'] );
    }

    // ── Core singleton wiring ───────────────────────────────────────────

    public function test_core_modules_share_same_settings(): void {
        // Reset singleton
        $ref  = new ReflectionClass( Core::class );
        $prop = $ref->getProperty( 'instance' );
        $prop->setAccessible( true );
        $prop->setValue( null, null );

        $core     = Core::get_instance();
        $settings = $core->get_settings();

        $this->assertEquals( $settings['enable_seo'], Core::get_default_settings()['enable_seo'] );
    }

    public function test_core_all_modules_initialized(): void {
        $ref  = new ReflectionClass( Core::class );
        $prop = $ref->getProperty( 'instance' );
        $prop->setAccessible( true );
        $prop->setValue( null, null );

        $core = Core::get_instance();

        $this->assertNotNull( $core->seo );
        $this->assertNotNull( $core->schema );
        $this->assertNotNull( $core->aeo );
        $this->assertNotNull( $core->geo );
        $this->assertNotNull( $core->analyzer );
        $this->assertNotNull( $core->sitemap );
        $this->assertNotNull( $core->title_engine );
    }
}
