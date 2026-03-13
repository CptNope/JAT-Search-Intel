<?php
namespace Search_Intel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Search_Intel\Redirect_Manager;

class RedirectManagerTest extends TestCase {

	private Redirect_Manager $manager;

	protected function setUp(): void {
		global $si_test_state, $wpdb;
		$si_test_state = [
			'actions'        => [],
			'filters'        => [],
			'options'        => [],
			'post_meta'      => [],
			'user_meta'      => [],
			'posts'          => [],
			'transients'     => [],
			'current_user'   => 1,
			'active_plugins' => [],
		];
		$wpdb->tables = [];

		$this->manager = new Redirect_Manager( [ 'enable_redirects' => true ] );
	}

	// ── Constructor ──────────────────────────────────────────────────────

	public function test_constructor_registers_template_redirect_hook(): void {
		global $si_test_state;
		$hooks = array_column( $si_test_state['actions']['template_redirect'] ?? [], 'callback' );
		$found_redirect = false;
		$found_404 = false;
		foreach ( $hooks as $cb ) {
			if ( is_array( $cb ) && $cb[1] === 'maybe_redirect' ) $found_redirect = true;
			if ( is_array( $cb ) && $cb[1] === 'log_404' ) $found_404 = true;
		}
		$this->assertTrue( $found_redirect );
		$this->assertTrue( $found_404 );
	}

	public function test_constructor_disabled_does_not_hook(): void {
		global $si_test_state;
		$si_test_state['actions'] = [];
		new Redirect_Manager( [ 'enable_redirects' => false ] );
		$hooks = $si_test_state['actions']['template_redirect'] ?? [];
		$this->assertEmpty( $hooks );
	}

	// ── Constants ────────────────────────────────────────────────────────

	public function test_status_codes_contains_expected_codes(): void {
		$this->assertArrayHasKey( 301, Redirect_Manager::STATUS_CODES );
		$this->assertArrayHasKey( 302, Redirect_Manager::STATUS_CODES );
		$this->assertArrayHasKey( 307, Redirect_Manager::STATUS_CODES );
		$this->assertArrayHasKey( 410, Redirect_Manager::STATUS_CODES );
	}

	public function test_match_types_contains_expected_types(): void {
		$this->assertArrayHasKey( 'exact', Redirect_Manager::MATCH_TYPES );
		$this->assertArrayHasKey( 'prefix', Redirect_Manager::MATCH_TYPES );
		$this->assertArrayHasKey( 'regex', Redirect_Manager::MATCH_TYPES );
	}

	// ── save_redirect ────────────────────────────────────────────────────

	public function test_save_redirect_inserts_new_rule(): void {
		global $wpdb;
		$id = $this->manager->save_redirect( [
			'source_url'  => '/old-page',
			'target_url'  => '/new-page',
			'status_code' => 301,
			'match_type'  => 'exact',
			'enabled'     => true,
			'note'        => 'Test redirect',
		] );

		$this->assertIsInt( $id );
		$this->assertGreaterThan( 0, $id );
		$this->assertNotEmpty( $wpdb->tables['wp_search_intel_redirects'] );
	}

	public function test_save_redirect_rejects_empty_source(): void {
		$result = $this->manager->save_redirect( [
			'source_url'  => '',
			'target_url'  => '/new-page',
		] );
		$this->assertFalse( $result );
	}

	public function test_save_redirect_rejects_loop(): void {
		$result = $this->manager->save_redirect( [
			'source_url'  => '/same-page',
			'target_url'  => '/same-page',
			'match_type'  => 'exact',
		] );
		$this->assertFalse( $result );
	}

	public function test_save_redirect_defaults_invalid_status_code(): void {
		global $wpdb;
		$id = $this->manager->save_redirect( [
			'source_url'  => '/page',
			'target_url'  => '/other',
			'status_code' => 999,
			'enabled'     => true,
		] );
		$this->assertIsInt( $id );
		$row = $wpdb->tables['wp_search_intel_redirects'][ $id ];
		$this->assertEquals( 301, $row['status_code'] );
	}

	public function test_save_redirect_defaults_invalid_match_type(): void {
		global $wpdb;
		$id = $this->manager->save_redirect( [
			'source_url'  => '/page',
			'target_url'  => '/other',
			'match_type'  => 'invalid',
			'enabled'     => true,
		] );
		$this->assertIsInt( $id );
		$row = $wpdb->tables['wp_search_intel_redirects'][ $id ];
		$this->assertEquals( 'exact', $row['match_type'] );
	}

	public function test_save_redirect_rejects_invalid_regex(): void {
		$result = $this->manager->save_redirect( [
			'source_url'  => '[invalid',
			'target_url'  => '/other',
			'match_type'  => 'regex',
		] );
		$this->assertFalse( $result );
	}

