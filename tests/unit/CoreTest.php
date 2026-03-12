<?php
/**
 * Unit tests for the Core class.
 */

use PHPUnit\Framework\TestCase;
use Search_Intel\Core;

class CoreTest extends TestCase {

    protected function setUp(): void {
        global $si_test_state;
        $si_test_state['post_meta']  = [];
        $si_test_state['options']    = [];
        $si_test_state['actions']    = [];
        $si_test_state['filters']    = [];
        $si_test_state['transients'] = [];

        // Reset singleton between tests
        $ref = new ReflectionClass( Core::class );
        $prop = $ref->getProperty( 'instance' );
        $prop->setAccessible( true );
        $prop->setValue( null, null );
    }

    // ── Default settings ─────────────────────────────────────────────────

    public function test_default_settings_returns_array(): void {
        $defaults = Core::get_default_settings();
        $this->assertIsArray( $defaults );
    }

    public function test_default_settings_has_seo_keys(): void {
        $defaults = Core::get_default_settings();

        $this->assertArrayHasKey( 'enable_seo', $defaults );
        $this->assertArrayHasKey( 'enable_og', $defaults );
        $this->assertArrayHasKey( 'enable_twitter', $defaults );
        $this->assertArrayHasKey( 'enable_canonical', $defaults );
        $this->assertArrayHasKey( 'enable_robots_meta', $defaults );
    }

    public function test_default_settings_has_schema_keys(): void {
        $defaults = Core::get_default_settings();

        $this->assertArrayHasKey( 'enable_schema', $defaults );
        $this->assertArrayHasKey( 'schema_article_type', $defaults );
        $this->assertArrayHasKey( 'enable_breadcrumbs', $defaults );
    }

    public function test_default_settings_has_aeo_keys(): void {
        $defaults = Core::get_default_settings();

        $this->assertArrayHasKey( 'enable_aeo', $defaults );
        $this->assertArrayHasKey( 'enable_faq_schema', $defaults );
        $this->assertArrayHasKey( 'enable_howto_schema', $defaults );
        $this->assertArrayHasKey( 'enable_speakable', $defaults );
    }

    public function test_default_settings_has_geo_keys(): void {
        $defaults = Core::get_default_settings();

        $this->assertArrayHasKey( 'enable_geo', $defaults );
        $this->assertArrayHasKey( 'enable_llms_txt', $defaults );
        $this->assertArrayHasKey( 'enable_ai_meta', $defaults );
        $this->assertArrayHasKey( 'enable_entity_markup', $defaults );
        $this->assertArrayHasKey( 'content_freshness', $defaults );
    }

    public function test_default_settings_has_sitemap_keys(): void {
        $defaults = Core::get_default_settings();

        $this->assertArrayHasKey( 'enable_sitemap', $defaults );
        $this->assertArrayHasKey( 'sitemap_post_types', $defaults );
        $this->assertArrayHasKey( 'sitemap_max_urls', $defaults );
    }

    public function test_default_settings_has_analyzer_keys(): void {
        $defaults = Core::get_default_settings();

        $this->assertArrayHasKey( 'enable_analyzer', $defaults );
        $this->assertArrayHasKey( 'target_readability', $defaults );
    }

    public function test_default_settings_has_title_templates(): void {
        $defaults = Core::get_default_settings();

        $this->assertArrayHasKey( 'title_templates', $defaults );
        $this->assertIsArray( $defaults['title_templates'] );
    }

    public function test_default_settings_has_v11_expanded_keys(): void {
        $defaults = Core::get_default_settings();

        $this->assertArrayHasKey( 'description_fallback', $defaults );
        $this->assertArrayHasKey( 'description_max_length', $defaults );
        $this->assertArrayHasKey( 'robots_default', $defaults );
        $this->assertArrayHasKey( 'ai_summary_word_count', $defaults );
        $this->assertArrayHasKey( 'default_content_intent', $defaults );
        $this->assertArrayHasKey( 'default_audience', $defaults );
    }

    public function test_default_seo_enabled(): void {
        $defaults = Core::get_default_settings();

        $this->assertTrue( $defaults['enable_seo'] );
        $this->assertTrue( $defaults['enable_og'] );
        $this->assertTrue( $defaults['enable_twitter'] );
    }

    public function test_default_separator_is_pipe(): void {
        $defaults = Core::get_default_settings();
        $this->assertEquals( '|', $defaults['separator'] );
    }

    public function test_default_post_types(): void {
        $defaults = Core::get_default_settings();
        $this->assertEquals( [ 'post', 'page' ], $defaults['enabled_post_types'] );
    }

    public function test_default_sitemap_max_urls(): void {
        $defaults = Core::get_default_settings();
        $this->assertEquals( 2000, $defaults['sitemap_max_urls'] );
    }

    public function test_default_description_max_length(): void {
        $defaults = Core::get_default_settings();
        $this->assertEquals( 160, $defaults['description_max_length'] );
    }

    // ── Singleton ────────────────────────────────────────────────────────

    public function test_get_instance_returns_core(): void {
        $instance = Core::get_instance();
        $this->assertInstanceOf( Core::class, $instance );
    }

    public function test_get_instance_returns_same_object(): void {
        $a = Core::get_instance();
        $b = Core::get_instance();
        $this->assertSame( $a, $b );
    }

    // ── Module initialization ────────────────────────────────────────────

    public function test_core_initializes_seo_manager(): void {
        $core = Core::get_instance();
        $this->assertInstanceOf( \Search_Intel\SEO_Manager::class, $core->seo );
    }

    public function test_core_initializes_schema_manager(): void {
        $core = Core::get_instance();
        $this->assertInstanceOf( \Search_Intel\Schema_Manager::class, $core->schema );
    }

    public function test_core_initializes_aeo_manager(): void {
        $core = Core::get_instance();
        $this->assertInstanceOf( \Search_Intel\AEO_Manager::class, $core->aeo );
    }

    public function test_core_initializes_geo_manager(): void {
        $core = Core::get_instance();
        $this->assertInstanceOf( \Search_Intel\GEO_Manager::class, $core->geo );
    }

    public function test_core_initializes_content_analyzer(): void {
        $core = Core::get_instance();
        $this->assertInstanceOf( \Search_Intel\Content_Analyzer::class, $core->analyzer );
    }

    public function test_core_initializes_sitemap(): void {
        $core = Core::get_instance();
        $this->assertInstanceOf( \Search_Intel\Sitemap::class, $core->sitemap );
    }

    public function test_core_initializes_title_engine(): void {
        $core = Core::get_instance();
        $this->assertInstanceOf( \Search_Intel\Title_Engine::class, $core->title_engine );
    }

    // ── Settings merge ──────────────────────────────────────────────────

    public function test_get_settings_merges_saved_with_defaults(): void {
        global $si_test_state;
        $si_test_state['options']['search_intel_settings'] = [
            'separator' => '-',
        ];

        $core     = Core::get_instance();
        $settings = $core->get_settings();

        $this->assertEquals( '-', $settings['separator'] );
        $this->assertTrue( $settings['enable_seo'] ); // default
    }

    public function test_get_settings_returns_defaults_when_no_saved(): void {
        $core     = Core::get_instance();
        $settings = $core->get_settings();

        $defaults = Core::get_default_settings();
        $this->assertEquals( $defaults['separator'], $settings['separator'] );
    }
}
