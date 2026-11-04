<?php
namespace Search_Intel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Search_Intel\Internal_Linker;

class InternalLinkerTest extends TestCase {

	private Internal_Linker $linker;

	protected function setUp(): void {
		global $si_test_state;
		$si_test_state['options']    = [];
		$si_test_state['transients'] = [];
		$si_test_state['posts']      = [];
		$si_test_state['post_terms'] = [];

		$this->linker = new Internal_Linker( [
			'enable_internal_linker' => true,
			'enabled_post_types'     => [ 'post', 'page' ],
		] );
	}

	public function test_extract_keywords_filters_stop_words(): void {
		$keywords = $this->linker->extract_keywords(
			'The quick brown fox jumps over the lazy dog and some wordpress plugin development'
		);

		$this->assertArrayHasKey( 'quick', $keywords );
		$this->assertArrayHasKey( 'brown', $keywords );
		$this->assertArrayHasKey( 'fox', $keywords );
		$this->assertArrayHasKey( 'wordpress', $keywords );
		$this->assertArrayNotHasKey( 'the', $keywords );
		$this->assertArrayNotHasKey( 'and', $keywords );
		$this->assertArrayNotHasKey( 'over', $keywords );
	}

	public function test_extract_keywords_counts_frequency(): void {
		$keywords = $this->linker->extract_keywords(
			'seo seo seo plugin plugin testing'
		);

		$this->assertEquals( 3, $keywords['seo'] );
		$this->assertEquals( 2, $keywords['plugin'] );
		$this->assertEquals( 1, $keywords['testing'] );
	}

	public function test_extract_keywords_ignores_short_words(): void {
		$keywords = $this->linker->extract_keywords( 'go to be do it an or if' );
		$this->assertEmpty( $keywords );
	}

	public function test_extract_keywords_strips_html(): void {
		$keywords = $this->linker->extract_keywords(
			'<p>Hello <strong>world</strong> of <a href="#">coding</a></p>'
		);

		$this->assertArrayHasKey( 'hello', $keywords );
		$this->assertArrayHasKey( 'world', $keywords );
		$this->assertArrayHasKey( 'coding', $keywords );
	}

	public function test_extract_keywords_limits_to_30(): void {
		$words = [];
		for ( $i = 0; $i < 50; $i++ ) {
			$words[] = 'uniqueword' . $i;
		}
		$keywords = $this->linker->extract_keywords( implode( ' ', $words ) );
		$this->assertLessThanOrEqual( 30, count( $keywords ) );
	}

	public function test_score_relevance_shared_terms(): void {
		global $si_test_state;
		$si_test_state['posts'][1] = [
			'ID' => 1, 'post_title' => 'SEO Guide', 'post_content' => 'Learn about seo optimization techniques',
			'post_type' => 'post', 'post_status' => 'publish',
		];
		$si_test_state['posts'][2] = [
			'ID' => 2, 'post_title' => 'SEO Tips', 'post_content' => 'More seo optimization tips and tricks',
			'post_type' => 'post', 'post_status' => 'publish',
		];
		$si_test_state['post_terms'][1]['category'] = [
			(object) [ 'term_id' => 10, 'name' => 'SEO' ],
		];
		$si_test_state['post_terms'][2]['category'] = [
			(object) [ 'term_id' => 10, 'name' => 'SEO' ],
		];

		$keywords  = $this->linker->extract_keywords( 'Learn about seo optimization techniques' );
		$candidate = (object) $si_test_state['posts'][2];
		$score     = $this->linker->score_relevance( 1, $candidate, $keywords );

		$this->assertGreaterThan( 0, $score );
	}

	public function test_score_relevance_no_overlap_returns_low(): void {
		global $si_test_state;
		$si_test_state['posts'][1] = [
			'ID' => 1, 'post_title' => 'Cooking Tips', 'post_content' => 'How to cook pasta perfectly',
			'post_type' => 'post', 'post_status' => 'publish',
		];
		$si_test_state['posts'][2] = [
			'ID' => 2, 'post_title' => 'Space Travel', 'post_content' => 'Mars exploration and rockets',
			'post_type' => 'post', 'post_status' => 'publish',
		];

		$keywords  = $this->linker->extract_keywords( 'How to cook pasta perfectly' );
		$candidate = (object) $si_test_state['posts'][2];
		$score     = $this->linker->score_relevance( 1, $candidate, $keywords );

		$this->assertEquals( 0.0, $score );
	}

	public function test_get_suggestions_returns_array(): void {
		global $si_test_state;
		$si_test_state['posts'][1] = [
			'ID' => 1, 'post_title' => 'Test', 'post_content' => 'Some content here',
			'post_type' => 'post', 'post_status' => 'publish',
		];

		$suggestions = $this->linker->get_suggestions( 1 );
		$this->assertIsArray( $suggestions );
	}

	public function test_get_suggestions_empty_for_missing_post(): void {
		$this->assertEmpty( $this->linker->get_suggestions( 999 ) );
	}

	public function test_get_suggestions_caches_results(): void {
		global $si_test_state;
		$si_test_state['posts'][1] = [
			'ID' => 1, 'post_title' => 'Test', 'post_content' => 'Some content',
			'post_type' => 'post', 'post_status' => 'publish',
		];

		$first  = $this->linker->get_suggestions( 1 );
		$cached = $si_test_state['transients']['search_intel_link_suggest_1'] ?? null;

		$this->assertNotNull( $cached );
		$this->assertEquals( $first, $cached );
	}

	public function test_invalidate_cache_removes_transient(): void {
		global $si_test_state;
		$si_test_state['transients']['search_intel_link_suggest_5'] = [ 'cached' ];

		$this->linker->invalidate_cache( 5 );

		$this->assertArrayNotHasKey( 'search_intel_link_suggest_5', $si_test_state['transients'] );
	}

	public function test_get_reason_with_shared_terms(): void {
		global $si_test_state;
		$si_test_state['post_terms'][1]['category'] = [
			(object) [ 'term_id' => 5, 'name' => 'SEO' ],
		];
		$si_test_state['post_terms'][2]['category'] = [
			(object) [ 'term_id' => 5, 'name' => 'SEO' ],
		];

		$candidate = (object) [ 'ID' => 2, 'post_title' => 'Test', 'post_type' => 'post' ];
		$reason    = $this->linker->get_reason( 1, $candidate );

		$this->assertStringContainsString( 'shared taxonomy terms', $reason );
	}

	public function test_get_reason_fallback(): void {
		$candidate = (object) [ 'ID' => 2, 'post_title' => 'Test', 'post_type' => 'post' ];
		$reason    = $this->linker->get_reason( 1, $candidate );

		$this->assertEquals( 'Related content', $reason );
	}

	public function test_find_related_posts_returns_array(): void {
		$related = $this->linker->find_related_posts( 1 );
		$this->assertIsArray( $related );
	}

	public function test_disabled_linker_does_not_register(): void {
		global $si_test_state;
		$si_test_state['actions'] = [];

		$linker = new Internal_Linker( [ 'enable_internal_linker' => false ] );
		$this->assertArrayNotHasKey( 'save_post', $si_test_state['actions'] ?? [] );
	}
}
