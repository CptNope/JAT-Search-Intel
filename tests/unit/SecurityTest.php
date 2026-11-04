<?php
/**
 * Security audit regression tests.
 * Verifies that all security hardening from v1.2 audit is in place.
 */

use PHPUnit\Framework\TestCase;
use Search_Intel\Admin;
use Search_Intel\Content_Analyzer;
use Search_Intel\Core;
use Search_Intel\GEO_Manager;

class SecurityTest extends TestCase {

    private function make_admin(): Admin {
        $settings = Core::get_default_settings();
        $ref  = new ReflectionClass( Core::class );
        $core = $ref->newInstanceWithoutConstructor();
        return new Admin( $settings, $core );
    }

    protected function setUp(): void {
        global $si_test_state;
        $si_test_state['post_meta']  = [];
        $si_test_state['user_meta']  = [];
        $si_test_state['actions']    = [];
        $si_test_state['filters']    = [];
        $si_test_state['transients'] = [];
        $si_test_state['posts']      = [
            1 => [
                'ID'                => 1,
                'post_title'        => 'Security Test',
                'post_content'      => '<p>Security content.</p>',
                'post_excerpt'      => '',
                'post_author'       => 1,
                'post_type'         => 'post',
                'post_date_gmt'     => gmdate( 'Y-m-d H:i:s' ),
                'post_modified_gmt' => gmdate( 'Y-m-d H:i:s' ),
            ],
        ];
    }

    // ── Nonce verification ───────────────────────────────────────────────

    public function test_metabox_save_requires_nonce(): void {
        $admin = $this->make_admin();
        $_POST = [ 'si_title' => 'Should Not Save' ];

        $admin->save_metabox_data( 1, get_post( 1 ) );

        $this->assertEmpty( get_post_meta( 1, '_search_intel_title', true ) );
    }

    public function test_user_eeat_save_requires_nonce(): void {
        $admin = $this->make_admin();
        $_POST = [ 'si_credentials' => 'PhD' ];

        $admin->save_user_eeat_fields( 1 );

        $this->assertEmpty( get_user_meta( 1, 'search_intel_credentials', true ) );
    }

    // ── Input sanitization ──────────────────────────────────────────────

    public function test_metabox_title_strips_html(): void {
        $admin = $this->make_admin();
        $_POST = [
            'search_intel_metabox_nonce' => 'test_nonce',
            'si_title' => '<img src=x onerror=alert(1)>Safe Title',
        ];

        $admin->save_metabox_data( 1, get_post( 1 ) );

        $saved = get_post_meta( 1, '_search_intel_title', true );
        $this->assertStringNotContainsString( '<img', $saved );
        $this->assertStringNotContainsString( 'onerror', $saved );
    }

    public function test_metabox_description_strips_scripts(): void {
        $admin = $this->make_admin();
        $_POST = [
            'search_intel_metabox_nonce' => 'test_nonce',
            'si_description' => '<script>document.cookie</script>Clean desc',
        ];

        $admin->save_metabox_data( 1, get_post( 1 ) );

        $saved = get_post_meta( 1, '_search_intel_description', true );
        $this->assertStringNotContainsString( '<script>', $saved );
    }

    public function test_metabox_canonical_uses_esc_url_raw(): void {
        // Verify esc_url_raw is used for canonical in source
        $source = file_get_contents( SEARCH_INTEL_DIR . 'includes/class-admin.php' );
        $this->assertStringContainsString( 'esc_url_raw', $source );
    }

    public function test_settings_sanitize_strips_html_from_text(): void {
        $admin = $this->make_admin();
        $result = $admin->sanitize_settings( [
            'site_name' => '<marquee>Evil</marquee>',
        ] );

        $this->assertStringNotContainsString( '<marquee>', $result['site_name'] );
    }

    public function test_settings_sanitize_boolean_coercion(): void {
        $admin = $this->make_admin();

        $result = $admin->sanitize_settings( [
            'enable_seo' => 'arbitrary_string',
        ] );
        $this->assertTrue( $result['enable_seo'] );

        $result2 = $admin->sanitize_settings( [
            'enable_seo' => '',
        ] );
        $this->assertFalse( $result2['enable_seo'] );
    }

    // ── Entity type whitelist (GEO Manager) ─────────────────────────────

    public function test_entity_type_whitelist_rejects_invalid(): void {
        global $si_test_state;
        $si_test_state['post_meta'][1]['_search_intel_entities'] = [
            [ 'name' => 'Test', 'type' => 'MaliciousType', 'url' => '' ],
        ];

        $mgr    = new GEO_Manager( Core::get_default_settings() );
        $result = $mgr->enhance_content_entities( '<p>Test entity.</p>' );

        // Should fall back to Thing, not use MaliciousType
        $this->assertStringContainsString( 'schema.org/Thing', $result );
        $this->assertStringNotContainsString( 'MaliciousType', $result );
    }

