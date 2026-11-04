<?php
namespace JAT_Search_Intel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use JAT_Search_Intel\IndexNow;

class IndexNowTest extends TestCase {

	private IndexNow $indexnow;

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

		$this->indexnow = new IndexNow( [
			'enable_indexnow'    => true,
			'indexnow_api_key'   => 'test-key-123',
			'indexnow_providers' => [ 'bing', 'yandex', 'naver' ],
		] );
	}

	// ── Constructor ──────────────────────────────────────────────────────

	public function test_constructor_registers_publish_hooks_when_enabled(): void {
		global $si_test_state;
		$hooks = array_column( $si_test_state['actions']['publish_post'] ?? [], 'callback' );
		$found = false;
		foreach ( $hooks as $cb ) {
			if ( is_array( $cb ) && $cb[1] === 'ping_on_publish' ) $found = true;
		}
		$this->assertTrue( $found );
	}

	public function test_constructor_registers_update_hook(): void {
		global $si_test_state;
		$hooks = array_column( $si_test_state['actions']['post_updated'] ?? [], 'callback' );
		$found = false;
		foreach ( $hooks as $cb ) {
			if ( is_array( $cb ) && $cb[1] === 'ping_on_update' ) $found = true;
		}
		$this->assertTrue( $found );
	}

	public function test_constructor_registers_init_hook_for_key_file(): void {
		global $si_test_state;
		$hooks = array_column( $si_test_state['actions']['init'] ?? [], 'callback' );
		$found = false;
		foreach ( $hooks as $cb ) {
			if ( is_array( $cb ) && $cb[1] === 'serve_key_file' ) $found = true;
		}
		$this->assertTrue( $found );
	}

	public function test_constructor_disabled_does_not_hook_publish(): void {
		global $si_test_state;
		$si_test_state['actions'] = [];
		new IndexNow( [ 'enable_indexnow' => false, 'indexnow_api_key' => '' ] );
		$hooks = $si_test_state['actions']['publish_post'] ?? [];
		$this->assertEmpty( $hooks );
	}

	// ── Constants ────────────────────────────────────────────────────────

	public function test_endpoints_contains_bing(): void {
		$this->assertArrayHasKey( 'bing', IndexNow::ENDPOINTS );
		$this->assertStringContainsString( 'bing.com', IndexNow::ENDPOINTS['bing'] );
	}

	public function test_endpoints_contains_yandex(): void {
		$this->assertArrayHasKey( 'yandex', IndexNow::ENDPOINTS );
	}

	public function test_endpoints_contains_naver(): void {
		$this->assertArrayHasKey( 'naver', IndexNow::ENDPOINTS );
	}

	// ── submit_url ───────────────────────────────────────────────────────

	public function test_submit_url_returns_results_for_each_provider(): void {
		global $si_test_state;
		$si_test_state['remote_response'] = [
			'response' => [ 'code' => 200, 'message' => 'OK' ],
			'body'     => '',
		];

		$results = $this->indexnow->submit_url( 'https://example.com/test-page' );

		$this->assertArrayHasKey( 'bing', $results );
		$this->assertArrayHasKey( 'yandex', $results );
		$this->assertArrayHasKey( 'naver', $results );
	}

	public function test_submit_url_success_response(): void {
		global $si_test_state;
		$si_test_state['remote_response'] = [
			'response' => [ 'code' => 200, 'message' => 'OK' ],
			'body'     => '',
		];

		$results = $this->indexnow->submit_url( 'https://example.com/page' );

		$this->assertTrue( $results['bing']['success'] );
		$this->assertEquals( 200, $results['bing']['status'] );
	}

	public function test_submit_url_failure_response(): void {
		global $si_test_state;
		$si_test_state['remote_response'] = [
			'response' => [ 'code' => 422, 'message' => 'Unprocessable Entity' ],
			'body'     => '',
		];

		$results = $this->indexnow->submit_url( 'https://example.com/page' );

		$this->assertFalse( $results['bing']['success'] );
		$this->assertEquals( 422, $results['bing']['status'] );
	}

	public function test_submit_url_empty_key_returns_empty(): void {
		$indexnow = new IndexNow( [
			'enable_indexnow'  => true,
			'indexnow_api_key' => '',
		] );

		$results = $indexnow->submit_url( 'https://example.com/page' );
		$this->assertEmpty( $results );
	}

	public function test_submit_url_logs_entry(): void {
		global $si_test_state;
		$si_test_state['remote_response'] = [
			'response' => [ 'code' => 200, 'message' => 'OK' ],
			'body'     => '',
		];

		$this->indexnow->submit_url( 'https://example.com/logged-page' );

		$log = $this->indexnow->get_log();
		$this->assertNotEmpty( $log );
		$this->assertEquals( 'https://example.com/logged-page', $log[0]['url'] );
	}

	// ── Log management ───────────────────────────────────────────────────

	public function test_get_log_returns_empty_array_initially(): void {
		$log = $this->indexnow->get_log();
		$this->assertIsArray( $log );
		$this->assertEmpty( $log );
	}

	public function test_clear_log_removes_all_entries(): void {
		global $si_test_state;
		$si_test_state['remote_response'] = [
			'response' => [ 'code' => 200, 'message' => 'OK' ],
			'body'     => '',
		];

		$this->indexnow->submit_url( 'https://example.com/page1' );
		$this->indexnow->submit_url( 'https://example.com/page2' );

		$this->assertNotEmpty( $this->indexnow->get_log() );

		$this->indexnow->clear_log();
		$this->assertEmpty( $this->indexnow->get_log() );
	}
}
