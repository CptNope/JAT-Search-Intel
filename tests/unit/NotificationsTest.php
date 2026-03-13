<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Search_Intel\Notifications;

/**
 * Unit tests for the Notifications & Scheduled Reports class.
 */
class NotificationsTest extends TestCase {

	private Notifications $notifier;

	protected function setUp(): void {
		global $si_test_state;
		$si_test_state['options']   = [];
		$si_test_state['posts']     = [];
		$si_test_state['post_meta'] = [];
		$si_test_state['last_email'] = null;

		$this->notifier = new Notifications( [
			'enable_notifications' => true,
			'notification_email'   => 'admin@example.com',
			'alert_threshold_seo'  => 50,
			'alert_threshold_aeo'  => 50,
			'alert_threshold_geo'  => 50,
			'slack_webhook_url'    => '',
		] );
	}

	// ── Thresholds ───────────────────────────────────────────────────────────

	public function test_get_thresholds_returns_configured_values(): void {
		$thresholds = $this->notifier->get_thresholds();

		$this->assertEquals( 50, $thresholds['seo'] );
		$this->assertEquals( 50, $thresholds['aeo'] );
		$this->assertEquals( 50, $thresholds['geo'] );
	}

	public function test_get_thresholds_with_custom_values(): void {
		$notifier = new Notifications( [
			'enable_notifications' => true,
			'alert_threshold_seo'  => 70,
			'alert_threshold_aeo'  => 60,
			'alert_threshold_geo'  => 80,
		] );

		$thresholds = $notifier->get_thresholds();

		$this->assertEquals( 70, $thresholds['seo'] );
		$this->assertEquals( 60, $thresholds['aeo'] );
		$this->assertEquals( 80, $thresholds['geo'] );
	}

	// ── Score Snapshots ──────────────────────────────────────────────────────

	public function test_snapshot_post_scores_stores_data(): void {
		$this->notifier->snapshot_post_scores( 42, [
			'seo'         => 80,
			'aeo'         => 70,
			'geo'         => 60,
			'readability' => 90,
		] );

		$snapshots = $this->notifier->get_snapshots();

		$this->assertArrayHasKey( 42, $snapshots );
		$this->assertEquals( 80, $snapshots[42]['seo'] );
		$this->assertEquals( 70, $snapshots[42]['aeo'] );
		$this->assertEquals( 60, $snapshots[42]['geo'] );
		$this->assertEquals( 90, $snapshots[42]['readability'] );
		$this->assertArrayHasKey( 'timestamp', $snapshots[42] );
	}

	public function test_get_snapshots_returns_empty_by_default(): void {
		$this->assertEmpty( $this->notifier->get_snapshots() );
	}

	// ── Notification Email ───────────────────────────────────────────────────

	public function test_get_notification_email_returns_configured_email(): void {
		$this->assertEquals( 'admin@example.com', $this->notifier->get_notification_email() );
	}

	public function test_get_notification_email_falls_back_to_admin_email(): void {
		global $si_test_state;
		$si_test_state['options']['admin_email'] = 'fallback@example.com';

		$notifier = new Notifications( [
			'enable_notifications' => true,
			'notification_email'   => '',
		] );

		$this->assertEquals( 'fallback@example.com', $notifier->get_notification_email() );
	}

	// ── Digest Summary ───────────────────────────────────────────────────────

	public function test_build_digest_summary_empty_site(): void {
		$summary = $this->notifier->build_digest_summary();

		$this->assertEquals( 0, $summary['total_posts'] );
		$this->assertEquals( 0, $summary['below_threshold'] );
		$this->assertEquals( 0, $summary['avg_seo'] );
		$this->assertEmpty( $summary['worst_posts'] );
	}

	public function test_build_digest_summary_with_posts(): void {
		global $si_test_state;

		$si_test_state['posts'][1] = [
			'ID' => 1, 'post_title' => 'Good Post',
			'post_content' => 'c', 'post_status' => 'publish', 'post_type' => 'post',
		];
		$si_test_state['posts'][2] = [
			'ID' => 2, 'post_title' => 'Bad Post',
			'post_content' => 'c', 'post_status' => 'publish', 'post_type' => 'post',
		];

		$si_test_state['post_meta'][1] = [
			'_search_intel_seo_score' => '80',
			'_search_intel_aeo_score' => '70',
			'_search_intel_geo_score' => '90',
			'_search_intel_readability_score' => '85',
		];
		$si_test_state['post_meta'][2] = [
			'_search_intel_seo_score' => '30',
			'_search_intel_aeo_score' => '20',
			'_search_intel_geo_score' => '40',
			'_search_intel_readability_score' => '50',
		];

		$summary = $this->notifier->build_digest_summary();

		$this->assertEquals( 2, $summary['total_posts'] );
		$this->assertEquals( 1, $summary['below_threshold'] );
		$this->assertEquals( 55.0, $summary['avg_seo'] );
		$this->assertEquals( 45.0, $summary['avg_aeo'] );
		$this->assertEquals( 65.0, $summary['avg_geo'] );
		$this->assertNotEmpty( $summary['worst_posts'] );
		// Worst post should be first.
		$this->assertEquals( 'Bad Post', $summary['worst_posts'][0]['title'] );
	}

