<?php
namespace JAT_Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Notifications & Scheduled Reports.
 *
 * Weekly email digest, score drop alerts, Slack webhook integration,
 * configurable per-module thresholds, and admin dashboard widget.
 *
 * @since 3.1.0
 */
class Notifications {

	/** @var array */
	private $settings;

	/** Cron hook for weekly digest. */
	private const DIGEST_HOOK = 'jat_search_intel_weekly_digest';

	/** Cron hook for score drop checks. */
	private const SCORE_DROP_HOOK = 'jat_search_intel_score_drop_check';

	/** Option key for storing previous score snapshots. */
	private const SNAPSHOT_OPTION = 'jat_search_intel_score_snapshots';

	/**
	 * @param array $settings Plugin settings.
	 */
	public function __construct( array $settings ) {
		$this->settings = $settings;

		if ( empty( $settings['enable_notifications'] ) ) {
			return;
		}

		// Schedule cron events.
		add_action( 'init', [ $this, 'schedule_events' ] );

		// Cron callbacks.
		add_action( self::DIGEST_HOOK, [ $this, 'send_weekly_digest' ] );
		add_action( self::SCORE_DROP_HOOK, [ $this, 'check_score_drops' ] );

		// Dashboard widget.
		add_action( 'wp_dashboard_setup', [ $this, 'register_dashboard_widget' ] );

		// Snapshot scores after each analysis run.
		add_action( 'jat_search_intel_analysis_complete', [ $this, 'snapshot_post_scores' ], 10, 2 );
	}

	// ── Scheduling ───────────────────────────────────────────────────────────

	/**
	 * Ensure cron events are scheduled.
	 */
	public function schedule_events(): void {
		if ( ! wp_next_scheduled( self::DIGEST_HOOK ) ) {
			wp_schedule_event( time(), 'weekly', self::DIGEST_HOOK );
		}

		if ( ! wp_next_scheduled( self::SCORE_DROP_HOOK ) ) {
			wp_schedule_event( time(), 'daily', self::SCORE_DROP_HOOK );
		}
	}

	/**
	 * Unschedule cron events (for deactivation).
	 */
	public function unschedule_events(): void {
		$digest_ts = wp_next_scheduled( self::DIGEST_HOOK );
		if ( $digest_ts ) {
			wp_unschedule_event( $digest_ts, self::DIGEST_HOOK );
		}

		$drop_ts = wp_next_scheduled( self::SCORE_DROP_HOOK );
		if ( $drop_ts ) {
			wp_unschedule_event( $drop_ts, self::SCORE_DROP_HOOK );
		}
	}

	// ── Thresholds ───────────────────────────────────────────────────────────

	/**
	 * Get the configured alert thresholds per module.
	 *
	 * @return array{seo: int, aeo: int, geo: int}
	 */
	public function get_thresholds(): array {
		return [
			'seo' => (int) ( $this->settings['alert_threshold_seo'] ?? 50 ),
			'aeo' => (int) ( $this->settings['alert_threshold_aeo'] ?? 50 ),
			'geo' => (int) ( $this->settings['alert_threshold_geo'] ?? 50 ),
		];
	}

	// ── Score Snapshots ──────────────────────────────────────────────────────

	/**
	 * Store a score snapshot for a post after analysis.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $scores  Array with seo, aeo, geo, readability scores.
	 */
	public function snapshot_post_scores( int $post_id, array $scores ): void {
		$snapshots = get_option( self::SNAPSHOT_OPTION, [] );
		$snapshots = is_array( $snapshots ) ? $snapshots : [];

		$snapshots[ $post_id ] = [
			'seo'         => (int) ( $scores['seo'] ?? 0 ),
			'aeo'         => (int) ( $scores['aeo'] ?? 0 ),
			'geo'         => (int) ( $scores['geo'] ?? 0 ),
			'readability' => (int) ( $scores['readability'] ?? 0 ),
			'timestamp'   => current_time( 'mysql' ),
		];

		update_option( self::SNAPSHOT_OPTION, $snapshots, false );
	}

	/**
	 * Get stored score snapshots.
	 *
	 * @return array<int, array>
	 */
	public function get_snapshots(): array {
		$snapshots = get_option( self::SNAPSHOT_OPTION, [] );
		return is_array( $snapshots ) ? $snapshots : [];
	}

	// ── Score Drop Detection ─────────────────────────────────────────────────

