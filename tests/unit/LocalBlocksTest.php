<?php
namespace JAT_Search_Intel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use JAT_Search_Intel\Local_Blocks;

class LocalBlocksTest extends TestCase {

	protected function setUp(): void {
		global $si_test_state;
		$si_test_state['denied_caps'] = [];
		$si_test_state['options']['jat_search_intel_settings'] = [
			'local_business_locations' => [
				[
					'name'          => 'Jeremy Anderson Tech',
					'street'        => '1 Main St',
					'city'          => 'Shrewsbury',
					'state'         => 'MA',
					'zip'           => '01545',
					'phone'         => '(508) 555-0100',
					'email'         => 'info@example.com',
					'service_areas' => [ 'Worcester', 'Boston' ],
					'latitude'      => '42.29',
					'longitude'     => '-71.71',
					'hours'         => [ 'monday' => [ 'open' => '09:00', 'close' => '17:00' ] ],
				],
			],
		];
	}

	public function test_info(): void {
		$html = Local_Blocks::render( 'info' );
		$this->assertStringContainsString( 'Jeremy Anderson Tech', $html );
		$this->assertStringContainsString( 'Shrewsbury, MA 01545', $html );
		$this->assertStringContainsString( 'tel:5085550100', $html );
		$this->assertStringContainsString( 'mailto:info@example.com', $html );
		$this->assertStringContainsString( 'Worcester, Boston', $html );
	}

	public function test_info_without_name(): void {
		$this->assertStringNotContainsString( '<strong>Jeremy Anderson Tech</strong>', Local_Blocks::render( 'info', 1, false ) );
	}

	public function test_hours_lists_every_day(): void {
		$html = Local_Blocks::render( 'hours' );
		$this->assertStringContainsString( 'Monday', $html );
		$this->assertStringContainsString( 'Sunday', $html );
		$this->assertSame( 6, substr_count( $html, 'Closed' ) );
	}

	public function test_map_has_embed_and_directions(): void {
		$html = Local_Blocks::render( 'map' );
		$this->assertStringContainsString( 'openstreetmap.org/export/embed.html', $html );
		$this->assertStringContainsString( 'loading="lazy"', $html );
		$this->assertStringContainsString( 'google.com/maps/dir', $html );
	}

	public function test_missing_location(): void {
		global $si_test_state;
		$si_test_state['denied_caps'] = [ 'manage_options' ];
		$this->assertSame( '', Local_Blocks::render( 'info', 5 ) );
		$si_test_state['denied_caps'] = [];
		$this->assertStringContainsString( 'Add a location', Local_Blocks::render( 'info', 5 ) );
	}

	public function test_shortcode(): void {
		$this->assertStringContainsString( 'jsi-local--hours', Local_Blocks::shortcode( [ 'display' => 'hours' ] ) );
	}
}
