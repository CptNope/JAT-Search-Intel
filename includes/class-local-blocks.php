<?php
/**
 * Local SEO on the page: business details, opening hours and a map,
 * from the locations saved under Local Business settings.
 *
 * Available as the "Local Business" block and the
 * [jat_search_intel_local display="info|hours|map" location="1"] shortcode.
 * The schema for each location is printed by Local_Business; these only
 * show the same facts to visitors, so the page and its markup agree.
 *
 * @package JAT_Search_Intel
 * @since 3.8.0
 */

namespace JAT_Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Local business display.
 */
class Local_Blocks {

	private const DAYS = [ 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' ];

	/**
	 * Saved locations.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function locations(): array {
		$settings  = get_option( 'jat_search_intel_settings', [] );
		$locations = is_array( $settings ) ? ( $settings['local_business_locations'] ?? [] ) : [];
		return is_array( $locations ) ? array_values( $locations ) : [];
	}

	/**
	 * Block render callback.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public static function render_block( $attributes ): string {
		$attributes = is_array( $attributes ) ? $attributes : [];
		return self::render(
			(string) ( $attributes['display'] ?? 'info' ),
			(int) ( $attributes['location'] ?? 1 ),
			! empty( $attributes['showName'] ) || ! isset( $attributes['showName'] )
		);
	}

	/**
	 * Shortcode handler.
	 *
	 * @param array|string $atts Attributes.
	 * @return string
	 */
	public static function shortcode( $atts ): string {
		$atts = shortcode_atts(
			[
				'display'  => 'info',
				'location' => 1,
				'name'     => 'yes',
			],
			is_array( $atts ) ? $atts : [],
			'jat_search_intel_local'
		);
		return self::render( (string) $atts['display'], (int) $atts['location'], 'no' !== $atts['name'] );
	}

	/**
	 * Render one view of one location.
	 *
	 * @param string $display   info|hours|map.
	 * @param int    $number    Location number, starting at 1.
	 * @param bool   $show_name Show the business name.
	 * @return string
	 */
	public static function render( string $display, int $number = 1, bool $show_name = true ): string {
		$locations = self::locations();
		$location  = $locations[ max( 1, $number ) - 1 ] ?? null;

		if ( ! is_array( $location ) ) {
			return current_user_can( 'manage_options' )
				? '<p class="jsi-local jsi-local--empty">' . esc_html__( 'Add a location under JAT Search Intel > Settings > Local Business to show it here.', 'jat-search-intel' ) . '</p>'
				: '';
		}

		switch ( $display ) {
			case 'hours':
				$html = self::hours( $location );
				break;
			case 'map':
				$html = self::map( $location );
				break;
			default:
				$html = self::info( $location, $show_name );
		}

		return '' === $html ? '' : '<div class="jsi-local jsi-local--' . esc_attr( $display ) . '">' . $html . '</div>';
	}

	/**
	 * Name, address, phone, email and service areas.
	 *
	 * @param array $location Location.
	 * @param bool  $show_name Show the name.
	 * @return string
	 */
	private static function info( array $location, bool $show_name ): string {
		$html = '';
		if ( $show_name && ! empty( $location['name'] ) ) {
			$html .= '<p class="jsi-local__name"><strong>' . esc_html( (string) $location['name'] ) . '</strong></p>';
		}

		$address = self::address_lines( $location );
		if ( $address ) {
			$html .= '<address class="jsi-local__address">' . implode( '<br>', array_map( 'esc_html', $address ) ) . '</address>';
		}

		$contact = [];
		if ( ! empty( $location['phone'] ) ) {
			$tel       = preg_replace( '/[^0-9+]/', '', (string) $location['phone'] );
			$contact[] = '<a href="' . esc_url( 'tel:' . $tel ) . '">' . esc_html( (string) $location['phone'] ) . '</a>';
		}
		if ( ! empty( $location['email'] ) && is_email( (string) $location['email'] ) ) {
			$email     = antispambot( (string) $location['email'] );
			$contact[] = '<a href="' . esc_url( 'mailto:' . $email ) . '">' . esc_html( $email ) . '</a>';
		}
		if ( $contact ) {
			$html .= '<p class="jsi-local__contact">' . implode( '<br>', $contact ) . '</p>';
		}

		if ( ! empty( $location['service_areas'] ) ) {
			$areas = array_filter( array_map( 'trim', (array) $location['service_areas'] ) );
			if ( $areas ) {
				$html .= '<p class="jsi-local__areas">' . esc_html__( 'Serving:', 'jat-search-intel' ) . ' ' . esc_html( implode( ', ', $areas ) ) . '</p>';
			}
		}

		return $html;
	}

	/**
	 * Opening hours table.
	 *
	 * @param array $location Location.
	 * @return string
	 */
	private static function hours( array $location ): string {
		$hours = is_array( $location['hours'] ?? null ) ? $location['hours'] : [];
		if ( ! $hours ) {
			return '';
		}

		$today = strtolower( wp_date( 'l' ) );
		$rows  = '';
		foreach ( self::DAYS as $day ) {
			$times = $hours[ $day ] ?? [];
			$open  = is_array( $times ) ? (string) ( $times['open'] ?? '' ) : '';
			$close = is_array( $times ) ? (string) ( $times['close'] ?? '' ) : '';
			$value = ( '' !== $open && '' !== $close )
				? self::time( $open ) . ' – ' . self::time( $close )
				: __( 'Closed', 'jat-search-intel' );
			$rows .= sprintf(
				'<tr%1$s><th scope="row">%2$s</th><td>%3$s</td></tr>',
				$day === $today ? ' class="jsi-local__today" aria-current="date"' : '',
				esc_html( self::day_label( $day ) ),
				esc_html( $value )
			);
		}

		return '<table class="jsi-local__hours"><caption>' . esc_html__( 'Opening hours', 'jat-search-intel' ) . '</caption><tbody>' . $rows . '</tbody></table>';
	}

	/**
	 * Map embed (OpenStreetMap, no key needed) and a directions link.
	 *
	 * @param array $location Location.
	 * @return string
	 */
	private static function map( array $location ): string {
		$lat = isset( $location['latitude'] ) ? (float) $location['latitude'] : 0.0;
		$lng = isset( $location['longitude'] ) ? (float) $location['longitude'] : 0.0;

		$query      = implode( ', ', self::address_lines( $location ) );
		$directions = 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode( $lat && $lng ? $lat . ',' . $lng : $query );

		$html = '';
		if ( $lat && $lng ) {
			$d    = 0.01;
			$bbox = implode( ',', [ $lng - $d, $lat - $d, $lng + $d, $lat + $d ] );
			$src  = 'https://www.openstreetmap.org/export/embed.html?bbox=' . rawurlencode( $bbox ) . '&layer=mapnik&marker=' . rawurlencode( $lat . ',' . $lng );
			$html .= sprintf(
				'<iframe class="jsi-local__map" src="%1$s" title="%2$s" loading="lazy" referrerpolicy="no-referrer" style="border:0;width:100%%;aspect-ratio:4/3"></iframe>',
				esc_url( $src ),
				/* translators: %s: business name */
				esc_attr( sprintf( __( 'Map showing %s', 'jat-search-intel' ), (string) ( $location['name'] ?? '' ) ) )
			);
		} elseif ( '' === $query ) {
			return '';
		}

		$html .= '<p class="jsi-local__directions"><a href="' . esc_url( $directions ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Get directions', 'jat-search-intel' ) . '</a></p>';
		return $html;
	}

	/**
	 * Address as display lines.
	 *
	 * @param array $location Location.
	 * @return string[]
	 */
	private static function address_lines( array $location ): array {
		$street = trim( (string) ( $location['street'] ?? '' ) );
		$city   = trim( (string) ( $location['city'] ?? '' ) );
		$region = trim( trim( (string) ( $location['state'] ?? '' ) ) . ' ' . trim( (string) ( $location['zip'] ?? '' ) ) );
		$lines  = [];
		if ( '' !== $street ) {
			$lines[] = $street;
		}
		$line2 = trim( $city . ( '' !== $city && '' !== $region ? ', ' : '' ) . $region );
		if ( '' !== $line2 ) {
			$lines[] = $line2;
		}
		if ( ! empty( $location['country'] ) ) {
			$lines[] = (string) $location['country'];
		}
		return $lines;
	}

	/**
	 * Format HH:MM using the site's time format.
	 *
	 * @param string $value Time.
	 * @return string
	 */
	private static function time( string $value ): string {
		$ts = strtotime( '1970-01-01 ' . $value . ' UTC' );
		return false === $ts ? $value : gmdate( (string) get_option( 'time_format', 'g:i a' ), $ts );
	}

	/**
	 * Translated day name.
	 *
	 * @param string $day Lower-case English day.
	 * @return string
	 */
	private static function day_label( string $day ): string {
		$labels = [
			'monday'    => __( 'Monday', 'jat-search-intel' ),
			'tuesday'   => __( 'Tuesday', 'jat-search-intel' ),
			'wednesday' => __( 'Wednesday', 'jat-search-intel' ),
			'thursday'  => __( 'Thursday', 'jat-search-intel' ),
			'friday'    => __( 'Friday', 'jat-search-intel' ),
			'saturday'  => __( 'Saturday', 'jat-search-intel' ),
			'sunday'    => __( 'Sunday', 'jat-search-intel' ),
		];
		return $labels[ $day ] ?? ucfirst( $day );
	}
}