    public function test_entity_type_whitelist_accepts_valid(): void {
        global $si_test_state;
        $si_test_state['post_meta'][1]['_search_intel_entities'] = [
            [ 'name' => 'Google', 'type' => 'Organization', 'url' => '' ],
        ];

        $mgr    = new GEO_Manager( Core::get_default_settings() );
        $result = $mgr->enhance_content_entities( '<p>Google is great.</p>' );

        $this->assertStringContainsString( 'schema.org/Organization', $result );
    }

    // ── Rate limiting (Content Analyzer AJAX) ───────────────────────────

    public function test_rate_limit_transient_key_format(): void {
        // Verify the rate limiting key structure exists in source
        $source = file_get_contents( SEARCH_INTEL_DIR . 'includes/class-content-analyzer.php' );
        $this->assertStringContainsString( 'si_analyze_rate_', $source );
    }

    public function test_capability_check_in_ajax_analyze(): void {
        $source = file_get_contents( SEARCH_INTEL_DIR . 'includes/class-content-analyzer.php' );
        $this->assertStringContainsString( "current_user_can( 'edit_post'", $source );
    }

    // ── SQL safety ──────────────────────────────────────────────────────

    public function test_sitemap_uses_wpdb_prepare(): void {
        $source = file_get_contents( SEARCH_INTEL_DIR . 'includes/class-sitemap.php' );
        $this->assertStringContainsString( '$wpdb->prepare', $source );
    }

    public function test_uninstall_uses_prepared_queries(): void {
        $source = file_get_contents( SEARCH_INTEL_DIR . 'uninstall.php' );
        $this->assertStringContainsString( '$wpdb->prepare', $source );
        $this->assertStringContainsString( '$wpdb->esc_like', $source );
    }

    public function test_uninstall_is_opt_in(): void {
        $source = file_get_contents( SEARCH_INTEL_DIR . 'uninstall.php' );
        $this->assertStringContainsString( 'delete_data_on_uninstall', $source );
        $this->assertStringContainsString( "defined( 'WP_UNINSTALL_PLUGIN' )", $source );
    }

    // ── Output escaping in source ───────────────────────────────────────

    public function test_seo_manager_uses_esc_attr(): void {
        $source = file_get_contents( SEARCH_INTEL_DIR . 'includes/class-seo-manager.php' );
        $this->assertStringContainsString( 'esc_attr', $source );
    }

    public function test_geo_manager_uses_esc_attr(): void {
        $source = file_get_contents( SEARCH_INTEL_DIR . 'includes/class-geo-manager.php' );
        $this->assertStringContainsString( 'esc_attr', $source );
    }

    public function test_schema_manager_uses_wp_json_encode(): void {
        $source = file_get_contents( SEARCH_INTEL_DIR . 'includes/class-schema-manager.php' );
        $this->assertStringContainsString( 'wp_json_encode', $source );
    }

    // ── Cache-control headers in source ─────────────────────────────────

    public function test_sitemap_sets_cache_headers(): void {
        $source = file_get_contents( SEARCH_INTEL_DIR . 'includes/class-sitemap.php' );
        $this->assertStringContainsString( 'Cache-Control', $source );
    }

    public function test_geo_llms_sets_cache_headers(): void {
        $source = file_get_contents( SEARCH_INTEL_DIR . 'includes/class-geo-manager.php' );
        $this->assertStringContainsString( 'Cache-Control', $source );
    }

    // ── Nonce verification in source ────────────────────────────────────

    public function test_content_analyzer_checks_ajax_referer(): void {
        $source = file_get_contents( SEARCH_INTEL_DIR . 'includes/class-content-analyzer.php' );
        $this->assertStringContainsString( 'check_ajax_referer', $source );
    }

    public function test_admin_metabox_verifies_nonce(): void {
        $source = file_get_contents( SEARCH_INTEL_DIR . 'includes/class-admin.php' );
        $this->assertStringContainsString( 'wp_verify_nonce', $source );
        $this->assertStringContainsString( 'search_intel_metabox_nonce', $source );
    }

    public function test_admin_user_eeat_verifies_nonce(): void {
        $source = file_get_contents( SEARCH_INTEL_DIR . 'includes/class-admin.php' );
        $this->assertStringContainsString( 'search_intel_user_eeat_nonce', $source );
    }

    // ── wp_unslash in source ────────────────────────────────────────────

    public function test_admin_uses_wp_unslash(): void {
        $source = file_get_contents( SEARCH_INTEL_DIR . 'includes/class-admin.php' );
        $this->assertStringContainsString( 'wp_unslash', $source );
    }

    public function test_content_analyzer_uses_wp_unslash(): void {
        $source = file_get_contents( SEARCH_INTEL_DIR . 'includes/class-content-analyzer.php' );
        $this->assertStringContainsString( 'wp_unslash', $source );
    }
}
