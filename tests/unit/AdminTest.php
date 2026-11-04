<?php
/**
 * Unit tests for the Admin class.
 */

use PHPUnit\Framework\TestCase;
use JAT_Search_Intel\Admin;
use JAT_Search_Intel\Core;

class AdminTest extends TestCase {

    private function make_admin( array $overrides = [] ): Admin {
        $settings = array_merge( Core::get_default_settings(), $overrides );

        // Bypass private constructor — Admin only stores the reference
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
                'post_title'        => 'Admin Test Post',
                'post_content'      => '<p>Content for admin testing.</p>',
                'post_excerpt'      => '',
                'post_author'       => 1,
                'post_type'         => 'post',
                'post_date_gmt'     => gmdate( 'Y-m-d H:i:s' ),
                'post_modified_gmt' => gmdate( 'Y-m-d H:i:s' ),
            ],
        ];
    }

    // ── Hooks registration ───────────────────────────────────────────────

    public function test_admin_registers_menu_hook(): void {
        $this->make_admin();
        $this->assertArrayHasKey( 'admin_menu', $GLOBALS['si_test_state']['actions'] );
    }

    public function test_admin_registers_settings_hook(): void {
        $this->make_admin();
        $this->assertArrayHasKey( 'admin_init', $GLOBALS['si_test_state']['actions'] );
    }

    public function test_admin_registers_metabox_hooks(): void {
        $this->make_admin();
        $this->assertArrayHasKey( 'add_meta_boxes', $GLOBALS['si_test_state']['actions'] );
        $this->assertArrayHasKey( 'save_post', $GLOBALS['si_test_state']['actions'] );
    }

    public function test_admin_registers_column_filters(): void {
        $this->make_admin();
        $this->assertArrayHasKey( 'manage_posts_columns', $GLOBALS['si_test_state']['filters'] );
        $this->assertArrayHasKey( 'manage_pages_columns', $GLOBALS['si_test_state']['filters'] );
    }

    public function test_admin_registers_user_profile_hooks(): void {
        $this->make_admin();
        $this->assertArrayHasKey( 'show_user_profile', $GLOBALS['si_test_state']['actions'] );
        $this->assertArrayHasKey( 'edit_user_profile', $GLOBALS['si_test_state']['actions'] );
        $this->assertArrayHasKey( 'personal_options_update', $GLOBALS['si_test_state']['actions'] );
        $this->assertArrayHasKey( 'edit_user_profile_update', $GLOBALS['si_test_state']['actions'] );
    }

    // ── Settings sanitization ────────────────────────────────────────────

    public function test_sanitize_settings_bool_keys(): void {
        $admin = $this->make_admin();
        $result = $admin->sanitize_settings( [
            'enable_seo' => '1',
            'enable_og'  => '',
        ] );

        $this->assertTrue( $result['enable_seo'] );
        $this->assertFalse( $result['enable_og'] );
    }

    public function test_sanitize_settings_text_keys(): void {
        $admin = $this->make_admin();
        $result = $admin->sanitize_settings( [
            'separator' => '|',
            'site_name' => '<script>alert(1)</script>My Site',
        ] );

        $this->assertEquals( '|', $result['separator'] );
        $this->assertStringNotContainsString( '<script>', $result['site_name'] );
        $this->assertStringContainsString( 'My Site', $result['site_name'] );
    }

    public function test_sanitize_settings_post_types_array(): void {
        $admin = $this->make_admin();
        $result = $admin->sanitize_settings( [
            'enabled_post_types' => [ 'post', 'page', 'product' ],
        ] );

        $this->assertEquals( [ 'post', 'page', 'product' ], $result['enabled_post_types'] );
    }

    public function test_sanitize_settings_numeric_fields(): void {
        $admin = $this->make_admin();
        $result = $admin->sanitize_settings( [
            'sitemap_max_urls'   => '5000',
            'target_readability' => '70',
        ] );

        $this->assertEquals( 5000, $result['sitemap_max_urls'] );
        $this->assertEquals( 70, $result['target_readability'] );
    }

    public function test_sanitize_settings_speakable_selectors(): void {
        $admin = $this->make_admin();
        $result = $admin->sanitize_settings( [
            'speakable_selectors' => ".entry-content\n.post-title",
        ] );

        $this->assertCount( 2, $result['speakable_selectors'] );
        $this->assertEquals( '.entry-content', $result['speakable_selectors'][0] );
    }

    public function test_sanitize_settings_defaults_on_missing(): void {
        $admin    = $this->make_admin();
        $defaults = Core::get_default_settings();
        $result   = $admin->sanitize_settings( [] );

        $this->assertEquals( $defaults['enabled_post_types'], $result['enabled_post_types'] );
    }

    // ── Social profiles sanitization ─────────────────────────────────────

    public function test_sanitize_social_profiles(): void {
        $admin = $this->make_admin();
        $result = $admin->sanitize_social_profiles( [
            'facebook'  => 'https://facebook.com/test',
            'twitter'   => 'https://twitter.com/test',
            'linkedin'  => 'not-a-url',
        ] );

        $this->assertEquals( 'https://facebook.com/test', $result['facebook'] );
        $this->assertEquals( 'https://twitter.com/test', $result['twitter'] );
    }

    public function test_sanitize_social_profiles_non_array(): void {
        $admin = $this->make_admin();
        $result = $admin->sanitize_social_profiles( 'not-an-array' );

        $this->assertEquals( [], $result );
    }

    // ── Plugin action links ──────────────────────────────────────────────

    public function test_plugin_action_links(): void {
        $admin = $this->make_admin();
        $links = $admin->plugin_action_links( [ '<a href="#">Deactivate</a>' ] );

        $this->assertCount( 2, $links );
        $this->assertStringContainsString( 'Settings', $links[0] );
        $this->assertStringContainsString( 'jat-search-intel', $links[0] );
    }

    // ── Admin columns ────────────────────────────────────────────────────

    public function test_add_admin_columns(): void {
        $admin   = $this->make_admin();
        $columns = $admin->add_admin_columns( [ 'title' => 'Title', 'date' => 'Date' ] );

        $this->assertArrayHasKey( 'si_scores', $columns );
    }

    // ── Save metabox security ────────────────────────────────────────────

    public function test_save_metabox_skips_without_nonce(): void {
        $admin = $this->make_admin();
        $_POST = []; // No nonce

        $admin->save_metabox_data( 1, get_post( 1 ) );

        // No post meta should be set
        $this->assertEmpty( get_post_meta( 1, '_jat_search_intel_title', true ) );
    }

    public function test_save_metabox_saves_text_fields(): void {
        $admin = $this->make_admin();
        $_POST = [
            'jat_search_intel_metabox_nonce' => 'test_nonce',
            'si_title'                   => 'My SEO Title',
            'si_description'             => 'My description',
            'si_focus_keyword'           => 'test keyword',
        ];

        $admin->save_metabox_data( 1, get_post( 1 ) );

        $this->assertEquals( 'My SEO Title', get_post_meta( 1, '_jat_search_intel_title', true ) );
        $this->assertEquals( 'My description', get_post_meta( 1, '_jat_search_intel_description', true ) );
        $this->assertEquals( 'test keyword', get_post_meta( 1, '_jat_search_intel_focus_keyword', true ) );
    }

    public function test_save_metabox_saves_checkboxes(): void {
        $admin = $this->make_admin();
        $_POST = [
            'jat_search_intel_metabox_nonce' => 'test_nonce',
            'si_noindex'                 => '1',
        ];

        $admin->save_metabox_data( 1, get_post( 1 ) );

        $this->assertEquals( '1', get_post_meta( 1, '_jat_search_intel_noindex', true ) );
        $this->assertEquals( '', get_post_meta( 1, '_jat_search_intel_nofollow', true ) );
    }

    public function test_save_metabox_saves_faqs_json(): void {
        $admin = $this->make_admin();
        $_POST = [
            'jat_search_intel_metabox_nonce' => 'test_nonce',
            'si_faqs' => json_encode( [
                [ 'question' => 'What?', 'answer' => 'This.' ],
                [ 'question' => 'Why?', 'answer' => 'Because.' ],
            ] ),
        ];

        $admin->save_metabox_data( 1, get_post( 1 ) );

        $faqs = get_post_meta( 1, '_jat_search_intel_faqs', true );
        $this->assertIsArray( $faqs );
        $this->assertCount( 2, $faqs );
        $this->assertEquals( 'What?', $faqs[0]['question'] );
    }

    public function test_save_metabox_saves_entities_json(): void {
        $admin = $this->make_admin();
        $_POST = [
            'jat_search_intel_metabox_nonce' => 'test_nonce',
            'si_entities' => json_encode( [
                [ 'name' => 'Google', 'type' => 'Organization', 'url' => 'https://google.com' ],
            ] ),
        ];

        $admin->save_metabox_data( 1, get_post( 1 ) );

        $entities = get_post_meta( 1, '_jat_search_intel_entities', true );
        $this->assertIsArray( $entities );
        $this->assertEquals( 'Google', $entities[0]['name'] );
        $this->assertEquals( 'Organization', $entities[0]['type'] );
    }

    public function test_save_metabox_saves_citations_json(): void {
        $admin = $this->make_admin();
        $_POST = [
            'jat_search_intel_metabox_nonce' => 'test_nonce',
            'si_citations' => json_encode( [
                [ 'source' => 'Blog', 'url' => 'https://blog.example.com', 'date' => '2024-01-01', 'quote' => 'Cited.' ],
            ] ),
        ];

        $admin->save_metabox_data( 1, get_post( 1 ) );

        $citations = get_post_meta( 1, '_jat_search_intel_citations', true );
        $this->assertIsArray( $citations );
        $this->assertEquals( 'Blog', $citations[0]['source'] );
    }

    public function test_save_metabox_sanitizes_xss(): void {
        $admin = $this->make_admin();
        $_POST = [
            'jat_search_intel_metabox_nonce' => 'test_nonce',
            'si_title' => '<script>alert("xss")</script>Safe Title',
        ];

        $admin->save_metabox_data( 1, get_post( 1 ) );

        $saved = get_post_meta( 1, '_jat_search_intel_title', true );
        $this->assertStringNotContainsString( '<script>', $saved );
        $this->assertStringContainsString( 'Safe Title', $saved );
    }

    // ── User E-E-A-T fields ─────────────────────────────────────────────

    public function test_save_user_eeat_skips_without_nonce(): void {
        $admin = $this->make_admin();
        $_POST = [
            'si_credentials' => "PhD\nMBA",
        ];

        $admin->save_user_eeat_fields( 1 );

        $this->assertEmpty( get_user_meta( 1, 'jat_search_intel_credentials', true ) );
    }

    public function test_save_user_eeat_saves_credentials(): void {
        $admin = $this->make_admin();
        $_POST = [
            'jat_search_intel_user_eeat_nonce' => 'test_nonce',
            'si_credentials' => "PhD in CS\nMBA",
        ];

        $admin->save_user_eeat_fields( 1 );

        $creds = get_user_meta( 1, 'jat_search_intel_credentials', true );
        $this->assertIsArray( $creds );
        $this->assertCount( 2, $creds );
        $this->assertEquals( 'PhD in CS', $creds[0] );
    }

    public function test_save_user_eeat_saves_experience(): void {
        $admin = $this->make_admin();
        $_POST = [
            'jat_search_intel_user_eeat_nonce' => 'test_nonce',
            'si_years_experience' => '15',
        ];

        $admin->save_user_eeat_fields( 1 );

        $this->assertEquals( 15, get_user_meta( 1, 'jat_search_intel_years_experience', true ) );
    }

    public function test_save_user_eeat_saves_social_profiles(): void {
        $admin = $this->make_admin();
        $_POST = [
            'jat_search_intel_user_eeat_nonce' => 'test_nonce',
            'si_social_profiles' => "https://linkedin.com/in/test\nhttps://github.com/test",
        ];

        $admin->save_user_eeat_fields( 1 );

        $profiles = get_user_meta( 1, 'jat_search_intel_social_profiles', true );
        $this->assertIsArray( $profiles );
        $this->assertCount( 2, $profiles );
    }
}
