<?php
namespace JAT_Search_Intel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use JAT_Search_Intel\RBAC;

class RBACTest extends TestCase {

	protected function setUp(): void {
		global $si_test_state;
		$si_test_state = [
			'options'    => [],
			'post_meta'  => [],
			'posts'      => [],
			'transients' => [],
			'users'      => [],
			'roles'      => [],
			'actions'    => [],
		];
	}

	private function make( array $settings = [] ): RBAC {
		return new RBAC( array_merge( [ 'enable_rbac' => true ], $settings ) );
	}

	// ── Role Caps ────────────────────────────────────────────────────────────

	public function test_get_seo_editor_caps_has_view(): void {
		$rbac = $this->make();
		$caps = $rbac->get_seo_editor_caps();

		$this->assertTrue( $caps[ RBAC::CAP_VIEW ] );
	}

	public function test_get_seo_editor_caps_has_module_caps(): void {
		$rbac = $this->make();
		$caps = $rbac->get_seo_editor_caps();

		$this->assertTrue( $caps['jat_search_intel_manage_seo'] );
		$this->assertTrue( $caps['jat_search_intel_manage_aeo'] );
		$this->assertTrue( $caps['jat_search_intel_manage_geo'] );
		$this->assertTrue( $caps['jat_search_intel_manage_sitemap'] );
	}

	public function test_get_seo_editor_caps_no_settings(): void {
		$rbac = $this->make();
		$caps = $rbac->get_seo_editor_caps();

		$this->assertFalse( $caps['jat_search_intel_manage_settings'] );
	}

	public function test_get_seo_editor_caps_has_wp_basics(): void {
		$rbac = $this->make();
		$caps = $rbac->get_seo_editor_caps();

		$this->assertTrue( $caps['read'] );
		$this->assertTrue( $caps['edit_posts'] );
	}

	// ── Module Access ────────────────────────────────────────────────────────

	public function test_check_module_access_admin_always_true(): void {
		global $si_test_state;
		$si_test_state['users'][1] = [
			'ID' => 1, 'user_login' => 'admin', 'roles' => [ 'administrator' ],
			'caps' => [ 'manage_options' => true ],
			'exists' => true,
		];
		// Make exists() work by using a mock-like approach.
		$user = $this->createUserObj( 1, [ 'manage_options' => true ] );

		$rbac = $this->make();
		$result = $rbac->check_module_access( false, 'seo', 1 );

		// user_can checks caps property — admin has manage_options.
		$this->assertTrue( $result );
	}

	public function test_check_module_access_user_with_cap(): void {
		global $si_test_state;
		$si_test_state['users'][2] = [
			'ID' => 2, 'user_login' => 'editor', 'roles' => [ 'jat_search_intel_seo_editor' ],
			'caps' => [ 'jat_search_intel_manage_seo' => true ],
		];

		$rbac = $this->make();
		// user_can with our stub checks caps array.
		$result = $rbac->check_module_access( false, 'seo', 2 );
		$this->assertTrue( $result );
	}

	public function test_check_module_access_user_without_cap(): void {
		global $si_test_state;
		$si_test_state['users'][3] = [
			'ID' => 3, 'user_login' => 'author', 'roles' => [ 'author' ],
			'caps' => [ 'jat_search_intel_view_scores' => true ],
		];

		$rbac = $this->make();
		$result = $rbac->check_module_access( false, 'seo', 3 );
		$this->assertFalse( $result );
	}

	public function test_check_module_access_invalid_user(): void {
		$rbac = $this->make();
		$result = $rbac->check_module_access( false, 'seo', 999 );
		$this->assertFalse( $result );
	}

	public function test_check_module_access_unknown_module(): void {
		global $si_test_state;
		$si_test_state['users'][1] = [
			'ID' => 1, 'user_login' => 'user', 'roles' => [ 'editor' ],
			'caps' => [ 'jat_search_intel_manage_seo' => true ],
		];

		$rbac = $this->make();
		$result = $rbac->check_module_access( false, 'nonexistent', 1 );
		$this->assertFalse( $result );
	}

	// ── Edit Access ──────────────────────────────────────────────────────────

	public function test_check_edit_access_admin(): void {
		global $si_test_state;
		$si_test_state['users'][1] = [
			'ID' => 1, 'user_login' => 'admin', 'roles' => [ 'administrator' ],
			'caps' => [ 'manage_options' => true ],
		];

		$rbac = $this->make();
		$this->assertTrue( $rbac->check_edit_access( false, 1 ) );
	}