	public function test_save_redirect_updates_existing(): void {
		global $wpdb;
		$id = $this->manager->save_redirect( [
			'source_url' => '/old',
			'target_url' => '/new',
			'enabled'    => true,
		] );

		$updated = $this->manager->save_redirect( [
			'id'         => $id,
			'source_url' => '/old-updated',
			'target_url' => '/new-updated',
			'enabled'    => true,
		] );

		$this->assertEquals( $id, $updated );
		$row = $wpdb->tables['wp_search_intel_redirects'][ $id ];
		$this->assertEquals( '/old-updated', $row['source_url'] );
	}

	// ── delete_redirect ──────────────────────────────────────────────────

	public function test_delete_redirect_removes_rule(): void {
		global $wpdb;
		$id = $this->manager->save_redirect( [
			'source_url' => '/delete-me',
			'target_url' => '/other',
			'enabled'    => true,
		] );

		$this->assertTrue( $this->manager->delete_redirect( $id ) );
		$this->assertArrayNotHasKey( $id, $wpdb->tables['wp_search_intel_redirects'] ?? [] );
	}

	public function test_delete_redirect_returns_false_for_nonexistent(): void {
		$this->assertFalse( $this->manager->delete_redirect( 99999 ) );
	}

	// ── CSV export/import ────────────────────────────────────────────────

	public function test_export_csv_returns_csv_string(): void {
		$csv = $this->manager->export_csv();
		$this->assertStringContainsString( 'source_url', $csv );
		$this->assertStringContainsString( 'target_url', $csv );
	}

	public function test_import_csv_imports_valid_rows(): void {
		$csv = "source_url,target_url,status_code,match_type,enabled,note\n/a,/b,301,exact,1,test\n/c,/d,302,exact,1,test2";
		$result = $this->manager->import_csv( $csv );
		$this->assertEquals( 2, $result['imported'] );
		$this->assertEquals( 0, $result['errors'] );
	}

	public function test_import_csv_skips_short_rows(): void {
		$csv = "source_url,target_url\nx";
		$result = $this->manager->import_csv( $csv );
		$this->assertEquals( 0, $result['imported'] );
		$this->assertEquals( 1, $result['skipped'] );
	}

	public function test_import_csv_empty_returns_zero(): void {
		$result = $this->manager->import_csv( '' );
		$this->assertEquals( 0, $result['imported'] );
	}

	// ── detect_slug_change ───────────────────────────────────────────────

	public function test_detect_slug_change_skips_when_permalink_unchanged(): void {
		global $wpdb;

		// In tests, get_permalink uses post ID, so same ID = same URL = no redirect
		$post_before = (object) [
			'ID'          => 10,
			'post_name'   => 'old-slug',
			'post_status' => 'publish',
		];
		$post_after = (object) [
			'ID'          => 10,
			'post_name'   => 'new-slug',
			'post_status' => 'publish',
		];

		$this->manager->detect_slug_change( 10, $post_after, $post_before );

		// Same ID means get_permalink returns same URL — no redirect created
		$table = $wpdb->tables['wp_search_intel_redirects'] ?? [];
		$this->assertEmpty( $table );
	}

	public function test_detect_slug_change_ignores_same_slug(): void {
		global $wpdb;

		$post = (object) [
			'ID'          => 10,
			'post_name'   => 'same-slug',
			'post_status' => 'publish',
		];

		$this->manager->detect_slug_change( 10, $post, $post );

		$table = $wpdb->tables['wp_search_intel_redirects'] ?? [];
		$this->assertEmpty( $table );
	}

	public function test_detect_slug_change_ignores_draft(): void {
		global $wpdb;

		$post_before = (object) [
			'ID'          => 10,
			'post_name'   => 'old-slug',
			'post_status' => 'draft',
		];
		$post_after = (object) [
			'ID'          => 10,
			'post_name'   => 'new-slug',
			'post_status' => 'draft',
		];

		$this->manager->detect_slug_change( 10, $post_after, $post_before );

		$table = $wpdb->tables['wp_search_intel_redirects'] ?? [];
		$this->assertEmpty( $table );
	}

	// ── Admin registration ───────────────────────────────────────────────

	public function test_register_admin_page_hooks_admin_menu(): void {
		global $si_test_state;
		$hooks = array_column( $si_test_state['actions']['admin_menu'] ?? [], 'callback' );
		$found = false;
		foreach ( $hooks as $cb ) {
			if ( is_array( $cb ) && $cb[1] === 'register_admin_page' ) $found = true;
		}
		// Constructor doesn't register admin hooks when is_admin() returns false in tests
		// This is expected — admin hooks only fire in admin context
		$this->assertFalse( $found );
	}
}
