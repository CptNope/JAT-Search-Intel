<?php
namespace JAT_Search_Intel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use JAT_Search_Intel\Link_Checker;

class LinkCheckerTest extends TestCase {

	protected function tearDown(): void {
		global $si_test_state;
		unset( $si_test_state['filters']['jat_search_intel_link_checker_links'], $si_test_state['filters']['jat_search_intel_link_checker_post_types'] );
	}


	private Link_Checker $checker;

	protected function setUp(): void {
		global $si_test_state;
		$si_test_state['options']          = [];
		$si_test_state['posts']            = [];
		$si_test_state['scheduled_events'] = [];
		$si_test_state['remote_head_response'] = null;
		unset( $si_test_state['filters']['jat_search_intel_link_checker_links'], $si_test_state['filters']['jat_search_intel_link_checker_post_types'] );

		$this->checker = new Link_Checker( [
			'enable_link_checker' => true,
			'enabled_post_types'  => [ 'post', 'page' ],
		] );
	}

	public function test_extract_links_from_content(): void {
		global $si_test_state;
		$si_test_state['posts'][1] = [
			'ID'           => 1,
			'post_title'   => 'Test Post',
			'post_content' => '<p>Visit <a href="https://example.com/page1">Page 1</a> and <a href="https://example.com/page2">Page 2</a>.</p>',
			'post_status'  => 'publish',
			'post_type'    => 'post',
		];

		$links = $this->checker->extract_links( 1 );

		$this->assertCount( 2, $links );
		$this->assertContains( 'https://example.com/page1', $links );
		$this->assertContains( 'https://example.com/page2', $links );
	}

	public function test_extract_links_skips_anchors_and_mailto(): void {
		global $si_test_state;
		$si_test_state['posts'][1] = [
			'ID'           => 1,
			'post_title'   => 'Test',
			'post_content' => '<a href="#top">Top</a> <a href="mailto:a@b.com">Email</a> <a href="tel:123">Phone</a> <a href="https://real.com">Real</a>',
			'post_status'  => 'publish',
			'post_type'    => 'post',
		];

		$links = $this->checker->extract_links( 1 );

		$this->assertCount( 1, $links );
		$this->assertEquals( 'https://real.com', $links[0] );
	}

	public function test_extract_links_resolves_relative_urls(): void {
		global $si_test_state;
		$si_test_state['posts'][1] = [
			'ID'           => 1,
			'post_title'   => 'Test',
			'post_content' => '<a href="/about">About</a>',
			'post_status'  => 'publish',
			'post_type'    => 'post',
		];

		$links = $this->checker->extract_links( 1 );

		$this->assertCount( 1, $links );
		$this->assertEquals( 'https://example.com/about', $links[0] );
	}

	public function test_extract_links_deduplicates(): void {
		global $si_test_state;
		$si_test_state['posts'][1] = [
			'ID'           => 1,
			'post_title'   => 'Test',
			'post_content' => '<a href="https://x.com">1</a> <a href="https://x.com">2</a>',
			'post_status'  => 'publish',
			'post_type'    => 'post',
		];

		$links = $this->checker->extract_links( 1 );
		$this->assertCount( 1, $links );
	}

	public function test_extract_links_filter_adds_links_kept_outside_the_content(): void {
		global $si_test_state;
		// A listing with no body text: its website and menu live in post meta.
		$si_test_state['posts'][7] = [
			'ID'           => 7,
			'post_title'   => 'A place',
			'post_content' => '',
			'post_status'  => 'publish',
			'post_type'    => 'listing',
		];
		add_filter( 'jat_search_intel_link_checker_links', function ( $urls, $post_id ) {
			return 7 === $post_id ? array_merge( $urls, [ 'https://place.example/', 'https://place.example/menu', 'tel:5085551234' ] ) : $urls;
		}, 10, 2 );

		$links = $this->checker->extract_links( 7 );

		$this->assertSame( [ 'https://place.example/', 'https://place.example/menu' ], $links );
	}

	public function test_post_types_filter_adds_custom_types(): void {
		add_filter( 'jat_search_intel_link_checker_post_types', function ( $types ) {
			return array_merge( $types, [ 'listing' ] );
		} );

		$this->assertContains( 'listing', $this->checker->post_types() );
		$this->assertContains( 'post', $this->checker->post_types() );
	}

	public function test_extract_links_empty_for_missing_post(): void {
		$this->assertEmpty( $this->checker->extract_links( 999 ) );
	}

	public function test_check_url_returns_ok_for_200(): void {
		global $si_test_state;
		$si_test_state['remote_head_response'] = [
			'response' => [ 'code' => 200, 'message' => 'OK' ],
		];

		$result = $this->checker->check_url( 'https://example.com' );

		$this->assertFalse( $result['broken'] );
		$this->assertEquals( 200, $result['status'] );
	}

	public function test_check_url_flags_404_as_broken(): void {
		global $si_test_state;
		$si_test_state['remote_head_response'] = [
			'response' => [ 'code' => 404, 'message' => 'Not Found' ],
		];

		$result = $this->checker->check_url( 'https://example.com/missing' );

		$this->assertTrue( $result['broken'] );
		$this->assertEquals( 404, $result['status'] );
	}

