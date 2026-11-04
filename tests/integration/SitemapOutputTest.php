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

    // ── Sitemap index ───────────────────────────────────────────────────

    public function test_sitemap_index_is_valid_xml(): void {
        $sitemap = $this->make_sitemap();
        $method  = new ReflectionMethod( $sitemap, 'generate_sitemap_index' );
        $method->setAccessible( true );
        $xml = $method->invoke( $sitemap );

        $this->assertStringStartsWith( '<?xml version="1.0"', $xml );
        $this->assertStringContainsString( '<sitemapindex', $xml );
        $this->assertStringContainsString( '</sitemapindex>', $xml );
    }

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

    public function test_post_sitemap_is_valid_xml(): void {
        $sitemap = $this->make_sitemap();
        $method  = new ReflectionMethod( $sitemap, 'generate_sitemap' );
        $method->setAccessible( true );
        $xml = $method->invoke( $sitemap, 'post', 1 );

        $this->assertStringStartsWith( '<?xml version="1.0"', $xml );
        $this->assertStringContainsString( '<urlset', $xml );
        $this->assertStringContainsString( '</urlset>', $xml );
    }

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

    public function test_sitemap_disabled_registers_no_hooks(): void {
        global $si_test_state;
        $si_test_state['actions'] = [];

        $this->make_sitemap( [ 'enable_sitemap' => false ] );

        $this->assertArrayNotHasKey( 'init', $si_test_state['actions'] );
    }
}
