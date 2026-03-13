<?php
namespace Search_Intel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Search_Intel\White_Label;

class WhiteLabelTest extends TestCase {

	protected function setUp(): void {
		global $si_test_state;
		$si_test_state = [
			'options'    => [],
			'post_meta'  => [],
			'posts'      => [],
			'transients' => [],
			'actions'    => [],
		];
	}

	private function make( array $settings = [] ): White_Label {
		return new White_Label( array_merge( [
			'enable_white_label' => true,
			'wl_plugin_name'     => 'My SEO Tool',
			'wl_plugin_uri'      => 'https://mytool.com',
			'wl_author_name'     => 'My Agency',
			'wl_author_uri'      => 'https://myagency.com',
			'wl_description'     => 'Custom SEO plugin',
			'wl_logo_url'        => 'https://mytool.com/logo.png',
			'wl_brand_color'     => '#ff6600',
			'wl_hide_branding'   => true,
		], $settings ) );
	}

	// ── Config ───────────────────────────────────────────────────────────────

	public function test_get_config_returns_all_fields(): void {
		$wl = $this->make();
		$config = $wl->get_config();

		$this->assertTrue( $config['enabled'] );
		$this->assertEquals( 'My SEO Tool', $config['plugin_name'] );
		$this->assertEquals( 'https://mytool.com', $config['plugin_uri'] );
		$this->assertEquals( 'My Agency', $config['author_name'] );
		$this->assertEquals( 'https://myagency.com', $config['author_uri'] );
		$this->assertEquals( 'Custom SEO plugin', $config['description'] );
		$this->assertEquals( 'https://mytool.com/logo.png', $config['logo_url'] );
		$this->assertEquals( '#ff6600', $config['brand_color'] );
		$this->assertTrue( $config['hide_branding'] );
	}

	public function test_get_config_defaults(): void {
		$wl = new White_Label( [ 'enable_white_label' => true ] );
		$config = $wl->get_config();

		$this->assertEquals( 'Search Intel', $config['plugin_name'] );
		$this->assertEquals( '#2271b1', $config['brand_color'] );
		$this->assertFalse( $config['hide_branding'] );
	}

	// ── Save Config ──────────────────────────────────────────────────────────

	public function test_save_config(): void {
		global $si_test_state;
		$wl = $this->make();
		$wl->save_config( [
			'wl_plugin_name' => 'New Name',
			'wl_brand_color' => '#123abc',
		] );

		$settings = $si_test_state['options']['search_intel_settings'];
		$this->assertEquals( 'New Name', $settings['wl_plugin_name'] );
		$this->assertEquals( '#123abc', $settings['wl_brand_color'] );
	}

	// ── Plugin Meta Filter ───────────────────────────────────────────────────

	public function test_filter_plugin_meta_replaces_name(): void {
		$wl = $this->make();

		$plugins = [
			'search-intel/search-intel.php' => [
				'Name'        => 'Search Intel',
				'Title'       => 'Search Intel',
				'Description' => 'Original description',
				'Author'      => 'Search Intel',
				'AuthorName'  => 'Search Intel',
				'AuthorURI'   => 'https://searchintel.io',
				'PluginURI'   => 'https://searchintel.io',
			],
		];

		$filtered = $wl->filter_plugin_meta( $plugins );

		$this->assertEquals( 'My SEO Tool', $filtered['search-intel/search-intel.php']['Name'] );
		$this->assertEquals( 'My SEO Tool', $filtered['search-intel/search-intel.php']['Title'] );
		$this->assertEquals( 'Custom SEO plugin', $filtered['search-intel/search-intel.php']['Description'] );
		$this->assertEquals( 'My Agency', $filtered['search-intel/search-intel.php']['Author'] );
		$this->assertEquals( 'https://myagency.com', $filtered['search-intel/search-intel.php']['AuthorURI'] );
		$this->assertEquals( 'https://mytool.com', $filtered['search-intel/search-intel.php']['PluginURI'] );
	}

	public function test_filter_plugin_meta_ignores_other_plugins(): void {
		$wl = $this->make();

		$plugins = [
			'other-plugin/other.php' => [
				'Name' => 'Other Plugin',
			],
		];

		$filtered = $wl->filter_plugin_meta( $plugins );
		$this->assertEquals( 'Other Plugin', $filtered['other-plugin/other.php']['Name'] );
	}

	// ── Brand Filters ────────────────────────────────────────────────────────

	public function test_get_custom_name(): void {
		$wl = $this->make();
		$this->assertEquals( 'My SEO Tool', $wl->get_custom_name( 'Search Intel' ) );
	}

	public function test_get_custom_name_fallback(): void {
		$wl = new White_Label( [ 'enable_white_label' => true ] );
		$this->assertEquals( 'Search Intel', $wl->get_custom_name( 'Search Intel' ) );
	}

	public function test_get_brand_color(): void {
		$wl = $this->make();
		$this->assertEquals( '#ff6600', $wl->get_brand_color( '#2271b1' ) );
	}

	public function test_get_brand_logo(): void {
		$wl = $this->make();
		$this->assertEquals( 'https://mytool.com/logo.png', $wl->get_brand_logo( '' ) );
	}

	// ── Reseller ─────────────────────────────────────────────────────────────

	public function test_export_reseller_config(): void {
		$wl = $this->make();
		$export = $wl->export_reseller_config();

		$this->assertArrayHasKey( 'white_label', $export );
		$this->assertArrayHasKey( 'version', $export );
		$this->assertArrayHasKey( 'exported', $export );
		$this->assertEquals( 'My SEO Tool', $export['white_label']['plugin_name'] );
	}

	public function test_import_reseller_config(): void {
		global $si_test_state;
		$wl = $this->make();

		$config = [
			'white_label' => [
				'plugin_name'  => 'Imported Tool',
				'brand_color'  => '#abcdef',
				'hide_branding' => true,
			],
		];

		$result = $wl->import_reseller_config( $config );
		$this->assertTrue( $result );

		$settings = $si_test_state['options']['search_intel_settings'];
		$this->assertTrue( $settings['enable_white_label'] );
		$this->assertEquals( 'Imported Tool', $settings['wl_plugin_name'] );
		$this->assertEquals( '#abcdef', $settings['wl_brand_color'] );
	}

	public function test_import_reseller_config_invalid(): void {
		$wl = $this->make();
		$this->assertFalse( $wl->import_reseller_config( [] ) );
		$this->assertFalse( $wl->import_reseller_config( [ 'white_label' => 'bad' ] ) );
	}

	// ── Disabled ─────────────────────────────────────────────────────────────

	public function test_disabled_does_not_register(): void {
		global $si_test_state;
		$si_test_state['actions'] = [];

		$wl = new White_Label( [ 'enable_white_label' => false ] );
		$this->assertArrayNotHasKey( 'admin_head', $si_test_state['actions'] ?? [] );
	}
}
