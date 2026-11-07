<?php
namespace JAT_Search_Intel\Tests\Unit;

use JAT_Search_Intel\SEO_History;

class SEOHistoryTest extends ParityTestCase {

	private SEO_History $history;

	protected function setUp(): void {
		parent::setUp();
		SEO_History::flush(); // Clear anything a previous test left pending.
		$GLOBALS['si_test_state']['posts'][3] = [ 'ID' => 3, 'post_type' => 'post', 'post_status' => 'publish' ];
		$this->history = new SEO_History();
	}

	/** Simulate core's update_post_meta(): filter first, then write. */
	private function set( string $key, string $value ): void {
		$this->history->capture_update( null, 3, $key, $value );
		update_post_meta( 3, $key, $value );
	}

	public function test_changes_are_grouped_into_one_entry(): void {
		update_post_meta( 3, '_jat_search_intel_title', 'Old title' );
		$this->set( '_jat_search_intel_title', 'New title' );
		$this->set( '_jat_search_intel_description', 'New description' );
		SEO_History::flush();

		$entries = SEO_History::get( 3 );
		$this->assertCount( 1, $entries );
		$this->assertSame( [ 'Old title', 'New title' ], $entries[0]['changes']['_jat_search_intel_title'] );
		$this->assertSame( [ '', 'New description' ], $entries[0]['changes']['_jat_search_intel_description'] );
		$this->assertSame( 1, $entries[0]['user'] );
	}

	public function test_untracked_and_unchanged_keys_are_ignored(): void {
		update_post_meta( 3, '_jat_search_intel_title', 'Same' );
		$this->set( '_jat_search_intel_title', 'Same' );
		$this->set( '_some_other_plugin_key', 'x' );
		SEO_History::flush();
		$this->assertSame( [], SEO_History::get( 3 ) );
	}

	public function test_change_and_change_back_in_one_request_records_nothing(): void {
		update_post_meta( 3, '_jat_search_intel_title', 'A' );
		$this->set( '_jat_search_intel_title', 'B' );
		$this->set( '_jat_search_intel_title', 'A' );
		SEO_History::flush();
		$this->assertSame( [], SEO_History::get( 3 ) );
	}

	public function test_short_circuited_writes_are_ignored(): void {
		$this->assertTrue( $this->history->capture_update( true, 3, '_jat_search_intel_title', 'x' ) );
		SEO_History::flush();
		$this->assertSame( [], SEO_History::get( 3 ) );
	}

	public function test_delete_is_recorded(): void {
		update_post_meta( 3, '_jat_search_intel_noindex', '1' );
		$this->history->capture_delete( null, 3, '_jat_search_intel_noindex' );
		SEO_History::flush();
		$this->assertSame( [ '1', '' ], SEO_History::get( 3 )[0]['changes']['_jat_search_intel_noindex'] );
	}

	public function test_history_is_capped(): void {
		for ( $i = 0; $i < SEO_History::MAX_ENTRIES + 5; $i++ ) {
			$this->set( '_jat_search_intel_title', 'Title ' . $i );
			SEO_History::flush();
		}
		$this->assertCount( SEO_History::MAX_ENTRIES, SEO_History::get( 3 ) );
		$this->assertSame( 'Title 34', SEO_History::get( 3 )[0]['changes']['_jat_search_intel_title'][1] );
	}

	public function test_restore_puts_old_values_back_with_partner_keys(): void {
		update_post_meta( 3, '_jat_search_intel_title', 'Old' );
		update_post_meta( 3, '_jat_search_intel_canonical', 'https://example.com/old/' );
		$this->set( '_jat_search_intel_title', 'New' );
		$this->set( '_jat_search_intel_canonical', 'https://example.com/new/' );
		$this->set( '_jat_search_intel_ai_summary', 'Added' );
		SEO_History::flush();
		$entry = SEO_History::get( 3 )[0];

		$restored = SEO_History::restore( 3, $entry['id'] );
		$this->assertSame( 'Old', $restored['_jat_search_intel_title'] );
		$this->assertSame( 'https://example.com/old/', $restored['_jat_search_intel_canonical_url'], 'The block editor key is sent back too.' );
		$this->assertSame( 'Old', get_post_meta( 3, '_jat_search_intel_title', true ) );
		$this->assertArrayNotHasKey( '_jat_search_intel_ai_summary', $GLOBALS['si_test_state']['post_meta'][3], 'A field that was empty is deleted.' );
	}

	public function test_restore_unknown_entry(): void {
		$this->assertInstanceOf( \WP_Error::class, SEO_History::restore( 3, 'nope' ) );
	}

	public function test_long_values_are_trimmed(): void {
		$this->set( '_jat_search_intel_ai_summary', str_repeat( 'x', 5000 ) );
		SEO_History::flush();
		$this->assertSame( 2000, strlen( SEO_History::get( 3 )[0]['changes']['_jat_search_intel_ai_summary'][1] ) );
	}

	public function test_no_entry_for_missing_post(): void {
		$this->history->capture_update( null, 404, '_jat_search_intel_title', 'x' );
		SEO_History::flush();
		$this->assertSame( [], SEO_History::get( 404 ) );
	}
}
