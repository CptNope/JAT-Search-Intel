<?php
namespace JAT_Search_Intel\Tests\Unit;

use JAT_Search_Intel\Primary_Term;

class PrimaryTermTest extends ParityTestCase {

	private function cats(): array {
		return [
			new \WP_Term( [ 'term_id' => 9, 'name' => 'Reviews', 'slug' => 'reviews' ] ),
			new \WP_Term( [ 'term_id' => 4, 'name' => 'News', 'slug' => 'news' ] ),
			new \WP_Term( [ 'term_id' => 12, 'name' => 'Guides', 'slug' => 'guides' ] ),
		];
	}

	public function test_meta_key(): void {
		$this->assertSame( '_jat_search_intel_primary_category', Primary_Term::meta_key( 'category' ) );
		$this->assertSame( '_jat_search_intel_primary_product_cat', Primary_Term::meta_key( 'product_cat' ) );
	}

	public function test_defaults_to_lowest_term_id(): void {
		$GLOBALS['si_test_state']['post_terms'][5]['category'] = $this->cats();
		$this->assertSame( 4, Primary_Term::get_term( 5 )->term_id );
	}

	public function test_uses_chosen_term(): void {
		$GLOBALS['si_test_state']['post_terms'][5]['category'] = $this->cats();
		update_post_meta( 5, '_jat_search_intel_primary_category', '12' );
		$this->assertSame( 12, Primary_Term::get_term( 5 )->term_id );
	}

	public function test_ignores_choice_that_is_no_longer_assigned(): void {
		$GLOBALS['si_test_state']['post_terms'][5]['category'] = $this->cats();
		update_post_meta( 5, '_jat_search_intel_primary_category', '99' );
		$this->assertSame( 4, Primary_Term::get_term( 5 )->term_id );
	}

	public function test_other_taxonomies_use_get_the_terms(): void {
		$GLOBALS['si_test_state']['post_terms'][5]['product_cat'] = [
			new \WP_Term( [ 'term_id' => 30, 'taxonomy' => 'product_cat' ] ),
			new \WP_Term( [ 'term_id' => 31, 'taxonomy' => 'product_cat' ] ),
		];
		update_post_meta( 5, '_jat_search_intel_primary_product_cat', '31' );
		$this->assertSame( 31, Primary_Term::get_term( 5, 'product_cat' )->term_id );
	}

	public function test_returns_null_without_terms(): void {
		$this->assertNull( Primary_Term::get_term( 0 ) );
		$this->assertNull( Primary_Term::get_term( 5, 'product_cat' ) );
		$GLOBALS['si_test_state']['post_terms'][5]['category'] = [ 'not a term' ];
		$this->assertNull( Primary_Term::get_term( 5 ) );
	}

	public function test_permalink_filter_swaps_in_primary(): void {
		$cats = $this->cats();
		$post = new \WP_Post( [ 'ID' => 5 ] );
		update_post_meta( 5, '_jat_search_intel_primary_category', '12' );
		$this->assertSame( $cats[2], Primary_Term::filter_post_link_category( $cats[1], $cats, $post ) );
	}

	public function test_permalink_filter_keeps_default_without_choice_or_post(): void {
		$cats = $this->cats();
		$this->assertSame( $cats[1], Primary_Term::filter_post_link_category( $cats[1], $cats, new \WP_Post( [ 'ID' => 5 ] ) ) );
		update_post_meta( 5, '_jat_search_intel_primary_category', '12' );
		$this->assertSame( $cats[1], Primary_Term::filter_post_link_category( $cats[1], $cats, (object) [ 'ID' => 5 ] ) );
		$this->assertSame( $cats[1], Primary_Term::filter_post_link_category( $cats[1], 'nope', new \WP_Post( [ 'ID' => 5 ] ) ) );
	}

	public function test_product_permalink_filter(): void {
		$terms = [ new \WP_Term( [ 'term_id' => 30 ] ), new \WP_Term( [ 'term_id' => 31 ] ) ];
		update_post_meta( 6, '_jat_search_intel_primary_product_cat', '31' );
		$this->assertSame( $terms[1], Primary_Term::filter_product_link_category( $terms[0], $terms, new \WP_Post( [ 'ID' => 6 ] ) ) );
	}
}
