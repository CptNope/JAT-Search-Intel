<?php
namespace JAT_Search_Intel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use JAT_Search_Intel\Local_Business;

class LocalBusinessTest extends TestCase {

	private Local_Business $lb;

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

		$this->lb = new Local_Business( [
			'enable_local_business' => true,
			'local_business_locations' => [
				[
					'type'        => 'Restaurant',
					'name'        => 'Test Restaurant',
					'description' => 'A fine dining place',
					'url'         => 'https://example.com',
					'phone'       => '+1-555-0100',
					'email'       => 'hello@example.com',
					'image'       => 'https://example.com/photo.jpg',
					'street'      => '123 Main St',
					'city'        => 'Springfield',
					'state'       => 'IL',
					'zip'         => '62704',
					'country'     => 'US',
					'latitude'    => '39.7817',
					'longitude'   => '-89.6501',
					'price_range' => '$$',
					'service_areas' => [ 'Springfield', 'Chatham' ],
					'hours'       => [
						'monday'  => [ 'open' => '09:00', 'close' => '17:00' ],
						'tuesday' => [ 'open' => '09:00', 'close' => '17:00' ],
					],
				],
			],
		] );
	}

	// ── Constructor ──────────────────────────────────────────────────────

	public function test_constructor_registers_wp_head_when_enabled(): void {
		global $si_test_state;
		$hooks = array_column( $si_test_state['actions']['wp_head'] ?? [], 'callback' );
		$found = false;
		foreach ( $hooks as $cb ) {
			if ( is_array( $cb ) && $cb[1] === 'render_schema' ) $found = true;
		}
		$this->assertTrue( $found );
	}

	public function test_constructor_disabled_does_not_hook(): void {
		global $si_test_state;
		$si_test_state['actions'] = [];
		new Local_Business( [ 'enable_local_business' => false ] );
		$hooks = $si_test_state['actions']['wp_head'] ?? [];
		$this->assertEmpty( $hooks );
	}

	// ── Business types ───────────────────────────────────────────────────

	public function test_business_types_constant(): void {
		$this->assertContains( 'LocalBusiness', Local_Business::BUSINESS_TYPES );
		$this->assertContains( 'Restaurant', Local_Business::BUSINESS_TYPES );
		$this->assertContains( 'Store', Local_Business::BUSINESS_TYPES );
	}

	// ── get_locations ────────────────────────────────────────────────────

	public function test_get_locations_returns_configured_array(): void {
		$locations = $this->lb->get_locations();
		$this->assertCount( 1, $locations );
		$this->assertEquals( 'Test Restaurant', $locations[0]['name'] );
	}

	public function test_get_location_returns_single(): void {
		$loc = $this->lb->get_location( 0 );
		$this->assertNotNull( $loc );
		$this->assertEquals( 'Test Restaurant', $loc['name'] );
	}

	public function test_get_location_returns_null_for_invalid_index(): void {
		$this->assertNull( $this->lb->get_location( 99 ) );
	}

	// ── build_location_schema ────────────────────────────────────────────

	public function test_build_location_schema_full(): void {
		$loc    = $this->lb->get_location( 0 );
		$schema = $this->lb->build_location_schema( $loc );

		$this->assertNotNull( $schema );
		$this->assertEquals( 'https://schema.org', $schema['@context'] );
		$this->assertEquals( 'Restaurant', $schema['@type'] );
		$this->assertEquals( 'Test Restaurant', $schema['name'] );
		$this->assertEquals( '+1-555-0100', $schema['telephone'] );
		$this->assertEquals( 'hello@example.com', $schema['email'] );
		$this->assertEquals( '$$', $schema['priceRange'] );
		$this->assertArrayHasKey( 'address', $schema );
		$this->assertArrayHasKey( 'geo', $schema );
		$this->assertArrayHasKey( 'openingHoursSpecification', $schema );
		$this->assertArrayHasKey( 'areaServed', $schema );
	}

	public function test_build_location_schema_returns_null_empty_name(): void {
		$schema = $this->lb->build_location_schema( [ 'name' => '' ] );
		$this->assertNull( $schema );
	}

	public function test_build_location_schema_defaults_invalid_type(): void {
		$schema = $this->lb->build_location_schema( [
			'name' => 'Biz',
			'type' => 'InvalidType',
		] );
		$this->assertEquals( 'LocalBusiness', $schema['@type'] );
	}

	// ── build_address ────────────────────────────────────────────────────

	public function test_build_address_full(): void {
		$address = $this->lb->build_address( [
			'street'  => '123 Main St',
			'city'    => 'Springfield',
			'state'   => 'IL',
			'zip'     => '62704',
			'country' => 'US',
		] );

		$this->assertNotNull( $address );
		$this->assertEquals( 'PostalAddress', $address['@type'] );
		$this->assertEquals( '123 Main St', $address['streetAddress'] );
		$this->assertEquals( 'Springfield', $address['addressLocality'] );
	}

	public function test_build_address_returns_null_no_data(): void {
		$this->assertNull( $this->lb->build_address( [] ) );
	}

	// ── build_geo ────────────────────────────────────────────────────────

	public function test_build_geo_full(): void {
		$geo = $this->lb->build_geo( [
			'latitude'  => '39.7817',
			'longitude' => '-89.6501',
		] );

		$this->assertNotNull( $geo );
		$this->assertEquals( 'GeoCoordinates', $geo['@type'] );
		$this->assertEquals( 39.7817, $geo['latitude'] );
		$this->assertEquals( -89.6501, $geo['longitude'] );
	}

	public function test_build_geo_returns_null_missing_coords(): void {
		$this->assertNull( $this->lb->build_geo( [] ) );
		$this->assertNull( $this->lb->build_geo( [ 'latitude' => '39.78' ] ) );
	}

	// ── build_opening_hours ──────────────────────────────────────────────

	public function test_build_opening_hours(): void {
		$hours = $this->lb->build_opening_hours( [
			'hours' => [
				'monday'  => [ 'open' => '09:00', 'close' => '17:00' ],
				'friday'  => [ 'open' => '10:00', 'close' => '22:00' ],
			],
		] );

		$this->assertCount( 2, $hours );
		$this->assertEquals( 'OpeningHoursSpecification', $hours[0]['@type'] );
		$this->assertStringContainsString( 'Monday', $hours[0]['dayOfWeek'] );
		$this->assertEquals( '09:00', $hours[0]['opens'] );
	}

	public function test_build_opening_hours_empty(): void {
		$hours = $this->lb->build_opening_hours( [] );
		$this->assertEmpty( $hours );
	}

	public function test_build_opening_hours_skips_incomplete_day(): void {
		$hours = $this->lb->build_opening_hours( [
			'hours' => [
				'monday' => [ 'open' => '09:00' ], // missing 'close'
			],
		] );
		$this->assertEmpty( $hours );
	}
}
