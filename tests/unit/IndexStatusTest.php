<?php
namespace JAT_Search_Intel\Tests\Unit;

use JAT_Search_Intel\Google_Auth;
use JAT_Search_Intel\Index_Status;

class IndexStatusTest extends ParityTestCase {

	private const API_BODY = [
		'inspectionResult' => [
			'inspectionResultLink' => 'https://search.google.com/search-console/inspect?resource_id=x',
			'indexStatusResult'    => [
				'verdict'         => 'PASS',
				'coverageState'   => 'Submitted and indexed',
				'indexingState'   => 'INDEXING_ALLOWED',
				'robotsTxtState'  => 'ALLOWED',
				'pageFetchState'  => 'SUCCESSFUL',
				'lastCrawlTime'   => '2026-11-01T10:00:00Z',
				'crawledAs'       => 'MOBILE',
				'googleCanonical' => 'https://example.com/a/',
				'userCanonical'   => 'https://example.com/a/',
			],
		],
	];

	protected function setUp(): void {
		parent::setUp();
		// A cached token lets inspect() run without a real service account.
		$GLOBALS['si_test_state']['transients'][ 'jat_search_intel_google_token_' . md5( Google_Auth::SCOPE_SEARCH_CONSOLE ) ] = 'token';
	}

	private function status( array $settings = [] ): Index_Status {
		return new Index_Status( $settings + [ 'gsc_site_url' => 'sc-domain:example.com', 'enabled_post_types' => [ 'post' ] ] );
	}

	private function respond( int $code, array $body ): void {
		$GLOBALS['si_test_state']['remote_post_response'] = [
			'response' => [ 'code' => $code ],
			'body'     => (string) json_encode( $body ),
		];
	}

	public function test_normalise_maps_and_sanitizes(): void {
		$out = Index_Status::normalise( self::API_BODY );
		$this->assertSame( 'PASS', $out['verdict'] );
		$this->assertSame( 'Submitted and indexed', $out['coverage'] );
		$this->assertSame( 'MOBILE', $out['crawled_as'] );
		$this->assertSame( 'https://example.com/a/', $out['google_canonical'] );
		$this->assertNotEmpty( $out['checked'] );

		$odd = Index_Status::normalise( [ 'inspectionResult' => [ 'indexStatusResult' => [ 'verdict' => [ 'nested' ], 'coverageState' => '<b>x</b>' ] ] ] );
		$this->assertSame( '', $odd['verdict'] );
		$this->assertSame( 'x', $odd['coverage'] );
	}

	/**
	 * @dataProvider verdicts
	 */
	public function test_describe( array $result, string $state ): void {
		$this->assertSame( $state, Index_Status::describe( $result )[0] );
	}

	public static function verdicts(): array {
		$base = [ 'coverage' => 'Crawled - currently not indexed' ];
		return [
			'none'    => [ [], 'unknown' ],
			'pass'    => [ $base + [ 'verdict' => 'PASS' ], 'indexed' ],
			'fail'    => [ $base + [ 'verdict' => 'FAIL' ], 'error' ],
			'neutral' => [ $base + [ 'verdict' => 'NEUTRAL' ], 'excluded' ],
			'partial' => [ $base + [ 'verdict' => 'PARTIAL' ], 'excluded' ],
			'other'   => [ $base + [ 'verdict' => 'VERDICT_UNSPECIFIED' ], 'unknown' ],
		];
	}

	public function test_inspect_needs_a_property(): void {
		$result = ( new Index_Status( [] ) )->inspect( 'https://example.com/a/' );
		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	public function test_check_post_stores_result(): void {
		$this->respond( 200, self::API_BODY );
		$result = $this->status()->check_post( 3 );
		$this->assertIsArray( $result );
		$this->assertSame( 'PASS', Index_Status::get( 3 )['verdict'] );
	}

	public function test_api_error_is_returned_and_not_stored(): void {
		$this->respond( 403, [ 'error' => [ 'message' => 'User does not have sufficient permission' ] ] );
		$result = $this->status()->check_post( 3 );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'User does not have sufficient permission', $result->get_error_message() );
		$this->assertSame( [], Index_Status::get( 3 ) );
	}

	public function test_run_stops_on_permission_error(): void {
		foreach ( [ 1, 2, 3, 4 ] as $id ) {
			$GLOBALS['si_test_state']['posts'][ $id ] = [ 'ID' => $id, 'post_type' => 'post', 'post_status' => 'publish' ];
		}
		$this->respond( 403, [ 'error' => [ 'message' => 'Forbidden' ] ] );
		$this->assertSame( [ 'checked' => 0, 'errors' => 1, 'message' => 'Forbidden' ], $this->status()->run( 10 ) );
	}

	public function test_run_stops_after_three_other_errors(): void {
		foreach ( [ 1, 2, 3, 4, 5 ] as $id ) {
			$GLOBALS['si_test_state']['posts'][ $id ] = [ 'ID' => $id, 'post_type' => 'post', 'post_status' => 'publish' ];
		}
		$this->respond( 500, [] );
		$this->assertSame( 3, $this->status()->run( 10 )['errors'] );
	}

	public function test_run_checks_batch(): void {
		foreach ( [ 1, 2 ] as $id ) {
			$GLOBALS['si_test_state']['posts'][ $id ] = [ 'ID' => $id, 'post_type' => 'post', 'post_status' => 'publish' ];
		}
		$this->respond( 200, self::API_BODY );
		$this->assertSame( 2, $this->status()->run( 10 )['checked'] );
	}

	public function test_next_batch_is_empty_without_post_types_or_limit(): void {
		$this->assertSame( [], $this->status( [ 'enabled_post_types' => [ 'nope' ] ] )->next_batch( 5 ) );
		$this->assertSame( [], $this->status()->next_batch( 0 ) );
	}

	public function test_is_connected_needs_service_account_and_property(): void {
		$this->assertFalse( Index_Status::is_connected( [ 'gsc_site_url' => 'sc-domain:example.com' ] ) );
	}
}
