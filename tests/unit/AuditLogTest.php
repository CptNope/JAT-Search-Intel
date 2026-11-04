<?php
namespace JAT_Search_Intel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use JAT_Search_Intel\Audit_Log;

class AuditLogTest extends TestCase {

	private Audit_Log $audit;

	protected function setUp(): void {
		global $si_test_state;
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

		$this->audit = new Audit_Log( [ 'enable_audit_log' => true ] );
	}

	// ── Constructor ──────────────────────────────────────────────────────

	public function test_constructor_registers_update_option_hook(): void {
		global $si_test_state;
		$hooks = array_column( $si_test_state['actions']['update_option_jat_search_intel_settings'] ?? [], 'callback' );
		$found = false;
		foreach ( $hooks as $cb ) {
			if ( is_array( $cb ) && $cb[1] === 'log_settings_change' ) $found = true;
		}
		$this->assertTrue( $found );
	}

	public function test_constructor_disabled_does_not_hook(): void {
		global $si_test_state;
		$si_test_state['actions'] = [];
		new Audit_Log( [ 'enable_audit_log' => false ] );
		$hooks = $si_test_state['actions']['update_option_jat_search_intel_settings'] ?? [];
		$this->assertEmpty( $hooks );
	}

	// ── add_entry / get_log ──────────────────────────────────────────────

	public function test_get_log_returns_empty_initially(): void {
		$log = $this->audit->get_log();
		$this->assertIsArray( $log );
		$this->assertEmpty( $log );
	}

	public function test_add_entry_stores_entry(): void {
		$this->audit->add_entry( 'test_action', [ 'key' => 'value' ] );

		$log = $this->audit->get_log();
		$this->assertCount( 1, $log );
		$this->assertEquals( 'test_action', $log[0]['action'] );
		$this->assertEquals( [ 'key' => 'value' ], $log[0]['details'] );
	}

	public function test_add_entry_prepends_newest_first(): void {
		$this->audit->add_entry( 'first', [] );
		$this->audit->add_entry( 'second', [] );

		$log = $this->audit->get_log();
		$this->assertEquals( 'second', $log[0]['action'] );
		$this->assertEquals( 'first', $log[1]['action'] );
	}

	public function test_add_entry_includes_user_id(): void {
		global $si_test_state;
		$si_test_state['current_user'] = 42;

		$this->audit->add_entry( 'test', [] );

		$log = $this->audit->get_log();
		$this->assertEquals( 42, $log[0]['user_id'] );
	}

	public function test_add_entry_includes_timestamp(): void {
		$this->audit->add_entry( 'test', [] );

		$log = $this->audit->get_log();
		$this->assertNotEmpty( $log[0]['timestamp'] );
	}

	public function test_add_entry_includes_ip_hash(): void {
		$this->audit->add_entry( 'test', [] );

		$log = $this->audit->get_log();
		$this->assertNotEmpty( $log[0]['ip_hash'] );
		// Should be a SHA-256 hash (64 hex chars)
		$this->assertEquals( 64, strlen( $log[0]['ip_hash'] ) );
	}

	// ── clear_log ────────────────────────────────────────────────────────

	public function test_clear_log_removes_all_entries(): void {
		$this->audit->add_entry( 'test1', [] );
		$this->audit->add_entry( 'test2', [] );
		$this->assertNotEmpty( $this->audit->get_log() );

		$this->audit->clear_log();
		$this->assertEmpty( $this->audit->get_log() );
	}

	// ── diff_settings ────────────────────────────────────────────────────

	public function test_diff_settings_detects_changes(): void {
		$old = [ 'enable_seo' => true, 'separator' => '|' ];
		$new = [ 'enable_seo' => false, 'separator' => '|' ];

		$diff = $this->audit->diff_settings( $old, $new );

		$this->assertArrayHasKey( 'enable_seo', $diff );
		$this->assertTrue( $diff['enable_seo']['old'] );
		$this->assertFalse( $diff['enable_seo']['new'] );
		$this->assertArrayNotHasKey( 'separator', $diff );
	}

	public function test_diff_settings_detects_new_keys(): void {
		$old = [ 'a' => 1 ];
		$new = [ 'a' => 1, 'b' => 2 ];

		$diff = $this->audit->diff_settings( $old, $new );

		$this->assertArrayHasKey( 'b', $diff );
		$this->assertNull( $diff['b']['old'] );
		$this->assertEquals( 2, $diff['b']['new'] );
	}

	public function test_diff_settings_detects_removed_keys(): void {
		$old = [ 'a' => 1, 'b' => 2 ];
		$new = [ 'a' => 1 ];

		$diff = $this->audit->diff_settings( $old, $new );

		$this->assertArrayHasKey( 'b', $diff );
		$this->assertEquals( 2, $diff['b']['old'] );
		$this->assertNull( $diff['b']['new'] );
	}

	public function test_diff_settings_empty_when_identical(): void {
		$settings = [ 'a' => 1, 'b' => 'two' ];
		$diff     = $this->audit->diff_settings( $settings, $settings );
		$this->assertEmpty( $diff );
	}

	// ── log_settings_change ──────────────────────────────────────────────

	public function test_log_settings_change_logs_when_different(): void {
		$old = [ 'enable_seo' => true ];
		$new = [ 'enable_seo' => false ];

		$this->audit->log_settings_change( $old, $new );

		$log = $this->audit->get_log();
		$this->assertCount( 1, $log );
		$this->assertEquals( 'settings_updated', $log[0]['action'] );
	}

	public function test_log_settings_change_skips_when_identical(): void {
		$settings = [ 'enable_seo' => true ];

		$this->audit->log_settings_change( $settings, $settings );

		$log = $this->audit->get_log();
		$this->assertEmpty( $log );
	}

	public function test_log_settings_change_skips_non_array(): void {
		$this->audit->log_settings_change( 'string', 'other' );

		$log = $this->audit->get_log();
		$this->assertEmpty( $log );
	}
}
