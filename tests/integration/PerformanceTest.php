<?php
/**
 * Performance benchmark tests for Search Intel.
 *
 * These tests verify that key operations complete within acceptable
 * time budgets. All benchmarks use wall-clock time with generous
 * margins for CI environments.
 */

use PHPUnit\Framework\TestCase;
use Search_Intel\Core;
use Search_Intel\SEO_Manager;
use Search_Intel\Schema_Manager;
use Search_Intel\AEO_Manager;
use Search_Intel\GEO_Manager;
use Search_Intel\Content_Analyzer;
use Search_Intel\Sitemap;

class PerformanceTest extends TestCase {

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
                'post_title'        => 'Performance Benchmark Post',
                'post_content'      => str_repeat( '<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.</p>', 20 ),
                'post_excerpt'      => 'Performance test excerpt.',
                'post_author'       => 1,
                'post_type'         => 'post',
                'post_status'       => 'publish',
                'post_date_gmt'     => '2026-01-15 10:00:00',
                'post_modified_gmt' => '2026-02-20 14:30:00',
            ],
        ];
        // Simulate real-world meta data
        $si_test_state['post_meta'][1] = [
            '_search_intel_title'           => 'Custom SEO Title',
            '_search_intel_description'     => 'Custom meta description for performance testing.',
            '_search_intel_focus_keyword'   => 'performance',
            '_search_intel_canonical'       => '',
            '_search_intel_noindex'         => '',
            '_search_intel_nofollow'        => '',
            '_search_intel_schema_type'     => 'Article',
            '_search_intel_direct_answer'   => 'This is a direct answer for performance testing.',
            '_search_intel_ai_summary'      => 'AI summary for performance.',
            '_search_intel_content_type'    => 'article',
            '_search_intel_content_intent'  => 'informational',
            '_search_intel_audience'        => 'developers',
            '_search_intel_faqs'            => [
                [ 'question' => 'What is SEO?', 'answer' => 'Search Engine Optimization.' ],
                [ 'question' => 'What is AEO?', 'answer' => 'Answer Engine Optimization.' ],
            ],
            '_search_intel_entities'        => [
                [ 'name' => 'WordPress', 'type' => 'SoftwareApplication', 'url' => 'https://wordpress.org' ],
            ],
            '_search_intel_citations'       => [
                [ 'source' => 'Study', 'url' => 'https://example.com/study', 'date' => '2025-06-01', 'quote' => 'Key result.' ],
            ],
        ];
    }

    // ── wp_head meta rendering < 5ms ────────────────────────────────────

    public function test_seo_meta_renders_under_5ms(): void {
        $seo = new SEO_Manager( Core::get_default_settings() );

        $start = hrtime( true );
        for ( $i = 0; $i < 100; $i++ ) {
            ob_start();
            $seo->render_meta_tags();
            $seo->render_canonical();
            $seo->render_robots_meta();
            $seo->render_open_graph();
            $seo->render_twitter_card();
            ob_end_clean();
        }
        $elapsed_ms = ( hrtime( true ) - $start ) / 1e6 / 100;

        $this->assertLessThan( 5.0, $elapsed_ms, "SEO meta render took {$elapsed_ms}ms (budget: 5ms)" );
    }

    // ── Schema JSON generation < 2ms ────────────────────────────────────

    public function test_schema_generation_under_2ms(): void {
        $schema = new Schema_Manager( Core::get_default_settings() );

        $start = hrtime( true );
        for ( $i = 0; $i < 100; $i++ ) {
            $schema->get_singular_schema( 1 );
        }
        $elapsed_ms = ( hrtime( true ) - $start ) / 1e6 / 100;

        $this->assertLessThan( 2.0, $elapsed_ms, "Schema generation took {$elapsed_ms}ms (budget: 2ms)" );
    }

    public function test_schema_render_under_5ms(): void {
        $schema = new Schema_Manager( Core::get_default_settings() );

        $start = hrtime( true );
        for ( $i = 0; $i < 100; $i++ ) {
            ob_start();
            $schema->render_schema();
            ob_end_clean();
        }
        $elapsed_ms = ( hrtime( true ) - $start ) / 1e6 / 100;

        $this->assertLessThan( 5.0, $elapsed_ms, "Schema render took {$elapsed_ms}ms (budget: 5ms)" );
    }

    // ── AEO meta render ─────────────────────────────────────────────────

    public function test_aeo_render_under_2ms(): void {
        $aeo = new AEO_Manager( Core::get_default_settings() );

        $start = hrtime( true );
        for ( $i = 0; $i < 100; $i++ ) {
            ob_start();
            $aeo->render_direct_answer_meta();
            ob_end_clean();
        }
        $elapsed_ms = ( hrtime( true ) - $start ) / 1e6 / 100;

        $this->assertLessThan( 2.0, $elapsed_ms, "AEO render took {$elapsed_ms}ms (budget: 2ms)" );
    }

    // ── GEO meta render ─────────────────────────────────────────────────

    public function test_geo_ai_meta_under_3ms(): void {
        $geo = new GEO_Manager( Core::get_default_settings() );

        $start = hrtime( true );
        for ( $i = 0; $i < 100; $i++ ) {
            ob_start();
            $geo->render_ai_meta();
            $geo->render_freshness_meta();
            $geo->render_ai_crawl_directives();
            ob_end_clean();
        }
        $elapsed_ms = ( hrtime( true ) - $start ) / 1e6 / 100;

        $this->assertLessThan( 3.0, $elapsed_ms, "GEO meta render took {$elapsed_ms}ms (budget: 3ms)" );
    }

    // ── Content analysis < 200ms ────────────────────────────────────────

    public function test_content_analysis_under_200ms(): void {
        $analyzer = new Content_Analyzer( Core::get_default_settings() );
        $content  = str_repeat( '<p>Content paragraph about performance optimization and SEO best practices.</p>', 30 );

        $start = hrtime( true );
        for ( $i = 0; $i < 10; $i++ ) {
            $analyzer->analyze( 1, $content, 'Test Title', 'performance', 'Test description' );
        }
        $elapsed_ms = ( hrtime( true ) - $start ) / 1e6 / 10;

        $this->assertLessThan( 200.0, $elapsed_ms, "Content analysis took {$elapsed_ms}ms (budget: 200ms)" );
    }

    // ── GEO scoring ─────────────────────────────────────────────────────

    public function test_geo_scoring_under_5ms(): void {
        $geo = new GEO_Manager( Core::get_default_settings() );

        $start = hrtime( true );
        for ( $i = 0; $i < 100; $i++ ) {
            $geo->get_geo_score( 1 );
        }
        $elapsed_ms = ( hrtime( true ) - $start ) / 1e6 / 100;

        $this->assertLessThan( 5.0, $elapsed_ms, "GEO scoring took {$elapsed_ms}ms (budget: 5ms)" );
    }

    // ── Sitemap generation (cached) < 5ms ───────────────────────────────

    public function test_sitemap_cached_under_5ms(): void {
        global $si_test_state;
        // Simulate cached sitemap
        $si_test_state['transients']['si_sitemap_index'] = '<?xml version="1.0" encoding="UTF-8"?><sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></sitemapindex>';

        $sitemap = new Sitemap( Core::get_default_settings() );

        $method = new ReflectionMethod( $sitemap, 'generate_sitemap_index' );
        $method->setAccessible( true );

        $start = hrtime( true );
        for ( $i = 0; $i < 100; $i++ ) {
            $method->invoke( $sitemap );
        }
        $elapsed_ms = ( hrtime( true ) - $start ) / 1e6 / 100;

        $this->assertLessThan( 5.0, $elapsed_ms, "Cached sitemap took {$elapsed_ms}ms (budget: 5ms)" );
    }

    // ── Full wp_head pipeline ───────────────────────────────────────────

    public function test_full_wp_head_pipeline_under_10ms(): void {
        $settings = Core::get_default_settings();
        $seo      = new SEO_Manager( $settings );
        $schema   = new Schema_Manager( $settings );
        $aeo      = new AEO_Manager( $settings );
        $geo      = new GEO_Manager( $settings );

        $start = hrtime( true );
        for ( $i = 0; $i < 50; $i++ ) {
            ob_start();
            $seo->render_meta_tags();
            $seo->render_canonical();
            $seo->render_robots_meta();
            $seo->render_open_graph();
            $seo->render_twitter_card();
            $schema->render_schema();
            $aeo->render_direct_answer_meta();
            $geo->render_ai_meta();
            $geo->render_freshness_meta();
            $geo->render_ai_crawl_directives();
            ob_end_clean();
        }
        $elapsed_ms = ( hrtime( true ) - $start ) / 1e6 / 50;

        $this->assertLessThan( 10.0, $elapsed_ms, "Full wp_head pipeline took {$elapsed_ms}ms (budget: 10ms)" );
    }

    // ── Core prefetch is registered ─────────────────────────────────────

    public function test_core_registers_prefetch_hook(): void {
        global $si_test_state;
        $si_test_state['actions'] = [];

        $ref  = new ReflectionClass( Core::class );
        $prop = $ref->getProperty( 'instance' );
        $prop->setAccessible( true );
        $prop->setValue( null, null );

        Core::get_instance();

        $this->assertArrayHasKey( 'wp', $si_test_state['actions'] );
    }
}
