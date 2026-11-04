<?php
namespace JAT_Search_Intel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use JAT_Search_Intel\Abilities;
use JAT_Search_Intel\Core;

/**
 * Abilities API registration and callbacks.
 */
class AbilitiesTest extends TestCase {

	protected function setUp(): void {
		global $si_test_state;
		$si_test_state['post_meta'] = [];
		$si_test_state['actions']   = [];
		$si_test_state['abilities'] = [];
		$si_test_state['posts']     = [
			10 => [ 'ID' => 10, 'post_type' => 'post', 'post_status' => 'publish', 'post_password' => '', 'post_title' => 'Hello', 'post_content' => 'x', 'post_author' => 1 ],
			11 => [ 'ID' => 11, 'post_type' => 'post', 'post_status' => 'draft', 'post_password' => '', 'post_title' => 'Draft', 'post_content' => 'x', 'post_author' => 1 ],
			12 => [ 'ID' => 12, 'post_type' => 'post', 'post_status' => 'publish', 'post_password' => 'pw', 'post_title' => 'Secret', 'post_content' => 'x', 'post_author' => 1 ],
		];
	}

	private function make(): Abilities {
		return new Abilities( Core::get_default_settings() );
	}

	public function test_hooks_and_registration(): void {
		global $si_test_state;
		$abilities = $this->make();
		$this->assertArrayHasKey( 'wp_abilities_api_init', $si_test_state['actions'] );
		$this->assertArrayHasKey( 'wp_abilities_api_categories_init', $si_test_state['actions'] );

		$abilities->register_abilities();
		$names = array_keys( $si_test_state['abilities'] );

		$this->assertContains( 'jat-search-intel/search-content', $names );
		$this->assertContains( 'jat-search-intel/update-seo-meta', $names );
		foreach ( $si_test_state['abilities'] as $name => $args ) {
			$this->assertMatchesRegularExpression( '#^[a-z0-9-]+/[a-z0-9-]+$#', $name );
			$this->assertSame( Abilities::CATEGORY, $args['category'] );
			$this->assertIsCallable( $args['execute_callback'] );
			$this->assertIsCallable( $args['permission_callback'] );
			$this->assertArrayHasKey( 'readonly', $args['meta']['annotations'] );
		}
	}

	public function test_write_abilities_are_not_public_or_readonly(): void {
		$defs = $this->make()->definitions();
		$this->assertFalse( $defs['jat-search-intel/update-seo-meta']['meta']['annotations']['readonly'] );
		$this->assertNotSame( '__return_true', $defs['jat-search-intel/update-seo-meta']['permission_callback'] );
		$this->assertNotSame( '__return_true', $defs['jat-search-intel/analyze-post']['permission_callback'] );
		$this->assertSame( '__return_true', $defs['jat-search-intel/search-content']['permission_callback'] );
	}

	public function test_only_published_public_indexable_posts_are_exposed(): void {
		global $si_test_state;
		$a = $this->make();
		$this->assertTrue( $a->is_exposable( 10 ) );
		$this->assertFalse( $a->is_exposable( 11 ), 'drafts' );
		$this->assertFalse( $a->is_exposable( 12 ), 'password protected' );
		$this->assertFalse( $a->is_exposable( 999 ), 'missing' );

		$si_test_state['post_meta'][10]['_jat_search_intel_ai_noindex'] = '1';
		$this->assertFalse( $a->is_exposable( 10 ), 'excluded from AI' );
	}

	public function test_can_edit_post_requires_a_post_id(): void {
		$a = $this->make();
		$this->assertFalse( $a->can_edit_post( [] ) );
		$this->assertTrue( $a->can_edit_post( [ 'post_id' => 10 ] ) );
	}

	public function test_update_seo_meta_sets_clears_and_skips(): void {
		global $si_test_state;
		$si_test_state['post_meta'][10]['_jat_search_intel_focus_keyword'] = 'old';

		$result = $this->make()->update_seo_meta( [
			'post_id'       => 10,
			'title'         => '<b>New title</b>',
			'focus_keyword' => '',
		] );

		$this->assertSame( [ 'title', 'focus_keyword' ], $result['updated'] );
		$this->assertSame( 'New title', $si_test_state['post_meta'][10]['_jat_search_intel_title'] );
		$this->assertArrayNotHasKey( '_jat_search_intel_focus_keyword', $si_test_state['post_meta'][10] );
		$this->assertArrayNotHasKey( '_jat_search_intel_description', $si_test_state['post_meta'][10] );
	}

	public function test_update_seo_meta_unknown_post(): void {
		$this->assertInstanceOf( \WP_Error::class, $this->make()->update_seo_meta( [ 'post_id' => 999 ] ) );
	}
}