	/**
	 * Check all published posts for score drops below configured thresholds.
	 *
	 * Compares current scores against stored snapshots. If a score dropped
	 * below the threshold, triggers an alert.
	 */
	public function check_score_drops(): void {
		$thresholds = $this->get_thresholds();
		$snapshots  = $this->get_snapshots();
		$alerts     = [];

		$posts = get_posts( [
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		] );

		foreach ( $posts as $post_id ) {
			$current_seo = (int) get_post_meta( $post_id, '_jat_search_intel_seo_score', true );
			$current_aeo = (int) get_post_meta( $post_id, '_jat_search_intel_aeo_score', true );
			$current_geo = (int) get_post_meta( $post_id, '_jat_search_intel_geo_score', true );

			$prev = $snapshots[ $post_id ] ?? null;

			// Check threshold violations.
			$violations = [];
			if ( $current_seo > 0 && $current_seo < $thresholds['seo'] ) {
				$violations['seo'] = [
					'current'  => $current_seo,
					'previous' => $prev['seo'] ?? null,
					'threshold'=> $thresholds['seo'],
				];
			}
			if ( $current_aeo > 0 && $current_aeo < $thresholds['aeo'] ) {
				$violations['aeo'] = [
					'current'  => $current_aeo,
					'previous' => $prev['aeo'] ?? null,
					'threshold'=> $thresholds['aeo'],
				];
			}
			if ( $current_geo > 0 && $current_geo < $thresholds['geo'] ) {
				$violations['geo'] = [
					'current'  => $current_geo,
					'previous' => $prev['geo'] ?? null,
					'threshold'=> $thresholds['geo'],
				];
			}

			if ( ! empty( $violations ) ) {
				$alerts[] = [
					'post_id'    => $post_id,
					'title'      => get_the_title( $post_id ),
					'violations' => $violations,
				];
			}
		}

		if ( empty( $alerts ) ) {
			return;
		}

		// Send alerts via email.
		$this->send_score_drop_email( $alerts );

		// Send alerts via Slack if configured.
		$this->send_slack_alert( $this->format_score_drop_slack( $alerts ) );
	}

	// ── Weekly Email Digest ──────────────────────────────────────────────────

	/**
	 * Send the weekly site health digest email.
	 */
	public function send_weekly_digest(): void {
		$recipient = $this->get_notification_email();
		if ( empty( $recipient ) ) {
			return;
		}

		$summary    = $this->build_digest_summary();
		$site_name  = get_bloginfo( 'name' );
		$subject    = sprintf( '[%s] Weekly SEO Health Summary', $site_name );

		$body  = sprintf( "Weekly SEO Health Report for %s\n", $site_name );
		$body .= sprintf( "Generated: %s\n\n", current_time( 'F j, Y' ) );

		$body .= "── Score Averages ──\n";
		$body .= sprintf( "SEO:         %s/100\n", $summary['avg_seo'] );
		$body .= sprintf( "AEO:         %s/100\n", $summary['avg_aeo'] );
		$body .= sprintf( "GEO:         %s/100\n", $summary['avg_geo'] );
		$body .= sprintf( "Readability: %s/100\n\n", $summary['avg_readability'] );

		$body .= sprintf( "Total scored posts: %d\n", $summary['total_posts'] );
		$body .= sprintf( "Posts below threshold: %d\n\n", $summary['below_threshold'] );

		if ( ! empty( $summary['worst_posts'] ) ) {
			$body .= "── Lowest Scoring Posts ──\n";
			foreach ( $summary['worst_posts'] as $post ) {
				$body .= sprintf(
					"  • %s (ID %d) — SEO: %d, AEO: %d, GEO: %d\n",
					$post['title'],
					$post['post_id'],
					$post['seo'],
					$post['aeo'],
					$post['geo']
				);
			}
			$body .= "\n";
		}

		if ( ! empty( $summary['recommendations'] ) ) {
			$body .= "── Recommendations ──\n";
			foreach ( $summary['recommendations'] as $rec ) {
				$body .= "  • {$rec}\n";
			}
		}

		wp_mail( $recipient, $subject, $body );

		// Also send to Slack if configured.
		$this->send_slack_alert( $this->format_digest_slack( $summary ) );
	}

