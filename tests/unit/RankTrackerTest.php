<?php
namespace JAT_Search_Intel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use JAT_Search_Intel\Core;
use JAT_Search_Intel\Google_Auth;
use JAT_Search_Intel\Rank_Tracker;

/**
 * Rank tracking from Search Console and service-account auth.
 */
class RankTrackerTest extends TestCase {

	protected function setUp(): void {
		global $si_test_state;
		$si_test_state['options']    = [];
		$si_test_state['actions']    = [];
		$si_test_state['filters']    = [];
		$si_test_state['transients'] = [];
	}

	public function test_regex_chunks_are_anchored_escaped_and_split(): void {
		$chunks = Rank_Tracker::build_regex_chunks( [ 'seo plugin', 'c++ tips', 'what is a.i.' ] );
		$this->assertSame( [ '^(?:seo plugin|c\+\+ tips|what is a\.i\.)$' ], $chunks );

		$many   = array_map( static function ( $i ) { return str_repeat( 'k', 90 ) . $i; }, range( 1, 100 ) );
		$chunks = Rank_Tracker::build_regex_chunks( $many );
		$this->assertGreaterThan( 1, count( $chunks ) );
		foreach ( $chunks as $chunk ) {
			$this->assertLessThan( 3600, strlen( $chunk ) );
		}
	}

	public function test_aggregate_weights_position_by_impressions_and_picks_best_page(): void {
		$rows = [
			[ 'keys' => [ '2026-10-01', 'SEO Plugin', 'https://x.test/a/' ], 'clicks' => 2, 'impressions' => 100, 'position' => 4.0 ],
			[ 'keys' => [ '2026-10-01', 'seo plugin', 'https://x.test/b/' ], 'clicks' => 9, 'impressions' => 300, 'position' => 8.0 ],
			[ 'keys' => [ '2026-10-02', 'seo plugin', 'https://x.test/a/' ], 'clicks' => 1, 'impressions' => 10, 'position' => 3.0 ],
		];

		$out = Rank_Tracker::aggregate( $rows );

		$this->assertCount( 2, $out );
		$day1 = $out['2026-10-01|seo plugin'];
		$this->assertSame( 7.0, $day1['position'] ); // (4*100 + 8*300) / 400.
		$this->assertSame( 11, $day1['clicks'] );
		$this->assertSame( 400, $day1['impressions'] );
		$this->assertSame( 'https://x.test/b/', $day1['url'] );
	}

	public function test_change_reports_improvement_as_positive(): void {
		$series = [
			[ 'day' => '2026-09-01', 'position' => '12.0' ],
			[ 'day' => '2026-09-24', 'position' => '9.0' ],
			[ 'day' => '2026-10-01', 'position' => '6.5' ],
		];
		$this->assertSame( 2.5, Rank_Tracker::change( $series, 7 ) );
		$this->assertSame( 5.5, Rank_Tracker::change( $series, 28 ) );
		$this->assertNull( Rank_Tracker::change( [ [ 'day' => '2026-10-01', 'position' => '3' ] ], 7 ) );
	}

	public function test_sparkline_renders_svg_only_with_two_points(): void {
		$this->assertSame( '', Rank_Tracker::sparkline( [ 5.0 ] ) );
		$this->assertStringStartsWith( '<svg', Rank_Tracker::sparkline( [ 5.0, 4.0, 3.0 ] ) );
	}

	public function test_configured_keywords_win_over_auto_mode(): void {
		$tracker = new Rank_Tracker( array_merge( Core::get_default_settings(), [ 'tracked_keywords' => [ 'a', 'b' ] ] ) );
		$this->assertSame( [ 'a', 'b' ], $tracker->get_keywords() );
	}

	public function test_cron_only_when_enabled(): void {
		global $si_test_state;
		new Rank_Tracker( Core::get_default_settings() );
		$this->assertArrayNotHasKey( Rank_Tracker::CRON_HOOK, $si_test_state['actions'] );

		new Rank_Tracker( array_merge( Core::get_default_settings(), [ 'enable_rank_tracking' => true ] ) );
		$this->assertArrayHasKey( Rank_Tracker::CRON_HOOK, $si_test_state['actions'] );
	}

	// ── Google service account ──────────────────────────────────────────

	public function test_save_key_rejects_non_service_account_json(): void {
		$this->assertInstanceOf( \WP_Error::class, Google_Auth::save_key( '{"type":"authorized_user"}' ) );
		$this->assertInstanceOf( \WP_Error::class, Google_Auth::save_key( 'nope' ) );
	}

	public function test_jwt_is_signed_and_verifiable(): void {
		if ( ! function_exists( 'openssl_pkey_new' ) ) {
			$this->markTestSkipped( 'OpenSSL not available.' );
		}
		$res = openssl_pkey_new( [ 'private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA ] );
		openssl_pkey_export( $res, $pem );
		$public = openssl_pkey_get_details( $res )['key'];

		$jwt = Google_Auth::build_jwt(
			[ 'client_email' => 'svc@proj.iam.gserviceaccount.com', 'private_key' => $pem, 'token_uri' => 'https://oauth2.googleapis.com/token' ],
			Google_Auth::SCOPE_SEARCH_CONSOLE,
			1700000000
		);

		$this->assertIsString( $jwt );
		[ $h, $c, $s ] = explode( '.', $jwt );
		$claims = json_decode( base64_decode( strtr( $c, '-_', '+/' ) ), true );
		$this->assertSame( 'svc@proj.iam.gserviceaccount.com', $claims['iss'] );
		$this->assertSame( 1700003600, $claims['exp'] );
		$sig = base64_decode( strtr( $s, '-_', '+/' ) . str_repeat( '=', ( 4 - strlen( $s ) % 4 ) % 4 ) );
		$this->assertSame( 1, openssl_verify( $h . '.' . $c, $sig, $public, OPENSSL_ALGO_SHA256 ) );
	}

	public function test_saved_key_round_trips_encrypted(): void {
		global $si_test_state;
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			$this->markTestSkipped( 'OpenSSL not available.' );
		}
		$json = '{"type":"service_account","client_email":"svc@proj.iam.gserviceaccount.com","private_key":"-----BEGIN PRIVATE KEY-----\nabc\n-----END PRIVATE KEY-----\n"}';

		$this->assertTrue( Google_Auth::save_key( $json ) );
		$this->assertStringNotContainsString( 'PRIVATE KEY', (string) $si_test_state['options'][ Google_Auth::KEY_OPTION ] );
		$this->assertSame( 'svc@proj.iam.gserviceaccount.com', Google_Auth::client_email() );
	}
}
