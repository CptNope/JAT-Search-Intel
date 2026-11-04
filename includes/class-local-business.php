<?php
namespace JAT_Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Local Business Schema — address, phone, hours, geo, multi-location.
 *
 * @since 2.0.0
 */
class Local_Business {

	/**
	 * Valid LocalBusiness subtypes.
	 *
	 * @var string[]
	 */
	public const BUSINESS_TYPES = [
		'LocalBusiness',
		'Restaurant',
		'Store',
		'MedicalBusiness',
		'LegalService',
		'FinancialService',
		'AutomotiveBusiness',
		'HealthAndBeautyBusiness',
		'HomeAndConstructionBusiness',
		'ProfessionalService',
		'SportsActivityLocation',
		'EntertainmentBusiness',
		'FoodEstablishment',
		'LodgingBusiness',
	];

	/** @var array */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param array $settings Plugin settings.
	 */
	public function __construct( array $settings ) {
		$this->settings = $settings;

		if ( ! empty( $settings['enable_local_business'] ) ) {
			add_action( 'wp_head', [ $this, 'render_schema' ], 12 );
			add_filter( 'jat_search_intel_schema_graph', [ $this, 'add_graph_nodes' ], 10, 2 );
		}
	}

	/**
	 * Render Local Business JSON-LD schema in wp_head.
	 */
	public function render_schema(): void {
		if ( $this->in_graph() ) {
			return; // Added to the shared graph via add_graph_nodes().
		}

		$locations = $this->get_locations();

		if ( empty( $locations ) ) {
			return;
		}

		foreach ( $locations as $location ) {
			$schema = $this->build_location_schema( $location );
			if ( $schema ) {
				echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
			}
		}
	}

	/**
	 * Add LocalBusiness nodes to the shared graph, linked to the publisher.
	 *
	 * @param array $nodes   Graph nodes.
	 * @param array $context Graph context.
	 * @return array
	 */
	public function add_graph_nodes( array $nodes, array $context = [] ): array {
		foreach ( $this->get_locations() as $i => $location ) {
			$node = $this->build_location_schema( $location );
			if ( ! $node ) {
				continue;
			}
			unset( $node['@context'] );
			if ( empty( $node['@id'] ) ) {
				$node['@id'] = home_url( '/#localbusiness-' . ( (int) $i + 1 ) );
			}
			if ( ! empty( $context['publisher_id'] ) ) {
				$node['parentOrganization'] = [ '@id' => $context['publisher_id'] ];
			}
			$nodes[] = $node;
		}
		return $nodes;
	}

	/**
	 * Build JSON-LD schema for a single location.
	 *
	 * @param array $location Location data.
	 * @return array|null
	 */
	public function build_location_schema( array $location ): ?array {
		$type = in_array( $location['type'] ?? '', self::BUSINESS_TYPES, true )
			? $location['type']
			: 'LocalBusiness';

		$schema = [
			'@context' => 'https://schema.org',
			'@type'    => $type,
			'name'     => $location['name'] ?? '',
		];

		if ( empty( $schema['name'] ) ) {
			return null;
		}

		// @id
		if ( ! empty( $location['url'] ) ) {
			$schema['@id']  = $location['url'] . '#localbusiness';
			$schema['url']  = $location['url'];
		}

		// Description
		if ( ! empty( $location['description'] ) ) {
			$schema['description'] = $location['description'];
		}

		// Image
		if ( ! empty( $location['image'] ) ) {
			$schema['image'] = $location['image'];
		}

		// Telephone
		if ( ! empty( $location['phone'] ) ) {
			$schema['telephone'] = $location['phone'];
		}

		// Email
		if ( ! empty( $location['email'] ) ) {
			$schema['email'] = $location['email'];
		}

		// Address
		$address = $this->build_address( $location );
		if ( $address ) {
			$schema['address'] = $address;
		}

		// Geo coordinates
		$geo = $this->build_geo( $location );
		if ( $geo ) {
			$schema['geo'] = $geo;
		}

		// Opening hours
		$hours = $this->build_opening_hours( $location );
		if ( ! empty( $hours ) ) {
			$schema['openingHoursSpecification'] = $hours;
		}

		// Service area
		if ( ! empty( $location['service_areas'] ) ) {
			$schema['areaServed'] = array_map( function ( $area ) {
				return [
					'@type' => 'City',
					'name'  => $area,
				];
			}, (array) $location['service_areas'] );
		}

		// Price range
		if ( ! empty( $location['price_range'] ) ) {
			$schema['priceRange'] = $location['price_range'];
		}

		return $schema;
	}