	public function test_check_edit_access_seo_editor(): void {
		global $si_test_state;
		$si_test_state['users'][2] = [
			'ID' => 2, 'user_login' => 'editor', 'roles' => [ 'jat_search_intel_seo_editor' ],
			'caps' => [ 'jat_search_intel_manage_seo' => true ],
		];

		$rbac = $this->make();
		$this->assertTrue( $rbac->check_edit_access( false, 2 ) );
	}

	public function test_check_edit_access_view_only_user(): void {
		global $si_test_state;
		$si_test_state['users'][3] = [
			'ID' => 3, 'user_login' => 'author', 'roles' => [ 'author' ],
			'caps' => [ 'jat_search_intel_view_scores' => true ],
		];

		$rbac = $this->make();
		$this->assertFalse( $rbac->check_edit_access( false, 3 ) );
	}

	// ── View Only ────────────────────────────────────────────────────────────

	public function test_is_view_only_for_author(): void {
		global $si_test_state;
		$si_test_state['users'][3] = [
			'ID' => 3, 'user_login' => 'author', 'roles' => [ 'author' ],
			'caps' => [ 'jat_search_intel_view_scores' => true ],
		];

		$rbac = $this->make();
		$this->assertTrue( $rbac->is_view_only( 3 ) );
	}

	public function test_is_view_only_false_for_admin(): void {
		global $si_test_state;
		$si_test_state['users'][1] = [
			'ID' => 1, 'user_login' => 'admin', 'roles' => [ 'administrator' ],
			'caps' => [ 'manage_options' => true, 'jat_search_intel_view_scores' => true ],
		];

		$rbac = $this->make();
		$this->assertFalse( $rbac->is_view_only( 1 ) );
	}

	public function test_is_view_only_false_for_seo_editor(): void {
		global $si_test_state;
		$si_test_state['users'][2] = [
			'ID' => 2, 'user_login' => 'editor', 'roles' => [ 'jat_search_intel_seo_editor' ],
			'caps' => [ 'jat_search_intel_view_scores' => true, 'jat_search_intel_manage_seo' => true ],
		];

		$rbac = $this->make();
		$this->assertFalse( $rbac->is_view_only( 2 ) );
	}

	// ── Settings Lock ────────────────────────────────────────────────────────

	public function test_settings_locked_for_non_admin(): void {
		global $si_test_state;
		$si_test_state['users'][2] = [
			'ID' => 2, 'user_login' => 'editor', 'roles' => [ 'editor' ],
			'caps' => [ 'jat_search_intel_manage_seo' => true ],
		];

		$rbac = $this->make( [ 'enable_rbac' => true, 'enable_settings_lock' => true ] );
		$this->assertTrue( $rbac->is_settings_locked( 2 ) );
	}

	public function test_settings_not_locked_for_admin(): void {
		global $si_test_state;
		$si_test_state['users'][1] = [
			'ID' => 1, 'user_login' => 'admin', 'roles' => [ 'administrator' ],
			'caps' => [ 'manage_options' => true ],
		];

		$rbac = $this->make( [ 'enable_rbac' => true, 'enable_settings_lock' => true ] );
		$this->assertFalse( $rbac->is_settings_locked( 1 ) );
	}

	public function test_settings_not_locked_when_disabled(): void {
		$rbac = $this->make( [ 'enable_rbac' => true, 'enable_settings_lock' => false ] );
		$this->assertFalse( $rbac->is_settings_locked( 999 ) );
	}

	// ── Disabled ─────────────────────────────────────────────────────────────

	public function test_disabled_does_not_register(): void {
		global $si_test_state;
		$si_test_state['actions'] = [];

		$rbac = new RBAC( [ 'enable_rbac' => false ] );
		$this->assertArrayNotHasKey( 'admin_init', $si_test_state['actions'] ?? [] );
	}

	// ── Permissions Summary ──────────────────────────────────────────────────

	public function test_get_permissions_summary_empty(): void {
		$rbac = $this->make();
		$this->assertSame( [], $rbac->get_permissions_summary() );
	}

	// ── Helper ───────────────────────────────────────────────────────────────

	private function createUserObj( int $id, array $caps ): void {
		global $si_test_state;
		$si_test_state['users'][ $id ] = array_merge(
			$si_test_state['users'][ $id ] ?? [],
			[ 'caps' => $caps ]
		);
	}
}
