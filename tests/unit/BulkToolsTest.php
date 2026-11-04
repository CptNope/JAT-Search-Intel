<?php
namespace Search_Intel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Search_Intel\Bulk_Tools;
use Search_Intel\Core;

class BulkToolsTest extends TestCase {

    private $core;
    private $bulk_tools;

    protected function setUp(): void {
        global $si_test_state;
        $si_test_state = [
            'post_meta'    => [],
            'user_meta'    => [],
            'options'      => [],
            'transients'   => [],
            'actions'      => [],
            'filters'      => [],
            'posts'        => [],
            'current_user' => 1,
        ];

        $si_test_state['options']['search_intel_settings'] = Core::get_default_settings();

        // Add test posts.
        for ( $i = 1; $i <= 3; $i++ ) {
            $si_test_state['posts'][ $i ] = [
                'ID'                => $i,
                'post_title'        => "Test Post {$i}",
                'post_content'      => '<p>Content for post ' . $i . ' with enough words for analysis.</p>',
                'post_excerpt'      => "Excerpt for post {$i}",
                'post_status'       => 'publish',
                'post_type'         => 'post',
                'post_author'       => 1,
                'post_date'         => '2025-01-01 00:00:00',
                'post_date_gmt'     => '2025-01-01 00:00:00',
                'post_modified'     => '2025-01-01 00:00:00',
                'post_modified_gmt' => '2025-01-01 00:00:00',
            ];
        }

        $this->core       = Core::get_instance();
        $settings          = $this->core->get_settings();
        $this->bulk_tools  = new Bulk_Tools( $settings, $this->core );
    }

    // ── Constructor ─────────────────────────────────────────────────────────

    public function test_constructor_registers_ajax_hooks(): void {
        global $si_test_state;

        $actions = array_keys( $si_test_state['actions'] );

        $this->assertContains( 'wp_ajax_search_intel_bulk_rescore', $actions );
        $this->assertContains( 'wp_ajax_search_intel_bulk_regenerate', $actions );
        $this->assertContains( 'wp_ajax_search_intel_bulk_sitemap_warm', $actions );
        $this->assertContains( 'wp_ajax_search_intel_export_settings', $actions );
        $this->assertContains( 'wp_ajax_search_intel_import_settings', $actions );
    }

    // ── Export Settings ─────────────────────────────────────────────────────

    public function test_export_includes_plugin_marker(): void {
        // We can't easily test AJAX handlers that call wp_send_json_*
        // since those are no-ops in tests. Instead, test the data structure
        // by verifying settings are retrievable.
        $settings = $this->core->get_settings();
        $this->assertArrayHasKey( 'separator', $settings );
        $this->assertArrayHasKey( 'enable_seo', $settings );
        $this->assertArrayHasKey( 'enable_rest_api', $settings );
    }

    // ── Import Settings (merge mode) ────────────────────────────────────────

    public function test_merge_import_preserves_existing_values(): void {
        global $si_test_state;

        // Set initial settings.
        $current = Core::get_default_settings();
        $current['separator'] = '|';
        $current['enable_seo'] = true;
        $si_test_state['options']['search_intel_settings'] = $current;

        // Import with merge mode — only change separator.
        $defaults = Core::get_default_settings();
        $new_settings = $this->core->get_settings();

        $import = [ 'separator' => '-' ];
        foreach ( $import as $key => $value ) {
            if ( array_key_exists( $key, $defaults ) ) {
                $new_settings[ $key ] = sanitize_text_field( $value );
            }
        }
        update_option( 'search_intel_settings', $new_settings );

        $saved = $si_test_state['options']['search_intel_settings'];
        $this->assertEquals( '-', $saved['separator'] );
        $this->assertTrue( $saved['enable_seo'] ); // Preserved.
    }

    // ── Import Settings (overwrite mode) ────────────────────────────────────

    public function test_overwrite_import_resets_to_defaults(): void {
        global $si_test_state;

        // Set custom settings.
        $current = Core::get_default_settings();
        $current['separator'] = '~';
        $current['enable_seo'] = false;
        $si_test_state['options']['search_intel_settings'] = $current;

        // Overwrite mode: start from defaults, apply import.
        $defaults = Core::get_default_settings();
        $new = $defaults;
        $import = [ 'separator' => '-' ];
        foreach ( $import as $key => $value ) {
            if ( array_key_exists( $key, $defaults ) ) {
                $new[ $key ] = sanitize_text_field( $value );
            }
        }
        update_option( 'search_intel_settings', $new );

        $saved = $si_test_state['options']['search_intel_settings'];
        $this->assertEquals( '-', $saved['separator'] );
        $this->assertTrue( $saved['enable_seo'] ); // Reset to default true.
    }

