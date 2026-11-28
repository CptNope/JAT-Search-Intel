<?php
/**
 * Integration tests for Sitemap XML output and llms.txt output.
 */

use PHPUnit\Framework\TestCase;
use JAT_Search_Intel\Sitemap;
use JAT_Search_Intel\Core;

class SitemapOutputTest extends TestCase {

    private function make_sitemap( array $overrides = [] ): Sitemap {
        $settings = array_merge( Core::get_default_settings(), $overrides );
        return new Sitemap( $settings );
    }

    protected function setUp(): void {
        global $si_test_state;
        $si_test_state['post_meta']  = [];
        $si_test_state['options']    = [];
        $si_test_state['actions']    = [];
        $si_test_state['filters']    = [];
        $si_test_state['transients'] = [];
        $si_test_state['posts']      = [
            1 => [
                'ID'                => 1,
                'post_title'        => 'Sitemap Post One',
                'post_content'      => '<p>First post content for sitemap.</p>',
                'post_excerpt'      => '',
                'post_author'       => 1,
                'post_type'         => 'post',
                'post_status'       => 'publish',
                'post_date_gmt'     => '2026-01-10 08:00:00',
                'post_modified_gmt' => '2026-02-15 12:00:00',
            ],
            2 => [
                'ID'                => 2,
                'post_title'        => 'Sitemap Post Two',
                'post_content'      => '<p>Second post content.</p>',
                'post_excerpt'      => '',
                'post_author'       => 1,
                'post_type'         => 'post',
                'post_status'       => 'publish',
                'post_date_gmt'     => '2026-02-05 09:00:00',
                'post_modified_gmt' => '2026-03-01 10:00:00',
            ],
        ];
    }

    // ── Cache invalidation ──────────────────────────────────────────────

    /**
     * With a persistent object cache, transients are not rows in the options table, so the
     * SQL delete in invalidate_cache() removes nothing (the test stub's query() does nothing
     * either). Saving a post must still retire the cached sitemap.
     */
    public function test_invalidate_cache_retires_cached_sitemap_with_object_cache(): void {
        global $si_test_state;
        $sitemap = $this->make_sitemap();
        $hash    = new ReflectionMethod( $sitemap, 'settings_hash' );
        $hash->setAccessible( true );
        $gen     = new ReflectionMethod( $sitemap, 'cache_generation' );
        $gen->setAccessible( true );

        $key_before = 'jat_search_intel_sitemap_post_1_' . $hash->invoke( $sitemap ) . '_' . $gen->invoke( $sitemap );
        $si_test_state['transients'][ $key_before ] = '<urlset>stale</urlset>';

        $sitemap->invalidate_cache();

        $key_after = 'jat_search_intel_sitemap_post_1_' . $hash->invoke( $sitemap ) . '_' . $gen->invoke( $sitemap );
        $this->assertNotSame( $key_before, $key_after );
        $this->assertFalse( get_transient( $key_after ) );

        $sitemap->invalidate_cache();
        $this->assertSame( '2', $gen->invoke( $sitemap ) );
    }

    public function test_saving_a_published_post_retires_cached_sitemaps(): void {
        $sitemap = $this->make_sitemap();
        $gen     = new ReflectionMethod( $sitemap, 'cache_generation' );
        $gen->setAccessible( true );

        $sitemap->invalidate_for_post( 1 );

        $this->assertSame( '1', $gen->invoke( $sitemap ) );
    }

    // ── Sitemap index ───────────────────────────────────────────────────

    public function test_sitemap_index_has_post_sitemap(): void {
        $sitemap = $this->make_sitemap();
        $method  = new ReflectionMethod( $sitemap, 'generate_sitemap_index' );
        $method->setAccessible( true );
        $xml = $method->invoke( $sitemap );

        $this->assertStringContainsString( 'sitemap-post.xml', $xml );
    }

    public function test_sitemap_index_has_page_sitemap(): void {
        $sitemap = $this->make_sitemap();
        $method  = new ReflectionMethod( $sitemap, 'generate_sitemap_index' );
        $method->setAccessible( true );
        $xml = $method->invoke( $sitemap );

        $this->assertStringContainsString( 'sitemap-page.xml', $xml );
    }

    public function test_sitemap_index_has_lastmod(): void {
        $sitemap = $this->make_sitemap();
        $method  = new ReflectionMethod( $sitemap, 'generate_sitemap_index' );
        $method->setAccessible( true );
        $xml = $method->invoke( $sitemap );

        $this->assertStringContainsString( '<lastmod>', $xml );
    }

    // ── Post type sitemap ───────────────────────────────────────────────

