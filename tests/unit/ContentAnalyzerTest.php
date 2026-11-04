<?php
/**
 * Unit tests for the Content_Analyzer class.
 */

use PHPUnit\Framework\TestCase;
use JAT_Search_Intel\Content_Analyzer;

class ContentAnalyzerTest extends TestCase {

    private Content_Analyzer $analyzer;

    protected function setUp(): void {
        global $si_test_state;
        $si_test_state['post_meta']  = [];
        $si_test_state['transients'] = [];
        $si_test_state['posts']      = [
            1 => [
                'ID'           => 1,
                'post_title'   => 'Test Post About SEO',
                'post_content' => '<p>This is a comprehensive post about search engine optimization and best practices.</p>',
                'post_excerpt' => '',
                'post_author'  => 1,
                'post_type'    => 'post',
            ],
        ];

        $this->analyzer = new Content_Analyzer( [
            'enable_seo'         => true,
            'enabled_post_types' => [ 'post', 'page' ],
        ] );
    }

    // ── Basic analyze return structure ───────────────────────────────────

    public function test_analyze_returns_expected_keys(): void {
        $result = $this->analyzer->analyze( 1, '<p>Hello world</p>', 'Test Title', 'seo', 'A test description.' );

        $this->assertArrayHasKey( 'seo_score', $result );
        $this->assertArrayHasKey( 'aeo_score', $result );
        $this->assertArrayHasKey( 'geo_score', $result );
        $this->assertArrayHasKey( 'readability', $result );
        $this->assertArrayHasKey( 'word_count', $result );
        $this->assertArrayHasKey( 'content_checks', $result );
    }

    public function test_analyze_scores_are_integers(): void {
        $result = $this->analyzer->analyze( 1, '<p>Hello world</p>', 'Test', 'keyword', 'desc' );

        $this->assertIsInt( $result['seo_score']['score'] );
        $this->assertIsInt( $result['aeo_score']['score'] );
        $this->assertIsInt( $result['geo_score']['score'] );
    }

    public function test_analyze_scores_are_between_0_and_100(): void {
        $result = $this->analyzer->analyze( 1, '<p>Hello world</p>', 'Test', 'keyword', 'desc' );

        $this->assertGreaterThanOrEqual( 0, $result['seo_score']['score'] );
        $this->assertLessThanOrEqual( 100, $result['seo_score']['score'] );
        $this->assertGreaterThanOrEqual( 0, $result['aeo_score']['score'] );
        $this->assertLessThanOrEqual( 100, $result['aeo_score']['score'] );
        $this->assertGreaterThanOrEqual( 0, $result['geo_score']['score'] );
        $this->assertLessThanOrEqual( 100, $result['geo_score']['score'] );
    }

    // ── SEO scoring ─────────────────────────────────────────────────────

    public function test_seo_score_has_checks(): void {
        $result = $this->analyzer->analyze( 1, '<p>Keyword in content</p>', 'Title', 'keyword', 'Description' );

        $this->assertArrayHasKey( 'checks', $result['seo_score'] );
        $this->assertIsArray( $result['seo_score']['checks'] );
        $this->assertNotEmpty( $result['seo_score']['checks'] );
    }

    public function test_seo_score_increases_with_keyword_in_title(): void {
        $without = $this->analyzer->analyze( 1, '<p>Hello world</p>', 'No Match', 'seo', 'desc' );
        $with    = $this->analyzer->analyze( 1, '<p>Hello world</p>', 'Best SEO Guide', 'seo', 'desc' );

        $this->assertGreaterThan( $without['seo_score']['score'], $with['seo_score']['score'] );
    }

    public function test_seo_checks_flag_missing_description(): void {
        $result = $this->analyzer->analyze( 1, '<p>Some content</p>', 'Title', 'keyword', '' );

        $no_desc = array_filter( $result['seo_score']['checks'], function ( $check ) {
            return ! $check['pass'] && stripos( $check['label'], 'description' ) !== false;
        } );

        $this->assertNotEmpty( $no_desc );
    }

    public function test_seo_checks_pass_good_description_length(): void {
        $desc   = str_repeat( 'A good meta description ', 6 ); // ~144 chars
        $result = $this->analyzer->analyze( 1, '<p>content</p>', 'Title', 'kw', $desc );

        $desc_check = array_filter( $result['seo_score']['checks'], function ( $check ) {
            return $check['pass'] && stripos( $check['label'], 'description' ) !== false;
        } );

        $this->assertNotEmpty( $desc_check );
    }

    public function test_seo_checks_flag_short_title(): void {
        $result = $this->analyzer->analyze( 1, '<p>Content</p>', 'Hi', 'kw', 'desc' );

        $title_check = array_filter( $result['seo_score']['checks'], function ( $check ) {
            return ! $check['pass'] && stripos( $check['label'], 'title' ) !== false;
        } );

        $this->assertNotEmpty( $title_check );
    }