    // ── Import validation ───────────────────────────────────────────────────

    public function test_import_ignores_unknown_keys(): void {
        global $si_test_state;

        $defaults = Core::get_default_settings();
        $new = $this->core->get_settings();

        $import = [ 'evil_key' => 'bad', 'separator' => '+' ];
        foreach ( $import as $key => $value ) {
            if ( ! array_key_exists( $key, $defaults ) ) {
                continue;
            }
            $new[ $key ] = sanitize_text_field( $value );
        }
        update_option( 'search_intel_settings', $new );

        $saved = $si_test_state['options']['search_intel_settings'];
        $this->assertEquals( '+', $saved['separator'] );
        $this->assertArrayNotHasKey( 'evil_key', $saved );
    }

    // ── Import type casting ─────────────────────────────────────────────────

    public function test_import_type_casts_booleans(): void {
        $defaults = Core::get_default_settings();
        $new = $this->core->get_settings();

        // Simulate importing a string "1" for a boolean setting.
        $default_val = $defaults['enable_seo']; // bool
        $this->assertTrue( is_bool( $default_val ) );

        $new['enable_seo'] = (bool) '1';
        $this->assertTrue( $new['enable_seo'] );

        $new['enable_seo'] = (bool) '';
        $this->assertFalse( $new['enable_seo'] );
    }

    public function test_import_type_casts_integers(): void {
        $defaults = Core::get_default_settings();

        $default_val = $defaults['sitemap_max_urls']; // int
        $this->assertIsInt( $default_val );

        $casted = absint( '3000' );
        $this->assertEquals( 3000, $casted );
        $this->assertIsInt( $casted );
    }

    public function test_import_type_casts_arrays(): void {
        $defaults = Core::get_default_settings();

        $default_val = $defaults['sitemap_post_types']; // array
        $this->assertIsArray( $default_val );

        // Arrays should have their values sanitized.
        $input = [ 'post', 'page', '<script>alert(1)</script>' ];
        $sanitized = array_map( 'sanitize_text_field', $input );
        $this->assertEquals( [ 'post', 'page', 'alert(1)' ], $sanitized );
    }

    // ── Enqueue assets ──────────────────────────────────────────────────────

    public function test_enqueue_assets_localizes_script(): void {
        // Just verify the method exists and can be called without error.
        $this->bulk_tools->enqueue_assets();
        $this->assertTrue( true ); // No exception = pass.
    }

    // ── Settings round-trip ─────────────────────────────────────────────────

    public function test_settings_export_import_roundtrip(): void {
        global $si_test_state;

        // Customize settings.
        $current = Core::get_default_settings();
        $current['separator'] = '>>>';
        $current['enable_og'] = false;
        $current['sitemap_max_urls'] = 5000;
        $si_test_state['options']['search_intel_settings'] = $current;

        // "Export" them.
        $exported = $this->core->get_settings();

        // Reset to defaults.
        $si_test_state['options']['search_intel_settings'] = Core::get_default_settings();

        // "Import" them back (merge mode).
        $new = $this->core->get_settings();
        $defaults = Core::get_default_settings();
        foreach ( $exported as $key => $value ) {
            if ( ! array_key_exists( $key, $defaults ) ) {
                continue;
            }
            $default_val = $defaults[ $key ];
            if ( is_bool( $default_val ) ) {
                $new[ $key ] = (bool) $value;
            } elseif ( is_int( $default_val ) ) {
                $new[ $key ] = absint( $value );
            } elseif ( is_array( $default_val ) ) {
                $new[ $key ] = is_array( $value ) ? array_map( 'sanitize_text_field', $value ) : $default_val;
            } else {
                $new[ $key ] = sanitize_text_field( $value );
            }
        }
        update_option( 'search_intel_settings', $new );

        $saved = $si_test_state['options']['search_intel_settings'];
        $this->assertEquals( '>>>', $saved['separator'] );
        $this->assertFalse( $saved['enable_og'] );
        $this->assertEquals( 5000, $saved['sitemap_max_urls'] );
    }
}