    public function test_post_sitemap_contains_urls(): void {
        $sitemap = $this->make_sitemap();
        $method  = new ReflectionMethod( $sitemap, 'generate_sitemap' );
        $method->setAccessible( true );
        $xml = $method->invoke( $sitemap, 'post', 1 );

        $this->assertStringContainsString( '<url>', $xml );
        $this->assertStringContainsString( '<loc>', $xml );
    }

    public function test_post_sitemap_has_lastmod(): void {
        $sitemap = $this->make_sitemap();
        $method  = new ReflectionMethod( $sitemap, 'generate_sitemap' );
        $method->setAccessible( true );
        $xml = $method->invoke( $sitemap, 'post', 1 );

        $this->assertStringContainsString( '<lastmod>', $xml );
    }

    public function test_post_sitemap_has_changefreq(): void {
        $sitemap = $this->make_sitemap();
        $method  = new ReflectionMethod( $sitemap, 'generate_sitemap' );
        $method->setAccessible( true );
        $xml = $method->invoke( $sitemap, 'post', 1 );

        $this->assertStringContainsString( '<changefreq>', $xml );
    }

    public function test_post_sitemap_has_priority(): void {
        $sitemap = $this->make_sitemap();
        $method  = new ReflectionMethod( $sitemap, 'generate_sitemap' );
        $method->setAccessible( true );
        $xml = $method->invoke( $sitemap, 'post', 1 );

        $this->assertStringContainsString( '<priority>', $xml );
    }

    public function test_page_sitemap_includes_homepage(): void {
        $sitemap = $this->make_sitemap();
        $method  = new ReflectionMethod( $sitemap, 'generate_sitemap' );
        $method->setAccessible( true );
        $xml = $method->invoke( $sitemap, 'page', 1 );

        $this->assertStringContainsString( '<priority>1.0</priority>', $xml );
        $this->assertStringContainsString( '<changefreq>daily</changefreq>', $xml );
    }

    public function test_static_front_page_is_listed_once(): void {
        global $si_test_state;
        $si_test_state['options']['show_on_front'] = 'page';
        $si_test_state['options']['page_on_front'] = 501;
        $si_test_state['posts'][501]      = [ 'ID' => 501, 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Home', 'post_name' => 'home', 'post_content' => '', 'post_modified' => '2026-11-01 00:00:00', 'post_modified_gmt' => '2026-11-01 00:00:00', 'post_date' => '2026-11-01 00:00:00' ];
        $si_test_state['permalinks'][501] = 'https://example.com/';
        $sitemap = $this->make_sitemap();
        $method  = new ReflectionMethod( $sitemap, 'generate_sitemap' );
        $method->setAccessible( true );
        $xml = $method->invoke( $sitemap, 'page', 1 );
        unset( $si_test_state['options']['show_on_front'], $si_test_state['options']['page_on_front'], $si_test_state['posts'][501], $si_test_state['permalinks'][501] );

        $this->assertSame( 1, substr_count( $xml, '<loc>https://example.com/</loc>' ) );
        $this->assertStringContainsString( '<priority>1.0</priority>', $xml );
    }

    public function test_sitemap_has_image_namespace(): void {
        $sitemap = $this->make_sitemap();
        $method  = new ReflectionMethod( $sitemap, 'generate_sitemap' );
        $method->setAccessible( true );
        $xml = $method->invoke( $sitemap, 'post', 1 );

        $this->assertStringContainsString( 'xmlns:image=', $xml );
    }

    // ── Changefreq logic ────────────────────────────────────────────────

    public function test_changefreq_returns_valid_value(): void {
        $sitemap = $this->make_sitemap();
        $method  = new ReflectionMethod( $sitemap, 'get_changefreq' );
        $method->setAccessible( true );

        $post   = (object) [ 'post_modified_gmt' => gmdate( 'Y-m-d H:i:s' ) ];
        $result = $method->invoke( $sitemap, $post );

        $valid = [ 'always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never' ];
        $this->assertContains( $result, $valid );
    }

    public function test_changefreq_recent_post_is_daily_or_weekly(): void {
        $sitemap = $this->make_sitemap();
        $method  = new ReflectionMethod( $sitemap, 'get_changefreq' );
        $method->setAccessible( true );

        $post   = (object) [ 'post_modified_gmt' => gmdate( 'Y-m-d H:i:s', time() - 86400 ) ]; // 1 day ago
        $result = $method->invoke( $sitemap, $post );

        $this->assertContains( $result, [ 'daily', 'weekly' ] );
    }

    // ── Sitemap disabled ────────────────────────────────────────────────

}
