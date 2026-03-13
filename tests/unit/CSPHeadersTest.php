<?php
namespace Search_Intel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Search_Intel\CSP_Headers;

class CSPHeadersTest extends TestCase {

	private CSP_Headers $csp;

	protected function setUp(): void {
		global $si_test_state;
		$si_test_state['actions'] = [];
		$si_test_state['filters'] = [];

		$this->csp = new CSP_Headers( [
			'enable_csp_headers' => true,
		] );
	}

	public function test_build_policy_admin_contains_self(): void {
		$policy = $this->csp->build_policy( 'admin' );

		$this->assertStringContainsString( "default-src 'self'", $policy );
		$this->assertStringContainsString( "script-src 'self'", $policy );
		$this->assertStringContainsString( "style-src 'self'", $policy );
		$this->assertStringContainsString( "frame-ancestors 'self'", $policy );
	}

	public function test_build_policy_admin_allows_images(): void {
		$policy = $this->csp->build_policy( 'admin' );

		$this->assertStringContainsString( "img-src 'self' data: https:", $policy );
	}

	public function test_build_policy_rest_is_strict(): void {
		$policy = $this->csp->build_policy( 'rest' );

		$this->assertStringContainsString( "default-src 'none'", $policy );
		$this->assertStringContainsString( "frame-ancestors 'none'", $policy );
		$this->assertStringNotContainsString( "script-src", $policy );
	}

	public function test_build_policy_admin_includes_form_action(): void {
		$policy = $this->csp->build_policy( 'admin' );

		$this->assertStringContainsString( "form-action 'self'", $policy );
	}

	public function test_build_policy_admin_includes_base_uri(): void {
		$policy = $this->csp->build_policy( 'admin' );

		$this->assertStringContainsString( "base-uri 'self'", $policy );
	}

	public function test_build_policy_rest_includes_base_uri_none(): void {
		$policy = $this->csp->build_policy( 'rest' );

		$this->assertStringContainsString( "base-uri 'none'", $policy );
	}

	public function test_is_plugin_admin_page_true(): void {
		$_GET['page'] = 'search-intel';
		$this->assertTrue( $this->csp->is_plugin_admin_page() );
		unset( $_GET['page'] );
	}

	public function test_is_plugin_admin_page_redirects(): void {
		$_GET['page'] = 'search-intel-redirects';
		$this->assertTrue( $this->csp->is_plugin_admin_page() );
		unset( $_GET['page'] );
	}

	public function test_is_plugin_admin_page_broken_links(): void {
		$_GET['page'] = 'search-intel-broken-links';
		$this->assertTrue( $this->csp->is_plugin_admin_page() );
		unset( $_GET['page'] );
	}

	public function test_is_plugin_admin_page_false(): void {
		$_GET['page'] = 'some-other-plugin';
		$this->assertFalse( $this->csp->is_plugin_admin_page() );
		unset( $_GET['page'] );
	}

	public function test_is_plugin_admin_page_no_page_param(): void {
		unset( $_GET['page'] );
		$this->assertFalse( $this->csp->is_plugin_admin_page() );
	}

	public function test_registers_hooks_when_enabled(): void {
		global $si_test_state;

		$this->assertArrayHasKey( 'admin_init', $si_test_state['actions'] ?? [] );
		$this->assertArrayHasKey( 'rest_pre_serve_request', $si_test_state['filters'] ?? [] );
	}

	public function test_disabled_csp_does_not_register(): void {
		global $si_test_state;
		$si_test_state['actions'] = [];
		$si_test_state['filters'] = [];

		$csp = new CSP_Headers( [ 'enable_csp_headers' => false ] );

		$this->assertArrayNotHasKey( 'admin_init', $si_test_state['actions'] ?? [] );
		$this->assertArrayNotHasKey( 'rest_pre_serve_request', $si_test_state['filters'] ?? [] );
	}

	public function test_policy_directives_semicolon_separated(): void {
		$policy = $this->csp->build_policy( 'admin' );

		$parts = explode( '; ', $policy );
		$this->assertGreaterThan( 3, count( $parts ) );
	}

	public function test_admin_policy_includes_connect_src(): void {
		$policy = $this->csp->build_policy( 'admin' );

		$this->assertStringContainsString( "connect-src 'self'", $policy );
	}

	public function test_admin_policy_includes_font_src(): void {
		$policy = $this->csp->build_policy( 'admin' );

		$this->assertStringContainsString( "font-src 'self' data:", $policy );
	}
}
