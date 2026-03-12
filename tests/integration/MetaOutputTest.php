<?php
/**
 * Integration tests for meta tag output.
 * Tests render_meta_tags, render_open_graph, render_twitter_card, render_canonical, render_robots_meta.
 */

use PHPUnit\Framework\TestCase;
use Search_Intel\SEO_Manager;
use Search_Intel\Core;

class MetaOutputTest extends TestCase {

    private function make_seo( array $overrides = [] ): SEO_Manager {
        $settings = array_merge( Core::get_default_settings(), $overrides );
        return new SEO_Manager( $settings );
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
                'post_title'        => 'Integration Test Post',
                'post_content'      => '<p>This is a detailed integration test post with enough content for descriptions.</p>',
                'post_excerpt'      => 'Custom excerpt for integration.',
                'post_author'       => 1,
                'post_type'         => 'post',
                'post_status'       => 'publish',
                'post_date_gmt'     => '2026-01-15 10:00:00',
                'post_modified_gmt' => '2026-02-20 14:30:00',
            ],
        ];
    }

    // ── Description meta tag ─────────────────────────────────────────────

    public function test_render_meta_tags_contains_description(): void {
        $seo = $this->make_seo();
        ob_start();
        $seo->render_meta_tags();
        $output = ob_get_clean();

        $this->assertStringContainsString( '<meta name="description"', $output );
    }

    public function test_render_meta_tags_contains_version_comment(): void {
        $seo = $this->make_seo();
        ob_start();
        $seo->render_meta_tags();
        $output = ob_get_clean();

        $this->assertStringContainsString( '<!-- Search Intel v', $output );
    }

    public function test_meta_description_uses_custom_when_set(): void {
        global $si_test_state;
        $si_test_state['post_meta'][1]['_search_intel_description'] = 'My custom SEO description.';

        $seo = $this->make_seo();
        ob_start();
        $seo->render_meta_tags();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'My custom SEO description.', $output );
    }

    public function test_meta_description_falls_back_to_excerpt(): void {
        $seo = $this->make_seo();
        ob_start();
        $seo->render_meta_tags();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'Custom excerpt for integration.', $output );
    }

    public function test_meta_description_escapes_html(): void {
        global $si_test_state;
        $si_test_state['post_meta'][1]['_search_intel_description'] = '<b>Bold</b> & "quoted"';

        $seo = $this->make_seo();
        ob_start();
        $seo->render_meta_tags();
        $output = ob_get_clean();

        $this->assertStringNotContainsString( '<b>', $output );
    }

    public function test_meta_author_rendered_for_posts(): void {
        $seo = $this->make_seo();
        ob_start();
        $seo->render_meta_tags();
        $output = ob_get_clean();

        $this->assertStringContainsString( '<meta name="author"', $output );
    }

    // ── Canonical URL ───────────────────────────────────────────────────

    public function test_render_canonical_outputs_link_tag(): void {
        $seo = $this->make_seo();
        ob_start();
        $seo->render_canonical();
        $output = ob_get_clean();

        $this->assertStringContainsString( '<link rel="canonical"', $output );
    }

    public function test_canonical_uses_permalink_by_default(): void {
        $seo = $this->make_seo();
        ob_start();
        $seo->render_canonical();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'https://example.com/?p=1', $output );
    }

    public function test_canonical_uses_custom_when_set(): void {
        global $si_test_state;
        $si_test_state['post_meta'][1]['_search_intel_canonical'] = 'https://example.com/my-seo-url';

        $seo = $this->make_seo();
        ob_start();
        $seo->render_canonical();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'https://example.com/my-seo-url', $output );
    }

    public function test_canonical_disabled_when_setting_off(): void {
        $seo = $this->make_seo( [ 'enable_canonical' => false ] );
        ob_start();
        $seo->render_canonical();
        $output = ob_get_clean();

        $this->assertEmpty( trim( $output ) );
    }

    // ── Robots meta ─────────────────────────────────────────────────────

    public function test_robots_meta_contains_rich_result_directives(): void {
        $seo = $this->make_seo();
        ob_start();
        $seo->render_robots_meta();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'max-snippet:-1', $output );
        $this->assertStringContainsString( 'max-image-preview:large', $output );
        $this->assertStringContainsString( 'max-video-preview:-1', $output );
    }

    public function test_robots_meta_includes_noindex_when_set(): void {
        global $si_test_state;
        $si_test_state['post_meta'][1]['_search_intel_noindex'] = '1';

        $seo = $this->make_seo();
        ob_start();
        $seo->render_robots_meta();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'noindex', $output );
    }

    public function test_robots_meta_includes_nofollow_when_set(): void {
        global $si_test_state;
        $si_test_state['post_meta'][1]['_search_intel_nofollow'] = '1';

        $seo = $this->make_seo();
        ob_start();
        $seo->render_robots_meta();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'nofollow', $output );
    }

    public function test_robots_disabled_when_setting_off(): void {
        $seo = $this->make_seo( [ 'enable_robots_meta' => false ] );
        ob_start();
        $seo->render_robots_meta();
        $output = ob_get_clean();

        $this->assertEmpty( trim( $output ) );
    }

    // ── Open Graph ──────────────────────────────────────────────────────

    public function test_open_graph_outputs_og_type(): void {
        $seo = $this->make_seo();
        ob_start();
        $seo->render_open_graph();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'og:type', $output );
        $this->assertStringContainsString( 'article', $output );
    }

    public function test_open_graph_outputs_og_title(): void {
        $seo = $this->make_seo();
        ob_start();
        $seo->render_open_graph();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'og:title', $output );
        $this->assertStringContainsString( 'Integration Test Post', $output );
    }

    public function test_open_graph_outputs_og_site_name(): void {
        $seo = $this->make_seo();
        ob_start();
        $seo->render_open_graph();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'og:site_name', $output );
    }

    public function test_open_graph_outputs_og_locale(): void {
        $seo = $this->make_seo();
        ob_start();
        $seo->render_open_graph();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'og:locale', $output );
        $this->assertStringContainsString( 'en_US', $output );
    }

    public function test_open_graph_uses_social_title_override(): void {
        global $si_test_state;
        $si_test_state['post_meta'][1]['_search_intel_social_title'] = 'Social Override Title';

        $seo = $this->make_seo();
        ob_start();
        $seo->render_open_graph();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'Social Override Title', $output );
    }

    public function test_open_graph_uses_social_description_override(): void {
        global $si_test_state;
        $si_test_state['post_meta'][1]['_search_intel_social_description'] = 'Social desc override.';

        $seo = $this->make_seo();
        ob_start();
        $seo->render_open_graph();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'Social desc override.', $output );
    }

    public function test_open_graph_includes_article_times(): void {
        $seo = $this->make_seo();
        ob_start();
        $seo->render_open_graph();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'article:published_time', $output );
        $this->assertStringContainsString( 'article:modified_time', $output );
    }

    public function test_open_graph_includes_article_author(): void {
        $seo = $this->make_seo();
        ob_start();
        $seo->render_open_graph();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'article:author', $output );
    }

    public function test_open_graph_includes_article_section(): void {
        $seo = $this->make_seo();
        ob_start();
        $seo->render_open_graph();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'article:section', $output );
    }

    public function test_open_graph_uses_default_og_image(): void {
        $seo = $this->make_seo( [ 'default_og_image' => 'https://example.com/default.jpg' ] );
        ob_start();
        $seo->render_open_graph();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'og:image', $output );
        $this->assertStringContainsString( 'https://example.com/default.jpg', $output );
    }

    // ── Twitter Card ────────────────────────────────────────────────────

    public function test_twitter_card_type_output(): void {
        $seo = $this->make_seo();
        ob_start();
        $seo->render_twitter_card();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'twitter:card', $output );
        $this->assertStringContainsString( 'summary_large_image', $output );
    }

    public function test_twitter_card_custom_type(): void {
        $seo = $this->make_seo( [ 'twitter_card_type' => 'summary' ] );
        ob_start();
        $seo->render_twitter_card();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'summary', $output );
    }

    public function test_twitter_card_site_handle(): void {
        $seo = $this->make_seo( [ 'twitter_site' => '@testsite' ] );
        ob_start();
        $seo->render_twitter_card();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'twitter:site', $output );
        $this->assertStringContainsString( '@testsite', $output );
    }

    public function test_twitter_card_title(): void {
        $seo = $this->make_seo();
        ob_start();
        $seo->render_twitter_card();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'twitter:title', $output );
        $this->assertStringContainsString( 'Integration Test Post', $output );
    }

    public function test_twitter_card_uses_social_override(): void {
        global $si_test_state;
        $si_test_state['post_meta'][1]['_search_intel_social_title'] = 'Twitter Override';

        $seo = $this->make_seo();
        ob_start();
        $seo->render_twitter_card();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'Twitter Override', $output );
    }

    public function test_twitter_card_description(): void {
        $seo = $this->make_seo();
        ob_start();
        $seo->render_twitter_card();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'twitter:description', $output );
    }

    // ── Title filter integration ────────────────────────────────────────

    public function test_filter_title_returns_custom_seo_title(): void {
        global $si_test_state;
        $si_test_state['post_meta'][1]['_search_intel_title'] = 'Custom SEO Title';

        $seo   = $this->make_seo();
        $title = $seo->filter_title( 'Original Title' );

        $this->assertEquals( 'Custom SEO Title', $title );
    }

    public function test_filter_title_returns_post_title_without_custom(): void {
        $seo   = $this->make_seo();
        $title = $seo->filter_title( 'Fallback Title' );

        $this->assertEquals( 'Integration Test Post', $title );
    }

    public function test_filter_separator_uses_setting(): void {
        $seo = $this->make_seo( [ 'separator' => '-' ] );
        $sep = $seo->filter_separator( '|' );

        $this->assertEquals( '-', $sep );
    }

    // ── Description truncation ──────────────────────────────────────────

    public function test_description_truncated_to_max_length(): void {
        global $si_test_state;
        $long_desc = str_repeat( 'word ', 100 );
        $si_test_state['post_meta'][1]['_search_intel_description'] = $long_desc;

        $seo  = $this->make_seo( [ 'description_max_length' => 50 ] );
        $desc = $seo->get_description( 1 );

        $this->assertLessThanOrEqual( 50, mb_strlen( $desc ) );
    }

    public function test_description_not_truncated_when_short(): void {
        global $si_test_state;
        $si_test_state['post_meta'][1]['_search_intel_description'] = 'Short description.';

        $seo  = $this->make_seo();
        $desc = $seo->get_description( 1 );

        $this->assertEquals( 'Short description.', $desc );
    }

    public function test_description_fallback_empty_returns_nothing(): void {
        global $si_test_state;
        $si_test_state['posts'][1]['post_excerpt'] = '';

        $seo  = $this->make_seo( [ 'description_fallback' => 'empty' ] );
        $desc = $seo->get_description( 1 );

        $this->assertEmpty( $desc );
    }
}
