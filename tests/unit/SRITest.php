<?php
namespace JAT_Search_Intel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use JAT_Search_Intel\SRI;

class SRITest extends TestCase {

	private SRI $sri;

	protected function setUp(): void {
		global $si_test_state;
		$si_test_state['options'] = [];
		$si_test_state['filters'] = [];

		$this->sri = new SRI( [
			'enable_sri' => true,
		] );
	}

	public function test_generate_hash_for_existing_file(): void {
		// Use the bootstrap file as a real file to hash.
		$file = __DIR__ . '/../bootstrap.php';
		$hash = $this->sri->generate_hash( $file );

		$this->assertNotEmpty( $hash );
		$this->assertStringStartsWith( 'sha256-', $hash );
	}

	public function test_generate_hash_returns_empty_for_missing_file(): void {
		$hash = $this->sri->generate_hash( '/nonexistent/file.js' );
		$this->assertEquals( '', $hash );
	}

	public function test_generate_hash_consistency(): void {
		$file  = __DIR__ . '/../bootstrap.php';
		$hash1 = $this->sri->generate_hash( $file );
		$hash2 = $this->sri->generate_hash( $file );

		$this->assertEquals( $hash1, $hash2 );
	}

	public function test_is_plugin_asset_true(): void {
		$this->assertTrue( $this->sri->is_plugin_asset( 'https://example.com/wp-content/plugins/jat-search-intel/assets/admin.js' ) );
	}

	public function test_is_plugin_asset_false(): void {
		$this->assertFalse( $this->sri->is_plugin_asset( 'https://example.com/wp-content/plugins/other-plugin/script.js' ) );
	}

	public function test_url_to_path_resolves_plugin_url(): void {
		$path = $this->sri->url_to_path( JAT_SEARCH_INTEL_URL . 'assets/admin.js' );
		$this->assertEquals( JAT_SEARCH_INTEL_DIR . 'assets/admin.js', $path );
	}

	public function test_url_to_path_strips_query_string(): void {
		$path = $this->sri->url_to_path( JAT_SEARCH_INTEL_URL . 'assets/admin.js?ver=1.0' );
		$this->assertEquals( JAT_SEARCH_INTEL_DIR . 'assets/admin.js', $path );
	}

	public function test_url_to_path_returns_empty_for_external(): void {
		$path = $this->sri->url_to_path( 'https://cdn.example.com/lib.js' );
		$this->assertEquals( '', $path );
	}

	public function test_add_script_integrity_injects_attribute(): void {
		$tag = '<script type="text/javascript" src="' . JAT_SEARCH_INTEL_URL . 'assets/admin.js"></script>';

		// Pre-cache a hash for the handle.
		global $si_test_state;
		$si_test_state['options']['jat_search_intel_sri_hashes'] = [
			'si-admin' => 'sha256-abc123',
		];
		$sri = new SRI( [ 'enable_sri' => true ] );

		$result = $sri->add_script_integrity( $tag, 'si-admin', JAT_SEARCH_INTEL_URL . 'assets/admin.js' );

		$this->assertStringContainsString( 'integrity="sha256-abc123"', $result );
		$this->assertStringContainsString( 'crossorigin="anonymous"', $result );
	}

	public function test_add_script_integrity_skips_non_plugin(): void {
		$tag = '<script src="https://cdn.example.com/lib.js"></script>';

		$result = $this->sri->add_script_integrity( $tag, 'external-lib', 'https://cdn.example.com/lib.js' );

		$this->assertStringNotContainsString( 'integrity=', $result );
		$this->assertEquals( $tag, $result );
	}

	public function test_add_script_integrity_skips_if_already_present(): void {
		$tag = '<script integrity="sha256-existing" src="' . JAT_SEARCH_INTEL_URL . 'assets/admin.js"></script>';

		global $si_test_state;
		$si_test_state['options']['jat_search_intel_sri_hashes'] = [
			'si-admin' => 'sha256-newhash',
		];
		$sri = new SRI( [ 'enable_sri' => true ] );

		$result = $sri->add_script_integrity( $tag, 'si-admin', JAT_SEARCH_INTEL_URL . 'assets/admin.js' );

		// Should not duplicate.
		$this->assertStringContainsString( 'sha256-existing', $result );
		$this->assertStringNotContainsString( 'sha256-newhash', $result );
	}

	public function test_add_style_integrity_injects_attribute(): void {
		$tag = '<link rel="stylesheet" href="' . JAT_SEARCH_INTEL_URL . 'assets/admin.css" />';

		global $si_test_state;
		$si_test_state['options']['jat_search_intel_sri_hashes'] = [
			'si-admin-css' => 'sha256-xyz789',
		];
		$sri = new SRI( [ 'enable_sri' => true ] );

		$result = $sri->add_style_integrity( $tag, 'si-admin-css', JAT_SEARCH_INTEL_URL . 'assets/admin.css', 'all' );

		$this->assertStringContainsString( 'integrity="sha256-xyz789"', $result );
		$this->assertStringContainsString( 'crossorigin="anonymous"', $result );
	}

	public function test_add_style_integrity_skips_non_plugin(): void {
		$tag = '<link rel="stylesheet" href="https://cdn.example.com/style.css" />';

		$result = $this->sri->add_style_integrity( $tag, 'external-css', 'https://cdn.example.com/style.css', 'all' );

		$this->assertEquals( $tag, $result );
	}

	public function test_regenerate_hashes_clears_cache(): void {
		global $si_test_state;
		$si_test_state['options']['jat_search_intel_sri_hashes'] = [ 'handle' => 'sha256-old' ];

		$this->sri->regenerate_hashes();

		$this->assertArrayNotHasKey( 'jat_search_intel_sri_hashes', $si_test_state['options'] );
	}

	public function test_get_hash_returns_empty_for_external(): void {
		$hash = $this->sri->get_hash( 'ext-lib', 'https://cdn.example.com/lib.js' );
		$this->assertEquals( '', $hash );
	}

	public function test_registers_hooks_when_enabled(): void {
		global $si_test_state;

		$this->assertArrayHasKey( 'script_loader_tag', $si_test_state['filters'] ?? [] );
		$this->assertArrayHasKey( 'style_loader_tag', $si_test_state['filters'] ?? [] );
	}

	public function test_disabled_sri_does_not_register(): void {
		global $si_test_state;
		$si_test_state['filters'] = [];

		$sri = new SRI( [ 'enable_sri' => false ] );

		$this->assertArrayNotHasKey( 'script_loader_tag', $si_test_state['filters'] ?? [] );
		$this->assertArrayNotHasKey( 'style_loader_tag', $si_test_state['filters'] ?? [] );
	}
}