	public function test_check_url_flags_500_as_broken(): void {
		global $si_test_state;
		$si_test_state['remote_head_response'] = [
			'response' => [ 'code' => 500, 'message' => 'Server Error' ],
		];

		$result = $this->checker->check_url( 'https://example.com/error' );

		$this->assertTrue( $result['broken'] );
		$this->assertEquals( 500, $result['status'] );
	}

	public function test_check_url_reports_403_as_blocked_not_broken(): void {
		global $si_test_state;
		$si_test_state['remote_head_response'] = [ 'response' => [ 'code' => 403, 'message' => 'Forbidden' ] ];
		$si_test_state['remote_response']      = [ 'response' => [ 'code' => 403, 'message' => 'Forbidden' ] ];

		$result = $this->checker->check_url( 'https://example.com/bot-wall' );

		$this->assertFalse( $result['broken'] );
		$this->assertTrue( $result['blocked'] );
		$this->assertEquals( 403, $result['status'] );
	}

	public function test_check_url_retries_refused_head_with_get(): void {
		global $si_test_state;
		$si_test_state['remote_head_response'] = [ 'response' => [ 'code' => 405, 'message' => 'Method Not Allowed' ] ];
		$si_test_state['remote_response']      = [ 'response' => [ 'code' => 200, 'message' => 'OK' ] ];

		$result = $this->checker->check_url( 'https://example.com/no-head' );

		$this->assertFalse( $result['broken'] );
		$this->assertEquals( 200, $result['status'] );
	}

	public function test_check_url_still_flags_404_after_get_retry(): void {
		global $si_test_state;
		$si_test_state['remote_head_response'] = [ 'response' => [ 'code' => 403, 'message' => 'Forbidden' ] ];
		$si_test_state['remote_response']      = [ 'response' => [ 'code' => 404, 'message' => 'Not Found' ] ];

		$result = $this->checker->check_url( 'https://example.com/gone' );

		$this->assertTrue( $result['broken'] );
		$this->assertFalse( $result['blocked'] );
	}

	public function test_check_url_passes_301_redirect(): void {
		global $si_test_state;
		$si_test_state['remote_head_response'] = [
			'response' => [ 'code' => 301, 'message' => 'Moved' ],
		];

		$result = $this->checker->check_url( 'https://example.com/old' );

		$this->assertFalse( $result['broken'] );
		$this->assertEquals( 301, $result['status'] );
	}

	public function test_scan_post_returns_only_broken_links(): void {
		global $si_test_state;
		$si_test_state['posts'][1] = [
			'ID'           => 1,
			'post_title'   => 'Test',
			'post_content' => '<a href="https://ok.com">OK</a> <a href="https://broken.com">Broken</a>',
			'post_status'  => 'publish',
			'post_type'    => 'post',
		];
		// Default response is 200, so all pass. Override for broken.
		$si_test_state['remote_head_response'] = [
			'response' => [ 'code' => 200, 'message' => 'OK' ],
		];

		$broken = $this->checker->scan_post( 1 );
		$this->assertEmpty( $broken );
	}

	public function test_get_broken_links_default_empty(): void {
		$this->assertEmpty( $this->checker->get_broken_links() );
	}

	public function test_clear_results(): void {
		global $si_test_state;
		$si_test_state['options']['jat_search_intel_broken_links'] = [ [ 'url' => 'x', 'broken' => true ] ];

		$this->checker->clear_results();
		$this->assertEmpty( $this->checker->get_broken_links() );
	}

	public function test_get_last_scan_time_default_empty(): void {
		$this->assertEquals( '', $this->checker->get_last_scan_time() );
	}

	public function test_schedule_creates_event(): void {
		global $si_test_state;
		$this->checker->schedule();

		$this->assertArrayHasKey( 'jat_search_intel_check_links', $si_test_state['scheduled_events'] ?? [] );
	}

	public function test_unschedule_removes_event(): void {
		global $si_test_state;
		$si_test_state['scheduled_events']['jat_search_intel_check_links'] = time();

		$this->checker->unschedule();

		$this->assertArrayNotHasKey( 'jat_search_intel_check_links', $si_test_state['scheduled_events'] ?? [] );
	}

	public function test_run_batch_check_resets_offset_when_no_posts(): void {
		global $si_test_state;
		// No posts — should reset offset and set last scan time.
		$this->checker->run_batch_check();

		$this->assertEquals( 0, $si_test_state['options']['jat_search_intel_link_checker_offset'] ?? -1 );
		$this->assertNotEmpty( $si_test_state['options']['jat_search_intel_link_checker_last_scan'] ?? '' );
	}

	public function test_disabled_checker_does_not_register(): void {
		global $si_test_state;
		$si_test_state['actions'] = [];

		$checker = new Link_Checker( [ 'enable_link_checker' => false ] );
		// Should not register any cron hooks.
		$this->assertArrayNotHasKey( 'jat_search_intel_check_links', $si_test_state['actions'] ?? [] );
	}

	public function test_enabled_checker_schedules_its_hourly_scan_on_init(): void {
		global $si_test_state;
		$si_test_state['actions'] = [];
		new \JAT_Search_Intel\Link_Checker( [ 'enable_link_checker' => true ] );

		$init = array_map( static function ( $hook ) {
			return is_array( $hook['callback'] ) ? $hook['callback'][1] : '';
		}, $si_test_state['actions']['init'] ?? [] );
		$this->assertContains( 'schedule', $init );
	}
}