	/**
	 * Build the digest summary data.
	 *
	 * @return array
	 */
	public function build_digest_summary(): array {
		$thresholds = $this->get_thresholds();

		$posts = get_posts( [
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		] );

		$total = count( $posts );
		$seo = $aeo = $geo = $read = 0;
		$below = 0;
		$scored = [];

		foreach ( $posts as $post_id ) {
			$s = (int) get_post_meta( $post_id, '_jat_search_intel_seo_score', true );
			$a = (int) get_post_meta( $post_id, '_jat_search_intel_aeo_score', true );
			$g = (int) get_post_meta( $post_id, '_jat_search_intel_geo_score', true );
			$r = (int) get_post_meta( $post_id, '_jat_search_intel_readability_score', true );

			$seo  += $s;
			$aeo  += $a;
			$geo  += $g;
			$read += $r;

			if ( $s < $thresholds['seo'] || $a < $thresholds['aeo'] || $g < $thresholds['geo'] ) {
				$below++;
			}

			$scored[] = [
				'post_id' => $post_id,
				'title'   => get_the_title( $post_id ),
				'seo'     => $s,
				'aeo'     => $a,
				'geo'     => $g,
				'avg'     => $total > 0 ? round( ( $s + $a + $g ) / 3, 1 ) : 0,
			];
		}

		// Sort by average score ascending — worst first.
		usort( $scored, function ( $a, $b ) {
			return $a['avg'] <=> $b['avg'];
		} );

		$recommendations = [];
		$avg_seo = $total > 0 ? round( $seo / $total, 1 ) : 0;
		$avg_aeo = $total > 0 ? round( $aeo / $total, 1 ) : 0;
		$avg_geo = $total > 0 ? round( $geo / $total, 1 ) : 0;

		if ( $avg_seo < 60 ) {
			$recommendations[] = 'Average SEO score is below 60. Consider adding focus keywords and meta descriptions to more posts.';
		}
		if ( $avg_aeo < 60 ) {
			$recommendations[] = 'Average AEO score is below 60. Add FAQ schemas and direct answers to improve AI engine visibility.';
		}
		if ( $avg_geo < 60 ) {
			$recommendations[] = 'Average GEO score is below 60. Improve entity tagging and structured data for generative search.';
		}
		if ( $below > $total * 0.3 ) {
			$recommendations[] = sprintf( '%d of %d posts are below your score thresholds. Consider a bulk rescore and optimization pass.', $below, $total );
		}

		return [
			'total_posts'     => $total,
			'below_threshold' => $below,
			'avg_seo'         => $avg_seo,
			'avg_aeo'         => $avg_aeo,
			'avg_geo'         => $avg_geo,
			'avg_readability' => $total > 0 ? round( $read / $total, 1 ) : 0,
			'worst_posts'     => array_slice( $scored, 0, 5 ),
			'recommendations' => $recommendations,
		];
	}

	// ── Score Drop Email ─────────────────────────────────────────────────────

	/**
	 * Send a score drop alert email.
	 *
	 * @param array $alerts Array of alert data.
	 */
	private function send_score_drop_email( array $alerts ): void {
		$recipient = $this->get_notification_email();
		if ( empty( $recipient ) ) {
			return;
		}

		$site_name = get_bloginfo( 'name' );
		$subject   = sprintf( '[%s] Score Drop Alert — %d post(s) below threshold', $site_name, count( $alerts ) );

		$body = "The following posts have scores below your configured thresholds:\n\n";

		foreach ( $alerts as $alert ) {
			$body .= sprintf( "• %s (ID %d)\n", $alert['title'], $alert['post_id'] );
			foreach ( $alert['violations'] as $module => $data ) {
				$body .= sprintf(
					"    %s: %d (threshold: %d%s)\n",
					strtoupper( $module ),
					$data['current'],
					$data['threshold'],
					$data['previous'] !== null ? sprintf( ', was %d', $data['previous'] ) : ''
				);
			}
			$body .= "\n";
		}

		$body .= sprintf( "Review at: %s\n", admin_url( 'admin.php?page=jat-search-intel-bulk-editor' ) );

		wp_mail( $recipient, $subject, $body );
	}

	// ── Slack Integration ────────────────────────────────────────────────────