    public function test_seo_checks_flag_low_word_count(): void {
        $result = $this->analyzer->analyze( 1, '<p>Short.</p>', 'Title', 'kw', 'desc' );

        $wc_check = array_filter( $result['seo_score']['checks'], function ( $check ) {
            return ! $check['pass'] && stripos( $check['label'], 'words' ) !== false;
        } );

        $this->assertNotEmpty( $wc_check );
    }

    // ── Readability ─────────────────────────────────────────────────────

    public function test_readability_returns_score_and_details(): void {
        $content = str_repeat( 'The cat sat on the mat. ', 50 );
        $result  = $this->analyzer->get_readability_score( $content );

        $this->assertArrayHasKey( 'score', $result );
        $this->assertArrayHasKey( 'grade', $result );
        $this->assertArrayHasKey( 'label', $result );
        $this->assertArrayHasKey( 'avg_sentence_len', $result );
    }

    public function test_readability_score_within_range(): void {
        $content = str_repeat( 'Simple words in a simple sentence. ', 20 );
        $result  = $this->analyzer->get_readability_score( $content );

        $this->assertGreaterThanOrEqual( 0, $result['score'] );
        $this->assertLessThanOrEqual( 100, $result['score'] );
    }

    public function test_readability_empty_content_returns_zero(): void {
        $result = $this->analyzer->get_readability_score( '' );

        $this->assertEquals( 0, $result['score'] );
    }

    // ── Word count ──────────────────────────────────────────────────────

    public function test_word_count_strips_html(): void {
        $content = '<p>One <strong>two</strong> three <a href="#">four</a> five.</p>';
        $result  = $this->analyzer->analyze( 1, $content, 'Title', 'kw', 'desc' );

        $this->assertEquals( 5, $result['word_count'] );
    }

    public function test_word_count_empty_content_is_zero(): void {
        $result = $this->analyzer->analyze( 1, '', 'Title', 'kw', 'desc' );

        $this->assertEquals( 0, $result['word_count'] );
    }

    // ── Content checks ──────────────────────────────────────────────────

    public function test_content_checks_is_array(): void {
        $result = $this->analyzer->analyze( 1, '<p>Content</p>', 'Title', 'kw', 'desc' );

        $this->assertIsArray( $result['content_checks'] );
    }

    public function test_content_checks_have_label_and_pass(): void {
        $content = '<h1>Title</h1><p>' . str_repeat( 'Word ', 100 ) . '</p>';
        $result  = $this->analyzer->analyze( 1, $content, 'Title', 'kw', 'desc' );

        foreach ( $result['content_checks'] as $check ) {
            $this->assertArrayHasKey( 'pass', $check );
            $this->assertArrayHasKey( 'label', $check );
        }
    }

    // ── H1 detection ────────────────────────────────────────────────────

    public function test_h1_check_passes_with_single_h1(): void {
        $content = '<h1>My Title</h1><p>' . str_repeat( 'Word ', 100 ) . '</p>';
        $result  = $this->analyzer->analyze( 1, $content, 'Title', 'kw', 'desc' );

        $h1_check = array_filter( $result['content_checks'], function ( $c ) {
            return stripos( $c['label'], 'H1' ) !== false && $c['pass'];
        } );

        $this->assertNotEmpty( $h1_check );
    }

    public function test_h1_check_fails_with_multiple_h1(): void {
        $content = '<h1>First</h1><h1>Second</h1><p>' . str_repeat( 'Word ', 100 ) . '</p>';
        $result  = $this->analyzer->analyze( 1, $content, 'Title', 'kw', 'desc' );

        $h1_fail = array_filter( $result['content_checks'], function ( $c ) {
            return stripos( $c['label'], 'H1' ) !== false && ! $c['pass'];
        } );

        $this->assertNotEmpty( $h1_fail );
    }

    // ── Internal links ──────────────────────────────────────────────────

    public function test_internal_links_check_fails_with_none(): void {
        $content = '<p>' . str_repeat( 'Word ', 200 ) . '</p>';
        $result  = $this->analyzer->analyze( 1, $content, 'Title', 'kw', 'desc' );

        $link_check = array_filter( $result['content_checks'], function ( $c ) {
            return stripos( $c['label'], 'internal' ) !== false && ! $c['pass'];
        } );

        $this->assertNotEmpty( $link_check );
    }

    // ── Social metadata check ───────────────────────────────────────────

    public function test_social_check_fails_when_no_social_meta(): void {
        $content = '<p>' . str_repeat( 'Word ', 100 ) . '</p>';
        $result  = $this->analyzer->analyze( 1, $content, 'Title', 'kw', 'desc' );

        $social_check = array_filter( $result['content_checks'], function ( $c ) {
            return stripos( $c['label'], 'social' ) !== false || stripos( $c['label'], 'Social' ) !== false;
        } );

        // Should fail since no social meta is set
        $failing = array_filter( $social_check, function ( $c ) {
            return ! $c['pass'];
        } );

        $this->assertNotEmpty( $failing );
    }