	public function test_build_digest_summary_generates_recommendations(): void {
		global $si_test_state;

		$si_test_state['posts'][1] = [
			'ID' => 1, 'post_title' => 'Low Post',
			'post_content' => 'c', 'post_status' => 'publish', 'post_type' => 'post',
		];
		$si_test_state['post_meta'][1] = [
			'_search_intel_seo_score' => '30',
			'_search_intel_aeo_score' => '20',
			'_search_intel_geo_score' => '25',
			'_search_intel_readability_score' => '40',
		];

		$summary = $this->notifier->build_digest_summary();

		$this->assertNotEmpty( $summary['recommendations'] );
	}

	// ── Weekly Digest ────────────────────────────────────────────────────────

	public function test_send_weekly_digest_sends_email(): void {
		global $si_test_state;

		$si_test_state['posts'][1] = [
			'ID' => 1, 'post_title' => 'Test Post',
			'post_content' => 'c', 'post_status' => 'publish', 'post_type' => 'post',
		];
		$si_test_state['post_meta'][1] = [
			'_search_intel_seo_score' => '75',
			'_search_intel_aeo_score' => '65',
			'_search_intel_geo_score' => '55',
			'_search_intel_readability_score' => '80',
		];

		$this->notifier->send_weekly_digest();

		$this->assertNotNull( $si_test_state['last_email'] );
		$this->assertEquals( 'admin@example.com', $si_test_state['last_email']['to'] );
		$this->assertStringContainsString( 'Weekly SEO Health', $si_test_state['last_email']['subject'] );
		$this->assertStringContainsString( 'SEO:', $si_test_state['last_email']['message'] );
	}

	// ── Score Drop Detection ─────────────────────────────────────────────────

	public function test_check_score_drops_no_alerts_when_above_threshold(): void {
		global $si_test_state;

		$si_test_state['posts'][1] = [
			'ID' => 1, 'post_title' => 'Good Post',
			'post_content' => 'c', 'post_status' => 'publish', 'post_type' => 'post',
		];
		$si_test_state['post_meta'][1] = [
			'_search_intel_seo_score' => '80',
			'_search_intel_aeo_score' => '70',
			'_search_intel_geo_score' => '90',
		];

		$this->notifier->check_score_drops();

		// No email should be sent since all scores are above threshold.
		$this->assertNull( $si_test_state['last_email'] );
	}

	public function test_check_score_drops_sends_alert_for_low_scores(): void {
		global $si_test_state;

		$si_test_state['posts'][1] = [
			'ID' => 1, 'post_title' => 'Dropping Post',
			'post_content' => 'c', 'post_status' => 'publish', 'post_type' => 'post',
		];
		$si_test_state['post_meta'][1] = [
			'_search_intel_seo_score' => '30',
			'_search_intel_aeo_score' => '60',
			'_search_intel_geo_score' => '25',
		];

		$this->notifier->check_score_drops();

		$this->assertNotNull( $si_test_state['last_email'] );
		$this->assertStringContainsString( 'Score Drop Alert', $si_test_state['last_email']['subject'] );
		$this->assertStringContainsString( 'Dropping Post', $si_test_state['last_email']['message'] );
	}

	// ── Slack ────────────────────────────────────────────────────────────────

	public function test_send_slack_alert_returns_false_without_webhook(): void {
		$result = $this->notifier->send_slack_alert( 'Test message' );
		$this->assertFalse( $result );
	}

	public function test_send_slack_alert_sends_with_webhook(): void {
		$notifier = new Notifications( [
			'enable_notifications' => true,
			'slack_webhook_url'    => 'https://hooks.slack.com/services/T00/B00/xxx',
		] );

		$result = $notifier->send_slack_alert( 'Test alert' );
		$this->assertTrue( $result );
	}
}