	/**
	 * Build PostalAddress schema.
	 *
	 * @param array $location Location data.
	 * @return array|null
	 */
	public function build_address( array $location ): ?array {
		$street  = $location['street'] ?? '';
		$city    = $location['city'] ?? '';
		$state   = $location['state'] ?? '';
		$zip     = $location['zip'] ?? '';
		$country = $location['country'] ?? '';

		if ( ! $street && ! $city ) {
			return null;
		}

		$address = [
			'@type' => 'PostalAddress',
		];

		if ( $street ) {
			$address['streetAddress'] = $street;
		}
		if ( $city ) {
			$address['addressLocality'] = $city;
		}
		if ( $state ) {
			$address['addressRegion'] = $state;
		}
		if ( $zip ) {
			$address['postalCode'] = $zip;
		}
		if ( $country ) {
			$address['addressCountry'] = $country;
		}

		return $address;
	}

	/**
	 * Build GeoCoordinates schema.
	 *
	 * @param array $location Location data.
	 * @return array|null
	 */
	public function build_geo( array $location ): ?array {
		$lat = $location['latitude'] ?? '';
		$lng = $location['longitude'] ?? '';

		if ( '' === $lat || '' === $lng ) {
			return null;
		}

		return [
			'@type'     => 'GeoCoordinates',
			'latitude'  => (float) $lat,
			'longitude' => (float) $lng,
		];
	}

	/**
	 * Build OpeningHoursSpecification from location hours config.
	 *
	 * @param array $location Location data.
	 * @return array
	 */
	public function build_opening_hours( array $location ): array {
		$hours = $location['hours'] ?? [];
		if ( empty( $hours ) ) {
			return [];
		}

		$day_map = [
			'monday'    => 'Monday',
			'tuesday'   => 'Tuesday',
			'wednesday' => 'Wednesday',
			'thursday'  => 'Thursday',
			'friday'    => 'Friday',
			'saturday'  => 'Saturday',
			'sunday'    => 'Sunday',
		];

		$specs = [];

		foreach ( $hours as $day => $times ) {
			$schema_day = $day_map[ strtolower( $day ) ] ?? null;
			if ( ! $schema_day || empty( $times['open'] ) || empty( $times['close'] ) ) {
				continue;
			}

			$specs[] = [
				'@type'     => 'OpeningHoursSpecification',
				'dayOfWeek' => 'https://schema.org/' . $schema_day,
				'opens'     => $times['open'],
				'closes'    => $times['close'],
			];
		}

		return $specs;
	}

	/**
	 * Get all configured locations.
	 *
	 * @return array
	 */
	public function get_locations(): array {
		return $this->settings['local_business_locations'] ?? [];
	}

	/**
	 * Get a single location by index.
	 *
	 * @param int $index Location index.
	 * @return array|null
	 */
	public function get_location( int $index ): ?array {
		$locations = $this->get_locations();
		return $locations[ $index ] ?? null;
	}

	/**
	 * Whether JSON-LD goes into the shared @graph printed by Schema_Manager.
	 *
	 * @return bool
	 */
	private function in_graph(): bool {
		return ! empty( $this->settings['enable_schema'] );
	}
}
