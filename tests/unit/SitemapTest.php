<?php
/**
 * Unit tests for the Sitemap class.
 */

use PHPUnit\Framework\TestCase;
use JAT_Search_Intel\Sitemap;

class SitemapTest extends TestCase {

    private function make_sitemap( array $overrides = [] ): Sitemap {
        $settings = array_merge( [
            'enable_sitemap'       => true,
            'sitemap_post_types'   => [ 'post', 'page' ],
            'sitemap_taxonomies'   => [ 'category' ],
            'sitemap_max_urls'     => 2000,
            'ping_search_engines'  => false,
        ], $overrides );

        return new Sitemap( $settings );
    }

    protected function setUp(): void {
        global $si_test_state;
        $si_test_state['actions']    = [];
        $si_test_state['transients'] = [];
    }

    // ── Constructor / hooks ──────────────────────────────────────────────

    public function test_registers_rewrite_hooks_when_enabled(): void {
        $this->make_sitemap();

        $this->assertArrayHasKey( 'init', $GLOBALS['si_test_state']['actions'] );
    }

    public function test_no_hooks_when_disabled(): void {
        global $si_test_state;
        $si_test_state['actions'] = [];

        $this->make_sitemap( [ 'enable_sitemap' => false ] );

        $this->assertArrayNotHasKey( 'init', $si_test_state['actions'] );
        $this->assertArrayNotHasKey( 'template_redirect', $si_test_state['actions'] );
    }

    public function test_legacy_ping_setting_registers_no_hooks(): void {
        // Google and Bing retired their sitemap ping endpoints; the legacy
        // setting must not trigger any outbound requests.
        $this->make_sitemap( [ 'ping_search_engines' => true ] );

        $this->assertArrayNotHasKey( 'publish_post', $GLOBALS['si_test_state']['actions'] );
        $this->assertArrayNotHasKey( 'publish_page', $GLOBALS['si_test_state']['actions'] );
    }

    public function test_cache_invalidation_hooks_registered(): void {
        $this->make_sitemap();

        $this->assertArrayHasKey( 'save_post', $GLOBALS['si_test_state']['actions'] );
        $this->assertArrayHasKey( 'delete_post', $GLOBALS['si_test_state']['actions'] );
        $this->assertArrayHasKey( 'created_term', $GLOBALS['si_test_state']['actions'] );
        $this->assertArrayHasKey( 'edited_term', $GLOBALS['si_test_state']['actions'] );
        $this->assertArrayHasKey( 'delete_term', $GLOBALS['si_test_state']['actions'] );
    }

    // ── Sitemap index generation ─────────────────────────────────────────

    public function test_generate_sitemap_index_returns_xml(): void {
        $sitemap = $this->make_sitemap();
        $method  = new ReflectionMethod( $sitemap, 'generate_sitemap_index' );
        $method->setAccessible( true );
        $xml = $method->invoke( $sitemap );

        $this->assertStringStartsWith( '<?xml version="1.0"', $xml );
        $this->assertStringContainsString( '<sitemapindex', $xml );
        $this->assertStringContainsString( '</sitemapindex>', $xml );
    }

    public function test_sitemap_index_includes_post_type_sitemaps(): void {
        $sitemap = $this->make_sitemap( [ 'sitemap_post_types' => [ 'post', 'page' ] ] );
        $method  = new ReflectionMethod( $sitemap, 'generate_sitemap_index' );
        $method->setAccessible( true );
        $xml = $method->invoke( $sitemap );

        $this->assertStringContainsString( 'sitemap-post', $xml );
        $this->assertStringContainsString( 'sitemap-page', $xml );
    }

    public function test_sitemap_index_includes_taxonomy_sitemaps(): void {
        $sitemap = $this->make_sitemap( [ 'sitemap_taxonomies' => [ 'category' ] ] );
        $method  = new ReflectionMethod( $sitemap, 'generate_sitemap_index' );
        $method->setAccessible( true );
        $xml = $method->invoke( $sitemap );

        // category taxonomy may not have terms in test env, but the structure should be valid
        $this->assertStringContainsString( '<sitemapindex', $xml );
    }

    // ── Sitemap generation ───────────────────────────────────────────────

    public function test_generate_sitemap_returns_valid_xml(): void {
        $sitemap = $this->make_sitemap();
        $method  = new ReflectionMethod( $sitemap, 'generate_sitemap' );
        $method->setAccessible( true );
        $xml = $method->invoke( $sitemap, 'post', 1 );

        $this->assertStringStartsWith( '<?xml version="1.0"', $xml );
        $this->assertStringContainsString( '<urlset', $xml );
        $this->assertStringContainsString( '</urlset>', $xml );
    }

    // ── Cache TTL constant ───────────────────────────────────────────────

    public function test_cache_ttl_is_twelve_hours(): void {
        $ref = new ReflectionClass( Sitemap::class );
        $ttl = $ref->getConstant( 'CACHE_TTL' );

        $this->assertEquals( 43200, $ttl ); // 12 hours
    }

    // ── RSS sitemap ──────────────────────────────────────────────────────

    public function test_rss_sitemap_lists_recent_posts(): void {
        global $si_test_state;
        $si_test_state['posts'] = [
            3 => [ 'ID' => 3, 'post_title' => 'Scam guide', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'post', 'post_author' => 1, 'post_modified_gmt' => '2026-11-15 22:00:00' ],
        ];

        $xml = $this->make_sitemap( [ 'enable_rss_sitemap' => true ] )->generate_rss_sitemap();

        $this->assertStringContainsString( '<rss version="2.0"', $xml );
        $this->assertStringContainsString( '<title>Scam guide</title>', $xml );
        $this->assertStringContainsString( 'sitemap.rss', $xml );
        $this->assertNotFalse( simplexml_load_string( $xml ) );
    }

    public function test_robots_lists_rss_sitemap_when_enabled(): void {
        $on  = $this->make_sitemap( [ 'enable_rss_sitemap' => true ] )->add_sitemap_to_robots( '', true );
        $off = $this->make_sitemap( [ 'enable_rss_sitemap' => false ] )->add_sitemap_to_robots( '', true );

        $this->assertStringContainsString( 'sitemap.rss', $on );
        $this->assertStringNotContainsString( 'sitemap.rss', $off );
    }

    public function test_own_url_skips_links_to_a_section_of_another_page(): void {
        global $si_test_state;
        $si_test_state['permalinks'][6] = 'https://example.com/services/#audit';

        $this->assertSame( 'https://example.com/?p=5', \JAT_Search_Intel\Sitemap::own_url( 5 ) );
        $this->assertSame( '', \JAT_Search_Intel\Sitemap::own_url( 6 ) );
        unset( $si_test_state['permalinks'] );
    }

    public function test_cache_key_changes_when_sitemap_post_types_change(): void {
        $hash = static function ( array $types ): string {
            $sitemap = new \JAT_Search_Intel\Sitemap( [ 'enable_sitemap' => true, 'sitemap_post_types' => $types ] );
            $method  = new \ReflectionMethod( $sitemap, 'settings_hash' );
            $method->setAccessible( true );
            return (string) $method->invoke( $sitemap );
        };
        $this->assertNotSame( $hash( [ 'post', 'page' ] ), $hash( [ 'post', 'page', 'project' ] ) );
        $this->assertSame( $hash( [ 'post' ] ), $hash( [ 'post' ] ) );
    }
}
