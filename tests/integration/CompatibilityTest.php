<?php
/**
 * Compatibility QA tests for Search Intel.
 *
 * Verifies defensive behaviour with conflicting plugins, various
 * editors, WooCommerce-style post types, and edge-case content.
 */

use PHPUnit\Framework\TestCase;
use Search_Intel\Core;
use Search_Intel\SEO_Manager;
use Search_Intel\Schema_Manager;
use Search_Intel\AEO_Manager;
use Search_Intel\GEO_Manager;
use Search_Intel\Content_Analyzer;
use Search_Intel\Sitemap;

class CompatibilityTest extends TestCase {

    protected function setUp(): void {
        global $si_test_state;
        $si_test_state['post_meta']      = [];
        $si_test_state['user_meta']      = [];
        $si_test_state['options']        = [];
        $si_test_state['actions']        = [];
        $si_test_state['filters']        = [];
        $si_test_state['transients']     = [];
        $si_test_state['active_plugins'] = [];
        $si_test_state['posts']          = [
            1 => [
                'ID'                => 1,
                'post_title'        => 'Compatibility Test Post',
                'post_content'      => '<p>Some content for compatibility testing.</p>',
                'post_excerpt'      => 'Excerpt.',
                'post_author'       => 1,
                'post_type'         => 'post',
                'post_status'       => 'publish',
                'post_date_gmt'     => '2026-01-15 10:00:00',
                'post_modified_gmt' => '2026-02-20 14:30:00',
            ],
        ];
    }

    // ── Conflicting Plugin Detection ────────────────────────────────────

    public function test_no_conflicts_detected_when_clean(): void {
        $core = $this->make_core();

        $ref = new ReflectionMethod( $core, 'get_conflicting_plugins' );
        $ref->setAccessible( true );

        $this->assertEmpty( $ref->invoke( $core ) );
    }

    public function test_yoast_conflict_detected(): void {
        global $si_test_state;
        $si_test_state['active_plugins'] = [ 'wordpress-seo/wp-seo.php' ];

        $core = $this->make_core();

        $ref = new ReflectionMethod( $core, 'get_conflicting_plugins' );
        $ref->setAccessible( true );

        $conflicts = $ref->invoke( $core );
        $this->assertContains( 'Yoast SEO', $conflicts );
    }

    public function test_rank_math_conflict_detected(): void {
        global $si_test_state;
        $si_test_state['active_plugins'] = [ 'seo-by-rank-math/rank-math.php' ];

        $core = $this->make_core();

        $ref = new ReflectionMethod( $core, 'get_conflicting_plugins' );
        $ref->setAccessible( true );

        $conflicts = $ref->invoke( $core );
        $this->assertContains( 'Rank Math', $conflicts );
    }

    public function test_aioseo_conflict_detected(): void {
        global $si_test_state;
        $si_test_state['active_plugins'] = [ 'all-in-one-seo-pack/all_in_one_seo_pack.php' ];

        $core = $this->make_core();

        $ref = new ReflectionMethod( $core, 'get_conflicting_plugins' );
        $ref->setAccessible( true );

        $conflicts = $ref->invoke( $core );
        $this->assertContains( 'All in One SEO', $conflicts );
    }

    public function test_multiple_conflicts_detected(): void {
        global $si_test_state;
        $si_test_state['active_plugins'] = [
            'wordpress-seo/wp-seo.php',
            'seo-by-rank-math/rank-math.php',
        ];

        $core = $this->make_core();

        $ref = new ReflectionMethod( $core, 'get_conflicting_plugins' );
        $ref->setAccessible( true );

        $conflicts = $ref->invoke( $core );
        $this->assertCount( 2, $conflicts );
    }

    // ── Prefetch Hook Registration ──────────────────────────────────────

    public function test_prefetch_hook_registered(): void {
        global $si_test_state;
        $si_test_state['actions'] = [];

        $core = $this->make_core();

        $this->assertArrayHasKey( 'wp', $si_test_state['actions'] );
    }

    public function test_compatibility_check_hook_registered(): void {
        global $si_test_state;
        $si_test_state['actions'] = [];

        $core = $this->make_core();

        $this->assertArrayHasKey( 'admin_init', $si_test_state['actions'] );
    }

    // ── Empty / Minimal Content Handling ────────────────────────────────

