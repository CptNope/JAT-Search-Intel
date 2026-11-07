<?php
namespace JAT_Search_Intel\Tests\Unit;

use JAT_Search_Intel\Schema_Map;

class SchemaMapTest extends ParityTestCase {

	public function test_robots_line_added_once_for_public_sites(): void {
		$line = 'Sitemap: https://example.com/wp-json/jat-search-intel/v1/schema-map';
		$out  = Schema_Map::robots_line( "User-agent: *\nDisallow:\n", true );
		$this->assertStringContainsString( $line, $out );
		$this->assertSame( $out, Schema_Map::robots_line( $out, true ) );
		$this->assertSame( 'User-agent: *', Schema_Map::robots_line( 'User-agent: *', false ) );
	}

	public function test_bump_version_changes_option(): void {
		Schema_Map::bump_version();
		$first = get_option( 'jat_search_intel_schema_map_version' );
		Schema_Map::bump_version();
		$this->assertNotSame( $first, get_option( 'jat_search_intel_schema_map_version' ) );
	}

	public function test_unknown_type_has_no_feed(): void {
		$map = new Schema_Map( [ 'sitemap_post_types' => [ 'post' ] ] );
		$this->assertNull( $map->build_feed( 'secret_type', 1 ) );
	}

	public function test_empty_page_has_no_feed(): void {
		$map = new Schema_Map( [ 'sitemap_post_types' => [ 'post' ] ] );
		$this->assertNull( $map->build_feed( 'post', 1 ) );
	}

	public function test_rest_feed_404s_for_unknown_type(): void {
		$map     = new Schema_Map( [ 'sitemap_post_types' => [ 'post' ] ] );
		$request = new \WP_REST_Request( 'GET', '/jat-search-intel/v1/schema/secret' );
		$request->set_param( 'type', 'secret' );
		$request->set_param( 'page', 1 );
		$this->assertInstanceOf( \WP_Error::class, $map->rest_feed( $request ) );
	}

	public function test_build_map_lists_one_feed_per_page(): void {
		for ( $i = 1; $i <= 3; $i++ ) {
			$GLOBALS['si_test_state']['posts'][ $i ] = [ 'ID' => $i, 'post_type' => 'post', 'post_status' => 'publish' ];
		}
		$xml = ( new Schema_Map( [ 'sitemap_post_types' => [ 'post', 'page' ] ] ) )->build_map();
		$this->assertStringStartsWith( '<?xml', $xml );
		$this->assertStringContainsString( 'contentType="structuredData/schema.org"', $xml );
		$this->assertStringContainsString( '/schema/post/1', $xml );
		$this->assertStringNotContainsString( '/schema/page/', $xml, 'Types without posts are left out.' );
		$this->assertNotFalse( simplexml_load_string( $xml ) );
	}

	public function test_serve_xml_only_touches_the_map_route(): void {
		$map      = new Schema_Map( [] );
		$response = new \WP_REST_Response( '<urlset/>' );

		$other = new \WP_REST_Request( 'GET', '/jat-search-intel/v1/schema/post' );
		$this->assertFalse( $map->serve_xml( false, $response, $other, null ) );

		ob_start();
		$served = $map->serve_xml( false, $response, new \WP_REST_Request( 'GET', '/jat-search-intel/v1/schema-map' ), null );
		$this->assertSame( '<urlset/>', ob_get_clean() );
		$this->assertTrue( $served );
	}

	public function test_rest_map_sets_xml_headers(): void {
		$response = ( new Schema_Map( [] ) )->rest_map();
		$this->assertSame( 'application/xml; charset=UTF-8', $response->get_headers()['Content-Type'] );
		$this->assertSame( 'noindex', $response->get_headers()['X-Robots-Tag'] );
	}
}