    public function test_social_check_passes_when_all_set(): void {
        global $si_test_state;
        $si_test_state['post_meta'][1]['_jat_search_intel_social_title']       = 'Social Title';
        $si_test_state['post_meta'][1]['_jat_search_intel_social_description'] = 'Social Desc';
        $si_test_state['post_meta'][1]['_jat_search_intel_social_image']       = 'https://example.com/img.jpg';

        $content = '<p>' . str_repeat( 'Word ', 100 ) . '</p>';
        $result  = $this->analyzer->analyze( 1, $content, 'Title', 'kw', 'desc' );

        $social_pass = array_filter( $result['content_checks'], function ( $c ) {
            return ( stripos( $c['label'], 'social' ) !== false || stripos( $c['label'], 'Social' ) !== false ) && $c['pass'];
        } );

        $this->assertNotEmpty( $social_pass );
    }

    // ── Secondary keywords ──────────────────────────────────────────────

    public function test_secondary_keyword_check_when_present(): void {
        global $si_test_state;
        $si_test_state['post_meta'][1]['_jat_search_intel_secondary_keywords'] = 'optimization, ranking';

        $content = '<p>Search engine optimization helps improve your ranking in results.</p>';
        $result  = $this->analyzer->analyze( 1, $content, 'Title', 'seo', 'desc' );

        $kw_check = array_filter( $result['content_checks'], function ( $c ) {
            return stripos( $c['label'], 'secondary' ) !== false && $c['pass'];
        } );

        $this->assertNotEmpty( $kw_check );
    }

    // ── AEO scoring ─────────────────────────────────────────────────────

    public function test_aeo_score_increases_with_question_headings(): void {
        $plain    = '<p>' . str_repeat( 'Word ', 100 ) . '</p>';
        $with_faq = '<h2>What is SEO?</h2><p>SEO is optimization.</p><h2>Why does it matter?</h2><p>It matters because visibility.</p>';

        $score_plain = $this->analyzer->analyze( 1, $plain, 'Title', 'kw', 'desc' );
        $score_faq   = $this->analyzer->analyze( 1, $with_faq, 'Title', 'kw', 'desc' );

        $this->assertGreaterThan( $score_plain['aeo_score']['score'], $score_faq['aeo_score']['score'] );
    }

    // ── GEO scoring ─────────────────────────────────────────────────────

    public function test_geo_score_increases_with_entities(): void {
        global $si_test_state;

        $content = '<p>' . str_repeat( 'Word ', 500 ) . '</p>';

        $si_test_state['post_meta'][1]['_jat_search_intel_entities'] = [];
        $score_no_entities = $this->analyzer->analyze( 1, $content, 'Title', 'kw', 'desc' );

        $si_test_state['post_meta'][1]['_jat_search_intel_entities'] = [
            [ 'name' => 'Google', 'type' => 'Organization', 'url' => '' ],
            [ 'name' => 'SEO', 'type' => 'Thing', 'url' => '' ],
            [ 'name' => 'Web', 'type' => 'Thing', 'url' => '' ],
        ];
        $score_with_entities = $this->analyzer->analyze( 1, $content, 'Title', 'kw', 'desc' );

        $this->assertGreaterThan( $score_no_entities['geo_score']['score'], $score_with_entities['geo_score']['score'] );
    }

    public function test_geo_score_increases_with_ai_summary(): void {
        global $si_test_state;

        $content = '<p>' . str_repeat( 'Word ', 500 ) . '</p>';

        $si_test_state['post_meta'][1]['_jat_search_intel_ai_summary'] = '';
        $no_summary = $this->analyzer->analyze( 1, $content, 'Title', 'kw', 'desc' );

        $si_test_state['post_meta'][1]['_jat_search_intel_ai_summary'] = 'This is a comprehensive AI summary of the article content for generative engines.';
        $with_summary = $this->analyzer->analyze( 1, $content, 'Title', 'kw', 'desc' );

        $this->assertGreaterThan( $no_summary['geo_score']['score'], $with_summary['geo_score']['score'] );
    }

    // ── Image alt text check ────────────────────────────────────────────

    public function test_alt_text_check_with_images(): void {
        $content = '<p>Text</p><img src="test.jpg" alt="A good image" /><img src="test2.jpg" />';
        $result  = $this->analyzer->analyze( 1, $content, 'Title', 'kw', 'desc' );

        $alt_check = array_filter( $result['content_checks'], function ( $c ) {
            return stripos( $c['label'], 'alt' ) !== false;
        } );

        $this->assertNotEmpty( $alt_check );
    }
}
