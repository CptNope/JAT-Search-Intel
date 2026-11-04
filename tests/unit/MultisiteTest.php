<?php
namespace JAT_Search_Intel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use JAT_Search_Intel\Multisite;

class MultisiteTest extends TestCase {

	protected function setUp(): void {
		global $si_test_state;
		$si_test_state = [
			'options'      => [],
			'post_meta'    => [],
			'posts'        => [],
			'transients'   => [],
			'site_options' => [],
			'is_multisite' => true,
			'actions'      => [],
		];
	}

	private function make( array $settings = [] ): Multisite {
		return new Multisite( array_merge( [ 'enable_multisite' => true ], $settings ) );
	}

	// ── Network Defaults ─────────────────────────────────────────────────────

	public function test_get_network_defaults_empty(): void {
		$ms = $this->make();
		$this->assertSame( [], $ms->get_network_defaults() );
	}

	public function test_save_and_get_network_defaults(): void {
		$ms = $this->make();
		$ms->save_network_defaults( [ 'enable_schema' => true, 'enable_geo' => true ] );
		$defaults = $ms->get_network_defaults();
		$this->assertTrue( $defaults['enable_schema'] );
		$this->assertTrue( $defaults['enable_geo'] );
	}

	public function test_get_network_defaults_returns_empty_when_not_multisite(): void {
		global $si_test_state;
		$si_test_state['is_multisite'] = false;
		$ms = $this->make();
		$this->assertSame( [], $ms->get_network_defaults() );
	}

	public function test_save_network_defaults_returns_false_when_not_multisite(): void {
		global $si_test_state;
		$si_test_state['is_multisite'] = false;
		$ms = $this->make();
		$this->assertFalse( $ms->save_network_defaults( [ 'enable_schema' => true ] ) );
	}

	// ── Effective Settings ───────────────────────────────────────────────────

	public function test_get_effective_settings_merges_network_defaults(): void {
		global $si_test_state;
		$si_test_state['site_options']['jat_search_intel_network_defaults'] = [
			'enable_schema' => true,
		];

		$ms = $this->make();
		$effective = $ms->get_effective_settings();
		$this->assertTrue( $effective['enable_schema'] );
	}

	public function test_get_effective_settings_site_override_wins(): void {
		global $si_test_state;
		$si_test_state['site_options']['jat_search_intel_network_defaults'] = [
			'enable_schema' => true,
		];
		$si_test_state['options']['jat_search_intel_settings'] = [
			'enable_schema' => false,
		];
		$si_test_state['options']['jat_search_intel_site_overrides'] = [ 'enable_schema' ];

		$ms = $this->make();
		$effective = $ms->get_effective_settings();
		$this->assertFalse( $effective['enable_schema'] );
	}

	// ── Site Overrides ───────────────────────────────────────────────────────

	public function test_set_site_override(): void {
		global $si_test_state;
		$ms = $this->make();
		$ms->set_site_override( 'enable_geo', true );

		$overrides = $si_test_state['options']['jat_search_intel_site_overrides'];
		$this->assertContains( 'enable_geo', $overrides );
	}

	public function test_remove_site_override(): void {
		global $si_test_state;
		$si_test_state['options']['jat_search_intel_site_overrides'] = [ 'enable_geo', 'enable_schema' ];

		$ms = $this->make();
		$ms->remove_site_override( 'enable_geo' );

		$overrides = $si_test_state['options']['jat_search_intel_site_overrides'];
		$this->assertNotContains( 'enable_geo', $overrides );
		$this->assertContains( 'enable_schema', $overrides );
	}

	// ── Network Report ───────────────────────────────────────────────────────

	public function test_get_network_report_empty_when_not_multisite(): void {
		global $si_test_state;
		$si_test_state['is_multisite'] = false;
		$ms = $this->make();
		$this->assertSame( [], $ms->get_network_report() );
	}

	public function test_get_network_report_returns_array(): void {
		$ms = $this->make();
		$report = $ms->get_network_report();
		$this->assertIsArray( $report );
	}

	// ── Apply Defaults to New Site ───────────────────────────────────────────

	public function test_apply_defaults_to_new_site(): void {
		global $si_test_state;
		$si_test_state['site_options']['jat_search_intel_network_defaults'] = [
			'enable_schema' => true,
		];

		$ms = $this->make();
		$site = (object) [ 'blog_id' => 2 ];
		$ms->apply_defaults_to_new_site( $site, [] );

		$settings = $si_test_state['options']['jat_search_intel_settings'] ?? [];
		$this->assertTrue( $settings['enable_schema'] );
	}

	public function test_apply_defaults_skips_when_empty(): void {
		global $si_test_state;
		$ms = $this->make();
		$site = (object) [ 'blog_id' => 2 ];
		$ms->apply_defaults_to_new_site( $site, [] );

		$this->assertArrayNotHasKey( 'jat_search_intel_settings', $si_test_state['options'] );
	}

	// ── Not Multisite ────────────────────────────────────────────────────────

	public function test_no_hooks_when_not_multisite(): void {
		global $si_test_state;
		$si_test_state['is_multisite'] = false;
		$si_test_state['actions'] = [];

		$ms = $this->make();

		$this->assertArrayNotHasKey( 'network_admin_menu', $si_test_state['actions'] ?? [] );
	}
}
