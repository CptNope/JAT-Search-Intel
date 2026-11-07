<?php
namespace JAT_Search_Intel\Tests\Unit;

use JAT_Search_Intel\Abilities;
use JAT_Search_Intel\Core;
use JAT_Search_Intel\Term_SEO;

class TermAbilitiesTest extends ParityTestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['si_test_state']['taxonomies'] = [ 'category' => 'category', 'post_tag' => 'post_tag', 'post_format' => 'post_format' ];
		$GLOBALS['si_test_state']['terms']      = [
			7 => new \WP_Term( [ 'term_id' => 7, 'taxonomy' => 'category', 'name' => 'News', 'count' => 0 ] ),
			8 => new \WP_Term( [ 'term_id' => 8, 'taxonomy' => 'nav_menu', 'name' => 'Menu' ] ),
		];
		update_term_meta( 7, '_jat_search_intel_title', 'News archive' );
		update_term_meta( 7, '_jat_search_intel_focus_keyword', 'secret plan' );
	}

	private function abilities(): Abilities {
		return new Abilities( Core::get_default_settings() );
	}

	public function test_post_format_is_not_a_seo_taxonomy(): void {
		$this->assertSame( [ 'category', 'post_tag' ], Term_SEO::taxonomies() );
	}

	public function test_get_term_seo_returns_fields(): void {
		$out = $this->abilities()->get_term_seo( [ 'term_id' => 7 ] );
		$this->assertSame( 'News archive', $out['title'] );
		$this->assertSame( 'secret plan', $out['focus_keyword'] );
		$this->assertTrue( $out['noindex_effective'], 'Empty terms are noindexed by default.' );
	}

	public function test_get_term_seo_hides_focus_keyword_from_visitors(): void {
		$GLOBALS['si_test_state']['denied_caps'] = [ 'edit_term' ];
		$this->assertArrayNotHasKey( 'focus_keyword', $this->abilities()->get_term_seo( [ 'term_id' => 7 ] ) );
	}

	public function test_private_taxonomies_and_missing_terms_are_not_found(): void {
		$this->assertInstanceOf( \WP_Error::class, $this->abilities()->get_term_seo( [ 'term_id' => 8 ] ) );
		$this->assertInstanceOf( \WP_Error::class, $this->abilities()->get_term_seo( [ 'term_id' => 404 ] ) );
		$this->assertInstanceOf( \WP_Error::class, $this->abilities()->update_term_seo( [ 'term_id' => 8, 'title' => 'x' ] ) );
	}

	public function test_update_term_seo_is_partial(): void {
		$out = $this->abilities()->update_term_seo( [ 'term_id' => 7, 'description' => 'All the news', 'noindex' => true, 'bogus' => 'x' ] );
		$this->assertSame( [ 'description', 'noindex' ], $out['updated'] );
		$this->assertSame( 'News archive', Term_SEO::get( 7, 'title' ) );
	}

	public function test_can_edit_term(): void {
		$abilities = $this->abilities();
		$this->assertTrue( $abilities->can_edit_term( [ 'term_id' => 7 ] ) );
		$this->assertFalse( $abilities->can_edit_term( [] ) );
		$GLOBALS['si_test_state']['denied_caps'] = [ 'edit_term' ];
		$this->assertFalse( $abilities->can_edit_term( [ 'term_id' => 7 ] ) );
	}
}
