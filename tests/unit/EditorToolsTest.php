<?php
namespace JAT_Search_Intel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use JAT_Search_Intel\Editor_Tools;

class EditorToolsTest extends TestCase {

	/** @var Editor_Tools */
	private $tools;

	protected function setUp(): void {
		global $si_test_state;
		$si_test_state = [
			'options'   => [],
			'post_meta' => [],
			'posts'     => [ 7 => [ 'ID' => 7, 'post_title' => 'P', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'post', 'post_author' => 1 ] ],
		];
		$this->tools = new Editor_Tools();
		$_POST       = [];
		$_REQUEST    = [];
	}

	protected function tearDown(): void {
		$_POST    = [];
		$_REQUEST = [];
	}

	private function quick_edit( array $fields ): void {
		$_POST    = array_merge( [ 'jsi_quick_edit' => '1', 'jat_search_intel_inline_nonce' => 'n' ], $fields );
		$_REQUEST = $_POST;
		$this->tools->save_inline( 7 );
	}

	public function test_quick_edit_saves_fields(): void {
		global $si_test_state;
		$this->quick_edit( [ 'jsi_title' => 'New title', 'jsi_description' => 'New description', 'jsi_keyword' => 'scam', 'jsi_noindex' => '1' ] );

		$meta = $si_test_state['post_meta'][7];
		$this->assertSame( 'New title', $meta['_jat_search_intel_title'] );
		$this->assertSame( 'New description', $meta['_jat_search_intel_description'] );
		$this->assertSame( 'scam', $meta['_jat_search_intel_focus_keyword'] );
		$this->assertSame( '1', $meta['_jat_search_intel_noindex'] );
	}

	public function test_quick_edit_clears_empty_fields(): void {
		global $si_test_state;
		$si_test_state['post_meta'][7]['_jat_search_intel_title']   = 'Old';
		$si_test_state['post_meta'][7]['_jat_search_intel_noindex'] = '1';

		$this->quick_edit( [ 'jsi_title' => '', 'jsi_description' => '', 'jsi_keyword' => '' ] );

		$this->assertArrayNotHasKey( '_jat_search_intel_title', $si_test_state['post_meta'][7] );
		$this->assertArrayNotHasKey( '_jat_search_intel_noindex', $si_test_state['post_meta'][7] );
	}

	public function test_bad_nonce_saves_nothing(): void {
		global $si_test_state;
		$si_test_state['nonce_valid'] = false;
		$this->quick_edit( [ 'jsi_title' => 'Sneaky' ] );
		$this->assertArrayNotHasKey( '_jat_search_intel_title', $si_test_state['post_meta'][7] ?? [] );
	}

	public function test_no_permission_saves_nothing(): void {
		global $si_test_state;
		$si_test_state['denied_caps'] = [ 'edit_post' ];
		$this->quick_edit( [ 'jsi_title' => 'Sneaky' ] );
		$this->assertArrayNotHasKey( '_jat_search_intel_title', $si_test_state['post_meta'][7] ?? [] );
	}

	public function test_missing_nonce_saves_nothing(): void {
		global $si_test_state;
		$_POST    = [ 'jsi_quick_edit' => '1', 'jsi_title' => 'Sneaky' ];
		$_REQUEST = $_POST;
		$this->tools->save_inline( 7 );
		$this->assertArrayNotHasKey( '_jat_search_intel_title', $si_test_state['post_meta'][7] ?? [] );
	}

	public function test_bulk_edit_changes_only_chosen_flags(): void {
		global $si_test_state;
		$si_test_state['post_meta'][7]['_jat_search_intel_title'] = 'Keep me';
		$_REQUEST = [ 'jat_search_intel_inline_nonce' => 'n', 'jsi_bulk_noindex' => 'noindex', 'jsi_bulk_nofollow' => '' ];
		$this->tools->save_inline( 7 );

		$this->assertSame( '1', $si_test_state['post_meta'][7]['_jat_search_intel_noindex'] );
		$this->assertArrayNotHasKey( '_jat_search_intel_nofollow', $si_test_state['post_meta'][7] );
		$this->assertSame( 'Keep me', $si_test_state['post_meta'][7]['_jat_search_intel_title'] );
	}
}
