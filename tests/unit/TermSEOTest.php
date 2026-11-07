<?php
namespace JAT_Search_Intel\Tests\Unit;

use JAT_Search_Intel\Term_SEO;

class TermSEOTest extends ParityTestCase {

	private function term( array $props = [] ): \WP_Term {
		return new \WP_Term( $props + [ 'term_id' => 7, 'taxonomy' => 'category', 'count' => 3, 'name' => 'News' ] );
	}

	public function test_get_returns_empty_for_unknown_field_or_term(): void {
		update_term_meta( 7, '_jat_search_intel_title', 'Hello' );
		$this->assertSame( 'Hello', Term_SEO::get( 7, 'title' ) );
		$this->assertSame( '', Term_SEO::get( 7, 'not_a_field' ) );
		$this->assertSame( '', Term_SEO::get( 0, 'title' ) );
	}

	public function test_get_all_casts_bools(): void {
		update_term_meta( 7, '_jat_search_intel_noindex', '1' );
		$all = Term_SEO::get_all( 7 );
		$this->assertTrue( $all['noindex'] );
		$this->assertFalse( $all['nofollow'] );
		$this->assertSame( '', $all['title'] );
		$this->assertSame( array_keys( Term_SEO::FIELDS ), array_keys( $all ) );
	}

	public function test_is_noindex_by_term_meta(): void {
		update_term_meta( 7, '_jat_search_intel_noindex', '1' );
		$this->assertTrue( Term_SEO::is_noindex( $this->term(), [] ) );
	}

	public function test_is_noindex_by_taxonomy_setting(): void {
		$this->assertTrue( Term_SEO::is_noindex( $this->term( [ 'taxonomy' => 'post_tag' ] ), [ 'noindex_taxonomies' => [ 'post_tag' ] ] ) );
		$this->assertFalse( Term_SEO::is_noindex( $this->term(), [ 'noindex_taxonomies' => [ 'post_tag' ] ] ) );
	}

	public function test_is_noindex_for_empty_terms_only_when_enabled(): void {
		$empty = $this->term( [ 'count' => 0 ] );
		$this->assertTrue( Term_SEO::is_noindex( $empty, [ 'noindex_empty_terms' => true ] ) );
		$this->assertFalse( Term_SEO::is_noindex( $empty, [ 'noindex_empty_terms' => false ] ) );
		$this->assertFalse( Term_SEO::is_noindex( $this->term(), [ 'noindex_empty_terms' => true ] ) );
	}

	public function test_update_sanitizes_by_type(): void {
		$changed = Term_SEO::update(
			7,
			[
				'title'       => '<b>Title</b>',
				'description' => "Line one\n<script>x</script>",
				'canonical'   => ' https://example.com/news/ ',
				'noindex'     => 'on',
			]
		);
		$this->assertSame( [ 'title', 'description', 'canonical', 'noindex' ], $changed );
		$this->assertSame( 'Title', Term_SEO::get( 7, 'title' ) );
		$this->assertStringNotContainsString( '<script>', Term_SEO::get( 7, 'description' ) );
		$this->assertSame( 'https://example.com/news/', Term_SEO::get( 7, 'canonical' ) );
		$this->assertSame( '1', Term_SEO::get( 7, 'noindex' ) );
	}

	public function test_full_update_clears_unchecked_boxes_but_partial_does_not(): void {
		update_term_meta( 7, '_jat_search_intel_noindex', '1' );
		update_term_meta( 7, '_jat_search_intel_title', 'Keep' );

		Term_SEO::update( 7, [ 'focus_keyword' => 'news' ], true );
		$this->assertSame( '1', Term_SEO::get( 7, 'noindex' ), 'Partial updates leave other fields alone.' );

		$changed = Term_SEO::update( 7, [] );
		$this->assertSame( [ 'noindex' ], $changed, 'A submitted form without the checkbox turns it off.' );
		$this->assertSame( '', Term_SEO::get( 7, 'noindex' ) );
		$this->assertSame( 'Keep', Term_SEO::get( 7, 'title' ), 'Text fields missing from the form are kept.' );
	}

	public function test_update_reports_no_change_for_same_value(): void {
		Term_SEO::update( 7, [ 'title' => 'Same' ], true );
		$this->assertSame( [], Term_SEO::update( 7, [ 'title' => 'Same' ], true ) );
	}

	public function test_save_ignores_requests_without_nonce(): void {
		$_POST['jat_search_intel_term'] = [ 'title' => 'Sneaky' ];
		( new Term_SEO( [ 'enable_seo' => true ] ) )->save( 7 );
		$this->assertSame( '', Term_SEO::get( 7, 'title' ) );
	}

	public function test_save_rejects_invalid_nonce(): void {
		$GLOBALS['si_test_state']['nonce_valid'] = false;
		$_POST['jat_search_intel_term_nonce']    = 'forged';
		$_POST['jat_search_intel_term']          = [ 'title' => 'Forged' ];
		( new Term_SEO( [ 'enable_seo' => true ] ) )->save( 7 );
		$this->assertSame( '', Term_SEO::get( 7, 'title' ) );
	}

	public function test_save_requires_edit_term_capability(): void {
		global $si_test_state;
		$si_test_state['denied_caps'] = [ 'edit_term' ];
		$_POST['jat_search_intel_term_nonce'] = 'nonce';
		$_POST['jat_search_intel_term']       = [ 'title' => 'Denied' ];
		( new Term_SEO( [ 'enable_seo' => true ] ) )->save( 7 );
		$this->assertSame( '', Term_SEO::get( 7, 'title' ) );
	}

	public function test_save_stores_fields_with_valid_nonce(): void {
		$_POST['jat_search_intel_term_nonce'] = 'nonce';
		$_POST['jat_search_intel_term']       = [ 'title' => 'Saved', 'nofollow' => '1', 'bogus' => 'x' ];
		( new Term_SEO( [ 'enable_seo' => true ] ) )->save( 7 );
		$this->assertSame( 'Saved', Term_SEO::get( 7, 'title' ) );
		$this->assertSame( '1', Term_SEO::get( 7, 'nofollow' ) );
		$this->assertArrayNotHasKey( '_jat_search_intel_bogus', $GLOBALS['si_test_state']['term_meta'][7] );
	}

	public function test_render_fields_outputs_nonce_and_escaped_values(): void {
		update_term_meta( 7, '_jat_search_intel_title', '"><script>alert(1)</script>' );
		ob_start();
		( new Term_SEO( [ 'enable_seo' => true, 'noindex_taxonomies' => [ 'category' ] ] ) )->render_fields( $this->term(), 'category' );
		$html = (string) ob_get_clean();
		$this->assertStringContainsString( 'jat_search_intel_term[title]', $html );
		$this->assertStringContainsString( 'jat_search_intel_term_nonce', $html );
		$this->assertStringNotContainsString( '<script>alert(1)</script>', $html );
		$this->assertStringContainsString( 'whole taxonomy is set to noindex', $html );
	}

	public function test_constructor_does_nothing_when_seo_disabled(): void {
		new Term_SEO( [ 'enable_seo' => false ] );
		$this->assertEmpty( $GLOBALS['si_test_state']['actions']['admin_init'] ?? [] );
	}
}
