<?php
namespace JAT_Search_Intel\Tests\Unit;

use JAT_Search_Intel\Modified_Date;

class ModifiedDateTest extends ParityTestCase {

	private Modified_Date $dates;

	protected function setUp(): void {
		parent::setUp();
		$this->dates = new Modified_Date();
		$GLOBALS['si_test_state']['wp_posts'][3] = new \WP_Post(
			[
				'ID'                => 3,
				'post_status'       => 'publish',
				'post_modified'     => '2026-01-02 03:04:05',
				'post_modified_gmt' => '2026-01-02 03:04:05',
			]
		);
	}

	private function save( int $id = 3, bool $update = true ): array {
		return $this->dates->keep_modified_date(
			[ 'post_modified' => '2026-11-07 10:00:00', 'post_modified_gmt' => '2026-11-07 10:00:00' ],
			[ 'ID' => $id ],
			[],
			$update
		);
	}

	public function test_unfrozen_post_gets_new_date(): void {
		$this->assertSame( '2026-11-07 10:00:00', $this->save()['post_modified'] );
	}

	public function test_frozen_meta_keeps_old_date(): void {
		update_post_meta( 3, Modified_Date::META_KEY, '1' );
		$data = $this->save();
		$this->assertSame( '2026-01-02 03:04:05', $data['post_modified'] );
		$this->assertSame( '2026-01-02 03:04:05', $data['post_modified_gmt'] );
	}

	public function test_new_posts_and_drafts_are_never_frozen(): void {
		update_post_meta( 3, Modified_Date::META_KEY, '1' );
		$this->assertSame( '2026-11-07 10:00:00', $this->save( 3, false )['post_modified'] );

		$GLOBALS['si_test_state']['wp_posts'][3]->post_status = 'draft';
		$this->assertSame( '2026-11-07 10:00:00', $this->save()['post_modified'] );
	}

	public function test_classic_editor_checkbox_needs_valid_nonce_and_capability(): void {
		$_POST['jat_search_intel_metabox_nonce'] = 'nonce';
		$_POST['si_freeze_modified']             = '1';
		$this->assertSame( '2026-01-02 03:04:05', $this->save()['post_modified'] );

		$GLOBALS['si_test_state']['nonce_valid'] = false;
		$this->assertSame( '2026-11-07 10:00:00', $this->save()['post_modified'], 'A forged nonce falls back to the stored meta (off).' );

		$GLOBALS['si_test_state']['nonce_valid'] = true;
		$GLOBALS['si_test_state']['denied_caps'] = [ 'edit_post' ];
		$this->assertSame( '2026-11-07 10:00:00', $this->save()['post_modified'] );
	}

	public function test_classic_editor_unchecked_box_overrides_stored_meta(): void {
		update_post_meta( 3, Modified_Date::META_KEY, '1' );
		$_POST['jat_search_intel_metabox_nonce'] = 'nonce';
		$this->assertSame( '2026-11-07 10:00:00', $this->save()['post_modified'] );
	}

	public function test_rest_value_is_used_for_block_editor_saves(): void {
		$request = new \WP_REST_Request( 'POST', '/wp/v2/posts/3' );
		$request->set_param( 'id', 3 );
		$request->set_param( 'meta', [ Modified_Date::META_KEY => true ] );
		$this->assertNull( $this->dates->capture_rest_value( null, [], $request ) );
		$this->assertSame( '2026-01-02 03:04:05', $this->save()['post_modified'] );

		$request->set_param( 'meta', [ Modified_Date::META_KEY => false ] );
		$this->dates->capture_rest_value( null, [], $request );
		update_post_meta( 3, Modified_Date::META_KEY, '1' );
		$this->assertSame( '2026-11-07 10:00:00', $this->save()['post_modified'], 'The value sent in the request wins over the old meta.' );
	}

	public function test_get_requests_are_ignored(): void {
		$request = new \WP_REST_Request( 'GET', '/wp/v2/posts/4' );
		$request->set_param( 'id', 4 );
		$request->set_param( 'meta', [ Modified_Date::META_KEY => true ] );
		$this->dates->capture_rest_value( null, [], $request );
		$GLOBALS['si_test_state']['wp_posts'][4] = new \WP_Post( [ 'ID' => 4, 'post_modified' => 'old', 'post_modified_gmt' => 'old' ] );
		$this->assertSame( '2026-11-07 10:00:00', $this->save( 4 )['post_modified'] );
	}
}