    public function test_seo_handles_empty_content(): void {
        global $si_test_state;
        $si_test_state['posts'][1]['post_content'] = '';
        $si_test_state['posts'][1]['post_excerpt']  = '';

        $seo = new SEO_Manager( Core::get_default_settings() );
        ob_start();
        $seo->render_meta_tags();
        $output = ob_get_clean();

        // Should not fatal; may output site description fallback
        $this->assertIsString( $output );
    }

    public function test_schema_handles_empty_content(): void {
        global $si_test_state;
        $si_test_state['posts'][1]['post_content'] = '';

        $schema = new Schema_Manager( Core::get_default_settings() );
        $result = $schema->get_singular_schema( 1 );

        $this->assertArrayHasKey( '@type', $result );
        $this->assertArrayHasKey( 'headline', $result );
    }

    public function test_analyzer_handles_empty_content(): void {
        $analyzer = new Content_Analyzer( Core::get_default_settings() );
        $result   = $analyzer->analyze( 1, '', 'Title', 'keyword', 'desc' );

        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'seo_score', $result );
        $this->assertEquals( 0, $result['word_count'] );
    }

    public function test_geo_handles_empty_content(): void {
        global $si_test_state;
        $si_test_state['posts'][1]['post_content'] = '';

        $geo    = new GEO_Manager( Core::get_default_settings() );
        $result = $geo->get_geo_score( 1 );

        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'score', $result );
    }

    // ── WooCommerce-style Custom Post Types ─────────────────────────────

    public function test_schema_handles_product_post_type(): void {
        global $si_test_state;
        $si_test_state['posts'][2] = [
            'ID'                => 2,
            'post_title'        => 'Test Product',
            'post_content'      => '<p>Product description with details.</p>',
            'post_excerpt'      => 'Product short description.',
            'post_author'       => 1,
            'post_type'         => 'product',
            'post_status'       => 'publish',
            'post_date_gmt'     => '2026-01-10 08:00:00',
            'post_modified_gmt' => '2026-02-15 12:00:00',
        ];

        $schema = new Schema_Manager( Core::get_default_settings() );
        $result = $schema->get_singular_schema( 2 );

        // Product should default to WebPage, not Article
        $this->assertEquals( 'WebPage', $result['@type'] );
    }

    public function test_analyzer_works_on_custom_post_type(): void {
        global $si_test_state;
        $si_test_state['posts'][2] = [
            'ID'                => 2,
            'post_title'        => 'Custom CPT',
            'post_content'      => '<p>Custom post type content for compatibility.</p>',
            'post_excerpt'      => '',
            'post_author'       => 1,
            'post_type'         => 'portfolio',
            'post_status'       => 'publish',
            'post_date_gmt'     => '2026-01-10 08:00:00',
            'post_modified_gmt' => '2026-02-15 12:00:00',
        ];

        $analyzer = new Content_Analyzer( Core::get_default_settings() );
        $result   = $analyzer->analyze( 2, '<p>Custom post type content.</p>', 'Custom CPT', 'custom', '' );

        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'seo_score', $result );
    }

    // ── Page Builder Content (Shortcodes / Raw HTML) ────────────────────

    public function test_analyzer_handles_shortcode_heavy_content(): void {
        $content = '[section][row][col]<p>Content inside page builder shortcodes.</p>[/col][/row][/section]';

        $analyzer = new Content_Analyzer( Core::get_default_settings() );
        $result   = $analyzer->analyze( 1, $content, 'Builder Page', 'builder', '' );

        $this->assertIsArray( $result );
        // Should not fatal even with shortcode-heavy content
        $this->assertGreaterThanOrEqual( 0, $result['word_count'] );
    }

    public function test_schema_handles_shortcode_content(): void {
        global $si_test_state;
        $si_test_state['posts'][1]['post_content'] = '[elementor-template id="123"]<div class="et_builder"><p>Divi content.</p></div>';

        $schema = new Schema_Manager( Core::get_default_settings() );
        $result = $schema->get_singular_schema( 1 );

        $this->assertArrayHasKey( 'description', $result );
        $this->assertIsString( $result['description'] );
    }

    // ── Unicode / Multibyte Content ─────────────────────────────────────

    public function test_seo_handles_unicode_description(): void {
        global $si_test_state;
        $si_test_state['post_meta'][1]['_search_intel_description'] = '这是一个中文描述，用于测试多字节字符处理。';

        $seo = new SEO_Manager( Core::get_default_settings() );
        ob_start();
        $seo->render_meta_tags();
        $output = ob_get_clean();

        $this->assertStringContainsString( '中文描述', $output );
    }

    public function test_seo_handles_unicode_title(): void {
        global $si_test_state;
        $si_test_state['post_meta'][1]['_search_intel_title'] = '日本語のタイトル — ユニコード対応';

        $seo   = new SEO_Manager( Core::get_default_settings() );
        $title = $seo->get_title( 1 );

        $this->assertStringContainsString( '日本語', $title );
    }

    public function test_schema_handles_unicode_title(): void {
        global $si_test_state;
        $si_test_state['posts'][1]['post_title'] = 'Ünïcödé Tëst Pöst — Spëcïäl Chàrs';

        $schema = new Schema_Manager( Core::get_default_settings() );
        $result = $schema->get_singular_schema( 1 );

        $this->assertStringContainsString( 'Ünïcödé', $result['headline'] );
    }

    // ── Very Long Content ───────────────────────────────────────────────

    public function test_analyzer_handles_very_long_content(): void {
        $content = str_repeat( '<p>' . str_repeat( 'word ', 100 ) . '</p>', 50 ); // ~5000 words

        $analyzer = new Content_Analyzer( Core::get_default_settings() );
        $result   = $analyzer->analyze( 1, $content, 'Long Post', 'long', '' );

        $this->assertIsArray( $result );
        $this->assertGreaterThan( 4000, $result['word_count'] );
    }

    // ── Custom Schema Type Override ─────────────────────────────────────

    public function test_custom_schema_type_overrides_default(): void {
        global $si_test_state;
        $si_test_state['post_meta'][1]['_search_intel_schema_type'] = 'TechArticle';

        $schema = new Schema_Manager( Core::get_default_settings() );
        $result = $schema->get_singular_schema( 1 );

        $this->assertEquals( 'TechArticle', $result['@type'] );
    }

    // ── Settings Defaults Are Valid ─────────────────────────────────────

    public function test_default_settings_contain_all_required_keys(): void {
        $defaults = Core::get_default_settings();

        $required = [
            'enable_seo', 'enable_og', 'enable_twitter', 'enable_canonical',
            'enable_schema', 'enable_aeo', 'enable_geo', 'enable_sitemap',
            'enable_analyzer', 'enabled_post_types', 'separator', 'site_name',
        ];

        foreach ( $required as $key ) {
            $this->assertArrayHasKey( $key, $defaults, "Missing default setting: {$key}" );
        }
    }

    public function test_default_post_types_include_post_and_page(): void {
        $defaults = Core::get_default_settings();
        $this->assertContains( 'post', $defaults['enabled_post_types'] );
        $this->assertContains( 'page', $defaults['enabled_post_types'] );
    }

    // ── Disabled Module Safety ──────────────────────────────────────────

    public function test_all_modules_disabled_produces_no_output(): void {
        $settings = Core::get_default_settings();
        $settings['enable_seo']    = false;
        $settings['enable_schema'] = false;
        $settings['enable_aeo']    = false;
        $settings['enable_geo']    = false;

        $seo    = new SEO_Manager( $settings );
        $schema = new Schema_Manager( $settings );
        $aeo    = new AEO_Manager( $settings );
        $geo    = new GEO_Manager( $settings );

        // None should register wp_head hooks
        global $si_test_state;
        $wp_head_hooks = $si_test_state['actions']['wp_head'] ?? [];

        // Verify no render methods were hooked
        $has_render = false;
        foreach ( $wp_head_hooks as $hook ) {
            $cb = $hook['callback'];
            if ( is_array( $cb ) && is_object( $cb[0] ) ) {
                $class = get_class( $cb[0] );
                if ( in_array( $class, [ SEO_Manager::class, Schema_Manager::class, AEO_Manager::class, GEO_Manager::class ], true ) ) {
                    $has_render = true;
                    break;
                }
            }
        }

        $this->assertFalse( $has_render, 'No render hooks should be registered when all modules disabled' );
    }

    // ── Sitemap Disabled Gracefully ─────────────────────────────────────

    public function test_sitemap_disabled_registers_no_hooks(): void {
        global $si_test_state;
        $si_test_state['actions'] = [];

        $settings = Core::get_default_settings();
        $settings['enable_sitemap'] = false;

        new Sitemap( $settings );

        $this->assertArrayNotHasKey( 'template_redirect', $si_test_state['actions'] );
    }

    // ── Helper ──────────────────────────────────────────────────────────

    private function make_core(): Core {
        // Reset singleton
        $ref  = new ReflectionClass( Core::class );
        $prop = $ref->getProperty( 'instance' );
        $prop->setAccessible( true );
        $prop->setValue( null, null );

        return Core::get_instance();
    }
}