	/**
	 * Send a message to the configured Slack webhook.
	 *
	 * @param string $text Message text.
	 * @return bool
	 */
	public function send_slack_alert( string $text ): bool {
		$webhook_url = $this->settings['slack_webhook_url'] ?? '';
		if ( empty( $webhook_url ) || empty( $text ) ) {
			return false;
		}

		$payload = wp_json_encode( [ 'text' => $text ] );

		$response = wp_remote_post( $webhook_url, [
			'headers' => [ 'Content-Type' => 'application/json' ],
			'body'    => $payload,
			'timeout' => 10,
		] );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );
		return $code >= 200 && $code < 300;
	}

	/**
	 * Format score drop alerts for Slack.
	 *
	 * @param array $alerts Alert data.
	 * @return string
	 */
	private function format_score_drop_slack( array $alerts ): string {
		$site = get_bloginfo( 'name' );
		$lines = [ "⚠️ *{$site} — Score Drop Alert*" ];
		$lines[] = sprintf( '%d post(s) below threshold:', count( $alerts ) );

		foreach ( array_slice( $alerts, 0, 10 ) as $alert ) {
			$modules = [];
			foreach ( $alert['violations'] as $m => $d ) {
				$modules[] = sprintf( '%s: %d/%d', strtoupper( $m ), $d['current'], $d['threshold'] );
			}
			$lines[] = sprintf( '• *%s* — %s', $alert['title'], implode( ', ', $modules ) );
		}

		if ( count( $alerts ) > 10 ) {
			$lines[] = sprintf( '...and %d more.', count( $alerts ) - 10 );
		}

		return implode( "\n", $lines );
	}

	/**
	 * Format digest summary for Slack.
	 *
	 * @param array $summary Digest summary data.
	 * @return string
	 */
	private function format_digest_slack( array $summary ): string {
		$site = get_bloginfo( 'name' );
		$lines = [ "📊 *{$site} — Weekly SEO Health*" ];
		$lines[] = sprintf(
			'Averages — SEO: %s | AEO: %s | GEO: %s | Readability: %s',
			$summary['avg_seo'],
			$summary['avg_aeo'],
			$summary['avg_geo'],
			$summary['avg_readability']
		);
		$lines[] = sprintf(
			'%d total posts, %d below threshold',
			$summary['total_posts'],
			$summary['below_threshold']
		);

		if ( ! empty( $summary['recommendations'] ) ) {
			$lines[] = '*Recommendations:*';
			foreach ( $summary['recommendations'] as $rec ) {
				$lines[] = "• {$rec}";
			}
		}

		return implode( "\n", $lines );
	}

	// ── Dashboard Widget ─────────────────────────────────────────────────────

	/**
	 * Register the admin dashboard widget.
	 */
	public function register_dashboard_widget(): void {
		$name = 'JAT Search Intel';

		// Use white-label name if available.
		$custom_name = $this->settings['wl_plugin_name'] ?? '';
		if ( ! empty( $custom_name ) ) {
			$name = $custom_name;
		}

		wp_add_dashboard_widget(
			'jat_search_intel_health',
			$name . ' — Site Health',
			[ $this, 'render_dashboard_widget' ]
		);
	}

	/**
	 * Render the dashboard widget content.
	 */
	public function render_dashboard_widget(): void {
		$summary    = $this->build_digest_summary();
		$thresholds = $this->get_thresholds();

		echo '<div class="si-dashboard-widget">';

		// Score bars.
		$modules = [
			'SEO'         => [ 'avg' => $summary['avg_seo'], 'threshold' => $thresholds['seo'] ],
			'AEO'         => [ 'avg' => $summary['avg_aeo'], 'threshold' => $thresholds['aeo'] ],
			'GEO'         => [ 'avg' => $summary['avg_geo'], 'threshold' => $thresholds['geo'] ],
			'Readability' => [ 'avg' => $summary['avg_readability'], 'threshold' => 0 ],
		];

		foreach ( $modules as $label => $data ) {
			$color = $data['avg'] >= 80 ? '#00a32a' : ( $data['avg'] >= 50 ? '#dba617' : '#cc1818' );
			$warn  = ( $data['threshold'] > 0 && $data['avg'] < $data['threshold'] ) ? ' ⚠' : '';

			printf(
				'<div style="margin-bottom:8px"><strong>%s</strong>%s <span style="float:right;color:%s">%s/100</span>'
				. '<div style="background:#f0f0f0;height:8px;border-radius:4px;margin-top:4px">'
				. '<div style="background:%s;height:8px;border-radius:4px;width:%s%%"></div>'
				. '</div></div>',
				esc_html( $label ),
				wp_kses_post( $warn ),
				esc_attr( $color ),
				esc_html( (string) $data['avg'] ),
				esc_attr( $color ),
				esc_attr( (string) min( 100, $data['avg'] ) )
			);
		}

		// Summary stats.
		printf(
			'<p style="margin-top:12px;color:#666">%d posts scored · %d below threshold</p>',
			esc_html( $summary['total_posts'] ),
			esc_html( $summary['below_threshold'] )
		);

		// Worst posts.
		if ( ! empty( $summary['worst_posts'] ) ) {
			echo '<p><strong>Needs attention:</strong></p><ul style="margin:0;padding-left:16px">';
			foreach ( array_slice( $summary['worst_posts'], 0, 3 ) as $post ) {
				printf(
					'<li><a href="%s">%s</a> — SEO: %d, AEO: %d, GEO: %d</li>',
					esc_url( get_edit_post_link( $post['post_id'] ) ),
					esc_html( $post['title'] ),
					esc_html( $post['seo'] ),
					esc_html( $post['aeo'] ),
					esc_html( $post['geo'] )
				);
			}
			echo '</ul>';
		}

		// Links.
		printf(
			'<p style="margin-top:12px"><a href="%s">View all scores →</a></p>',
			esc_url( admin_url( 'admin.php?page=jat-search-intel-bulk-editor' ) )
		);

		echo '</div>';
	}

	// ── Helpers ──────────────────────────────────────────────────────────────

	/**
	 * Get the notification email address.
	 *
	 * @return string
	 */
	public function get_notification_email(): string {
		$email = $this->settings['notification_email'] ?? '';
		if ( ! empty( $email ) && is_email( $email ) ) {
			return $email;
		}
		return get_option( 'admin_email', '' );
	}
}
