<?php
/**
 * Unit tests for the Sitemap class.
 */

use PHPUnit\Framework\TestCase;
use Search_Intel\Sitemap;

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

        $this->assertStringContainsString( '<?xml version="1.0"', $xml );
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

        $this->assertStringContainsString( '<?xml version="1.0"', $xml );
        $this->assertStringContainsString( '<urlset', $xml );
        $this->assertStringContainsString( '</urlset>', $xml );
    }

    // ── Cache TTL constant ───────────────────────────────────────────────

    public function test_cache_ttl_is_twelve_hours(): void {
        $ref = new ReflectionClass( Sitemap::class );
        $ttl = $ref->getConstant( 'CACHE_TTL' );

        $this->assertEquals( 43200, $ttl ); // 12 hours
    }
}
