<?php
namespace JAT_Search_Intel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use JAT_Search_Intel\Privacy;

class PrivacyTest extends TestCase {

	private Privacy $privacy;

	protected function setUp(): void {
		global $si_test_state;
		$si_test_state = [
			'post_meta'      => [],
			'user_meta'      => [],
			'options'        => [],
			'transients'     => [],
			'actions'        => [],
			'filters'        => [],
			'posts'          => [],
			'current_user'   => 1,
			'users'          => [],
			'privacy_policy' => [],
		];

		// Test user with E-E-A-T data.
		$si_test_state['users'][1] = [
			'ID'         => 1,
			'user_email' => 'author@example.com',
		];
		$si_test_state['user_meta'][1] = [
			'jat_search_intel_credentials'      => [ 'PhD in Computer Science', 'AWS Certified' ],
			'jat_search_intel_expertise_areas'   => [ 'Machine Learning', 'Cloud Architecture' ],
			'jat_search_intel_years_experience'  => '12',
			'jat_search_intel_social_profiles'   => [ 'https://linkedin.com/in/test', 'https://github.com/test' ],
			'jat_search_intel_eeat_consent'      => '1',
		];

		$this->privacy = new Privacy();
	}

	// ── Constructor ─────────────────────────────────────────────────────────

	public function test_constructor_registers_admin_init_action(): void {
		global $si_test_state;

		$actions = array_keys( $si_test_state['actions'] );

		$this->assertContains( 'admin_init', $actions );
	}

	public function test_constructor_registers_exporter_filter(): void {
		global $si_test_state;

		$filters = array_keys( $si_test_state['filters'] );

		$this->assertContains( 'wp_privacy_personal_data_exporters', $filters );
	}

	public function test_constructor_registers_eraser_filter(): void {
		global $si_test_state;

		$filters = array_keys( $si_test_state['filters'] );

		$this->assertContains( 'wp_privacy_personal_data_erasers', $filters );
	}

	// ── register_exporter() ─────────────────────────────────────────────────

	public function test_register_exporter_adds_jat_search_intel_key(): void {
		$exporters = $this->privacy->register_exporter( [] );

		$this->assertArrayHasKey( 'jat-search-intel', $exporters );
		$this->assertArrayHasKey( 'exporter_friendly_name', $exporters['jat-search-intel'] );
		$this->assertArrayHasKey( 'callback', $exporters['jat-search-intel'] );
		$this->assertIsCallable( $exporters['jat-search-intel']['callback'] );
	}

	public function test_register_exporter_preserves_existing_exporters(): void {
		$existing  = [ 'other-plugin' => [ 'exporter_friendly_name' => 'Other', 'callback' => '__return_true' ] ];
		$exporters = $this->privacy->register_exporter( $existing );

		$this->assertArrayHasKey( 'other-plugin', $exporters );
		$this->assertArrayHasKey( 'jat-search-intel', $exporters );
	}

	// ── register_eraser() ───────────────────────────────────────────────────

	public function test_register_eraser_adds_jat_search_intel_key(): void {
		$erasers = $this->privacy->register_eraser( [] );

		$this->assertArrayHasKey( 'jat-search-intel', $erasers );
		$this->assertArrayHasKey( 'eraser_friendly_name', $erasers['jat-search-intel'] );
		$this->assertArrayHasKey( 'callback', $erasers['jat-search-intel'] );
		$this->assertIsCallable( $erasers['jat-search-intel']['callback'] );
	}

	// ── export_personal_data() ──────────────────────────────────────────────

	public function test_export_returns_empty_for_unknown_email(): void {
		$result = $this->privacy->export_personal_data( 'nobody@example.com' );

		$this->assertSame( [], $result['data'] );
		$this->assertTrue( $result['done'] );
	}

	public function test_export_returns_data_for_known_user(): void {
		$result = $this->privacy->export_personal_data( 'author@example.com' );

		$this->assertTrue( $result['done'] );
		$this->assertNotEmpty( $result['data'] );

		$group = $result['data'][0];
		$this->assertSame( 'jat-search-intel-author', $group['group_id'] );
		$this->assertSame( 'jat-search-intel-author-1', $group['item_id'] );
	}

	public function test_export_includes_all_populated_fields(): void {
		$result = $this->privacy->export_personal_data( 'author@example.com' );
		$data   = $result['data'][0]['data'];

		$names = array_column( $data, 'name' );

		$this->assertContains( 'Credentials / Qualifications', $names );
		$this->assertContains( 'Areas of Expertise', $names );
		$this->assertContains( 'Years of Experience', $names );
		$this->assertContains( 'Social / Professional Profiles', $names );
		$this->assertContains( 'E-E-A-T Data Consent', $names );
	}

	public function test_export_formats_arrays_as_comma_separated(): void {
		$result = $this->privacy->export_personal_data( 'author@example.com' );
		$data   = $result['data'][0]['data'];

		$creds = array_values( array_filter( $data, function ( $item ) {
			return $item['name'] === 'Credentials / Qualifications';
		} ) );

		$this->assertSame( 'PhD in Computer Science, AWS Certified', $creds[0]['value'] );
	}

	public function test_export_skips_empty_fields(): void {
		global $si_test_state;
		$si_test_state['user_meta'][1] = [
			'jat_search_intel_credentials' => [ 'PhD' ],
		];

		$result = $this->privacy->export_personal_data( 'author@example.com' );
		$data   = $result['data'][0]['data'];

		$this->assertCount( 1, $data );
		$this->assertSame( 'Credentials / Qualifications', $data[0]['name'] );
	}

	public function test_export_returns_empty_data_when_no_meta(): void {
		global $si_test_state;
		$si_test_state['user_meta'][1] = [];

		$result = $this->privacy->export_personal_data( 'author@example.com' );

		$this->assertSame( [], $result['data'] );
	}

	// ── erase_personal_data() ───────────────────────────────────────────────

	public function test_erase_returns_no_action_for_unknown_email(): void {
		$result = $this->privacy->erase_personal_data( 'nobody@example.com' );

		$this->assertFalse( $result['items_removed'] );
		$this->assertFalse( $result['items_retained'] );
		$this->assertTrue( $result['done'] );
	}

	public function test_erase_removes_all_user_meta(): void {
		global $si_test_state;

		$result = $this->privacy->erase_personal_data( 'author@example.com' );

		$this->assertTrue( $result['items_removed'] );
		$this->assertFalse( $result['items_retained'] );
		$this->assertTrue( $result['done'] );
		$this->assertNotEmpty( $result['messages'] );

		// Verify meta was actually deleted.
		$remaining = $si_test_state['user_meta'][1] ?? [];
		$this->assertArrayNotHasKey( 'jat_search_intel_credentials', $remaining );
		$this->assertArrayNotHasKey( 'jat_search_intel_expertise_areas', $remaining );
		$this->assertArrayNotHasKey( 'jat_search_intel_years_experience', $remaining );
		$this->assertArrayNotHasKey( 'jat_search_intel_social_profiles', $remaining );
		$this->assertArrayNotHasKey( 'jat_search_intel_eeat_consent', $remaining );
	}

	public function test_erase_message_includes_count(): void {
		$result = $this->privacy->erase_personal_data( 'author@example.com' );

		$this->assertStringContainsString( '5', $result['messages'][0] );
	}

	public function test_erase_returns_no_action_when_no_meta(): void {
		global $si_test_state;
		$si_test_state['user_meta'][1] = [];

		$result = $this->privacy->erase_personal_data( 'author@example.com' );

		$this->assertFalse( $result['items_removed'] );
		$this->assertEmpty( $result['messages'] );
	}

	// ── add_privacy_policy_content() ────────────────────────────────────────

	public function test_privacy_policy_content_is_registered(): void {
		global $si_test_state;

		$this->privacy->add_privacy_policy_content();

		$this->assertArrayHasKey( 'JAT Search Intel', $si_test_state['privacy_policy'] );
	}

	public function test_privacy_policy_content_covers_data_collection(): void {
		global $si_test_state;

		$this->privacy->add_privacy_policy_content();

		$content = $si_test_state['privacy_policy']['JAT Search Intel'];

		$this->assertStringContainsString( 'personal data we collect', $content );
		$this->assertStringContainsString( 'credentials', $content );
		$this->assertStringContainsString( 'expertise', $content );
	}

	public function test_privacy_policy_content_covers_data_storage(): void {
		global $si_test_state;

		$this->privacy->add_privacy_policy_content();

		$content = $si_test_state['privacy_policy']['JAT Search Intel'];

		$this->assertStringContainsString( 'stored locally', $content );
		$this->assertStringContainsString( 'does not transmit', $content );
	}

	public function test_privacy_policy_content_covers_export_deletion(): void {
		global $si_test_state;

		$this->privacy->add_privacy_policy_content();

		$content = $si_test_state['privacy_policy']['JAT Search Intel'];

		$this->assertStringContainsString( 'export', $content );
		$this->assertStringContainsString( 'erasure', $content );
	}

	public function test_privacy_policy_content_mentions_search_engine_pinging(): void {
		global $si_test_state;

		$this->privacy->add_privacy_policy_content();

		$content = $si_test_state['privacy_policy']['JAT Search Intel'];

		$this->assertStringContainsString( 'opt-in', $content );
		$this->assertStringContainsString( 'sitemap', $content );
	}
}
