<?php
namespace JAT_Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AI Visibility Dashboard.
 *
 * Provides a site-wide GEO health score, API integrations (Google Search Console, Bing Webmaster),
 * llms.txt access log tracking, AI referral traffic identification, visibility trends,
 * prioritized recommendations, and content gap mapping.
 */
class Visibility_Dashboard {

	/** @var array */
	private $settings;

	public function __construct( array $settings ) {
		$this->settings = $settings;

		if ( empty( $settings['enable_visibility_dashboard'] ) ) {
			return;
		}

		// Admin page and AJAX endpoints.
		add_action( 'admin_menu', [ $this, 'register_admin_page' ] );
		add_action( 'wp_ajax_jat_search_intel_refresh_visibility', [ $this, 'ajax_refresh' ] );

		// Track llms.txt access via query var detection.
		add_action( 'template_redirect', [ $this, 'track_llms_access' ], 0 ); // Before llms.txt is served (priority 1) and exits.

		// Schedule daily cron for API data sync.
		add_action( 'init', [ $this, 'schedule_sync' ] );
		add_action( 'jat_search_intel_visibility_sync', [ $this, 'run_sync' ] );
	}

	// ── GEO Health Score ─────────────────────────────────────────────────────

	/**
	 * Site-wide GEO health score (0–100). See GEO_Health for the factors and weights.
	 *
	 * @return array{score:int, grade:string, factors:array<string,array{label:string,score:int,max:int,detail:string}>}
	 */
	public function calculate_health_score(): array {
		return ( new GEO_Health( $this->settings ) )->calculate();
	}

	// ── API Integrations ─────────────────────────────────────────────────────

	/**
	 * Fetch Google Search Console performance data.
	 *
	 * @param string $access_token OAuth2 access token.
	 * @param string $site_url     Verified property URL.
	 * @param int    $days         Lookback period.
	 * @return array{rows: array, totals: array}|null
	 */
	public function fetch_gsc_data( string $access_token, string $site_url, int $days = 28 ): ?array {
		if ( empty( $access_token ) || empty( $site_url ) ) {
			return null;
		}

		$start = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );
		$end   = gmdate( 'Y-m-d', strtotime( '-2 days' ) );

		$response = wp_remote_post(
			'https://searchconsole.googleapis.com/webmasters/v3/sites/' . rawurlencode( $site_url ) . '/searchAnalytics/query',
			[
				'headers' => [
					'Authorization' => 'Bearer ' . $access_token,
					'Content-Type'  => 'application/json',
				],
				'body'    => (string) wp_json_encode( [
					'startDate'  => $start,
					'endDate'    => $end,
					'dimensions' => [ 'query', 'page' ],
					'rowLimit'   => 100,
				] ),
				'timeout' => 30,
			]
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) ) {
			return null;
		}

		return [
			'rows'   => $body['rows'] ?? [],
			'totals' => [
				'clicks'      => array_sum( array_column( $body['rows'] ?? [], 'clicks' ) ),
				'impressions' => array_sum( array_column( $body['rows'] ?? [], 'impressions' ) ),
			],
		];
	}

	/**
	 * Fetch Bing Webmaster API data.
	 *
	 * @param string $api_key  Bing Webmaster API key.
	 * @param string $site_url Verified site URL.
	 * @return array|null
	 */
	public function fetch_bing_data( string $api_key, string $site_url ): ?array {
		if ( empty( $api_key ) || empty( $site_url ) ) {
			return null;
		}

		$encoded = rawurlencode( $site_url );
		$url     = "https://ssl.bing.com/webmaster/api.svc/json/GetQueryStats?siteUrl={$encoded}&apikey={$api_key}";

		$response = wp_remote_get( $url, [ 'timeout' => 30 ] );

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return is_array( $body ) ? $body : null;
	}

	// ── llms.txt Access Tracking ─────────────────────────────────────────────

	/**
	 * Track access to the llms.txt endpoint and log AI bot visits.
	 */
	public function track_llms_access(): void {
		$is_llms      = get_query_var( 'jat_search_intel_llms_txt' );
		$is_llms_full = get_query_var( 'jat_search_intel_llms_full_txt' );

		if ( ! $is_llms && ! $is_llms_full ) {
			return;
		}

		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$ip         = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$bot        = $this->identify_ai_bot( $user_agent );

		$log = get_option( 'jat_search_intel_llms_access_log', [] );
		if ( ! is_array( $log ) ) {
			$log = [];
		}

		$log[] = [
			'time'       => current_time( 'mysql' ),
			'endpoint'   => $is_llms_full ? 'llms-full.txt' : 'llms.txt',
			'user_agent' => $user_agent,
			'ip'         => '' !== $ip ? hash( 'sha256', $ip . wp_salt() ) : '', // Hashed: raw IPs are never stored.
			'bot'        => $bot,
		];

		// Keep last 500 entries.
		if ( count( $log ) > 500 ) {
			$log = array_slice( $log, -500 );
		}

		update_option( 'jat_search_intel_llms_access_log', $log, false );
	}

	/**
	 * Get the llms.txt access log.
	 *
	 * @param int $limit Max entries.
	 * @return array
	 */
	public function get_llms_access_log( int $limit = 50 ): array {
		$log = get_option( 'jat_search_intel_llms_access_log', [] );
		if ( ! is_array( $log ) ) {
			return [];
		}

		return array_slice( array_reverse( $log ), 0, $limit );
	}

	// ── AI Referral Traffic ──────────────────────────────────────────────────

	/**
	 * Identify known AI bot from user agent string.
	 *
	 * @param string $user_agent Full user agent.
	 * @return string Bot identifier or 'unknown'.
	 */
	public function identify_ai_bot( string $user_agent ): string {
		$bots = [
			'GPTBot'         => 'OpenAI GPTBot',
			'ChatGPT-User'   => 'ChatGPT User',
			'Google-Extended' => 'Google AI',
			'Googlebot'      => 'Googlebot',
			'Bingbot'        => 'Bingbot',
			'CCBot'          => 'Common Crawl',
			'anthropic-ai'   => 'Anthropic',
			'ClaudeBot'      => 'Claude',
			'PerplexityBot'  => 'Perplexity',
			'Bytespider'     => 'ByteDance',
			'Applebot'       => 'Apple',
			'facebookexternalhit' => 'Facebook',
			'Twitterbot'     => 'Twitter/X',
		];

		foreach ( $bots as $needle => $label ) {
			if ( stripos( $user_agent, $needle ) !== false ) {
				return $label;
			}
		}

		return 'unknown';
	}

	/**
	 * Identify AI referral sources from a referrer URL.
	 *
	 * @param string $referrer Referrer URL.
	 * @return string Source name or empty.
	 */
	public function identify_ai_referral( string $referrer ): string {
		$sources = [
			'chat.openai.com'   => 'ChatGPT',
			'chatgpt.com'       => 'ChatGPT',
			'perplexity.ai'     => 'Perplexity',
			'you.com'           => 'You.com',
			'phind.com'         => 'Phind',
			'bard.google.com'   => 'Google Bard',
			'gemini.google.com' => 'Google Gemini',
			'copilot.microsoft.com' => 'Microsoft Copilot',
			'claude.ai'         => 'Claude',
		];

		foreach ( $sources as $domain => $name ) {
			if ( stripos( $referrer, $domain ) !== false ) {
				return $name;
			}
		}

		return '';
	}

	/**
	 * Get AI referral traffic summary from stored log.
	 *
	 * @param int $days Lookback period in days.
	 * @return array{total: int, by_source: array, trend: array}
	 */
	public function get_ai_referral_summary( int $days = 30 ): array {
		$log = get_option( 'jat_search_intel_ai_referrals', [] );
		if ( ! is_array( $log ) ) {
			$log = [];
		}

		$cutoff    = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );
		$total     = 0;
		$by_source = [];
		$by_day    = [];

		foreach ( $log as $entry ) {
			if ( ( $entry['date'] ?? '' ) < $cutoff ) {
				continue;
			}

			$total++;
			$src = $entry['source'] ?? 'unknown';
			$day = $entry['date'] ?? '';

			$by_source[ $src ] = ( $by_source[ $src ] ?? 0 ) + 1;
			$by_day[ $day ]    = ( $by_day[ $day ] ?? 0 ) + 1;
		}

		// Visits recorded by the AI Search analytics module.
		$recorded = AI_Search::summary( 'referrals', $days );
		$total   += $recorded['total'];
		foreach ( $recorded['by_label'] as $src => $count ) {
			$by_source[ $src ] = ( $by_source[ $src ] ?? 0 ) + $count;
		}
		foreach ( $recorded['by_day'] as $day => $count ) {
			$by_day[ $day ] = ( $by_day[ $day ] ?? 0 ) + $count;
		}

		arsort( $by_source );
		ksort( $by_day );

		return [
			'total'     => $total,
			'by_source' => $by_source,
			'trend'     => $by_day,
		];
	}

	// ── Visibility Trends ────────────────────────────────────────────────────

	/**
	 * Get visibility score trend over time.
	 *
	 * @param int $days Number of days.
	 * @return array Daily score entries.
	 */
	public function get_visibility_trend( int $days = 30 ): array {
		$trend = get_option( 'jat_search_intel_visibility_trend', [] );
		if ( ! is_array( $trend ) ) {
			return [];
		}

		return array_slice( $trend, -$days );
	}

	/**
	 * Record today's visibility score snapshot.
	 */
	public function record_daily_score(): void {
		$health = $this->calculate_health_score();
		$trend  = get_option( 'jat_search_intel_visibility_trend', [] );
		if ( ! is_array( $trend ) ) {
			$trend = [];
		}

		$today = gmdate( 'Y-m-d' );

		// Avoid duplicate entries for today.
		foreach ( $trend as $key => $entry ) {
			if ( ( $entry['date'] ?? '' ) === $today ) {
				$trend[ $key ] = [
					'date'  => $today,
					'score' => $health['score'],
					'grade' => $health['grade'],
				];
				update_option( 'jat_search_intel_visibility_trend', $trend, false );
				return;
			}
		}

		$trend[] = [
			'date'  => $today,
			'score' => $health['score'],
			'grade' => $health['grade'],
		];

		// Keep last 365 days.
		if ( count( $trend ) > 365 ) {
			$trend = array_slice( $trend, -365 );
		}

		update_option( 'jat_search_intel_visibility_trend', $trend, false );
	}

	// ── Recommendations ──────────────────────────────────────────────────────

	/**
	 * Generate prioritized recommendations based on current health score factors.
	 *
	 * @return array List of recommendation arrays with priority, message, action.
	 */
	public function get_recommendations(): array {
		$health = $this->calculate_health_score();
		$recs   = [];

		foreach ( $health['factors'] as $key => $factor ) {
			if ( $factor['max'] <= 0 ) {
				continue; // Optional extras earn no points, so they never need fixing.
			}
			$ratio = $factor['score'] / $factor['max'];

			if ( $ratio >= 0.8 ) {
				continue;
			}

			$priority = $ratio < 0.3 ? 'high' : ( $ratio < 0.6 ? 'medium' : 'low' );

			$recs[] = [
				'factor'   => $key,
				'label'    => $factor['label'],
				'priority' => $priority,
				'score'    => $factor['score'],
				'max'      => $factor['max'],
				'message'  => $this->recommendation_message( $key, $factor ),
			];
		}

		// Sort by priority: high > medium > low.
		usort( $recs, function ( $a, $b ) {
			$order = [ 'high' => 0, 'medium' => 1, 'low' => 2 ];
			return ( $order[ $a['priority'] ] ?? 3 ) - ( $order[ $b['priority'] ] ?? 3 );
		} );

		return $recs;
	}

	/**
	 * Generate recommendation message for a specific factor.
	 *
	 * @param string $key    Factor key.
	 * @param array  $factor Factor data.
	 * @return string Human-readable recommendation.
	 */
	private function recommendation_message( string $key, array $factor ): string {
		$message = GEO_Health::recommendation( $key );
		/* translators: 1: factor name, 2: points, 3: maximum points. */
		return '' !== $message ? $message : sprintf( __( 'Improve %1$s (currently %2$d/%3$d).', 'jat-search-intel' ), $factor['label'], $factor['score'], $factor['max'] );
	}

	// ── Content Gap Map ──────────────────────────────────────────────────────

	/**
	 * Analyze content coverage gaps across categories.
	 *
	 * Identifies thin categories, categories without proper GEO optimization,
	 * and missing content types.
	 *
	 * @return array{gaps: array, coverage: array}
	 */
	public function analyze_content_gaps(): array {
		$categories = get_categories( [ 'hide_empty' => false ] );
		$gaps       = [];
		$coverage   = [];

		foreach ( $categories as $cat ) {
			$posts = get_posts( [
				'category'    => $cat->term_id,
				'numberposts' => 50,
				'post_status' => 'publish',
				'fields'      => 'ids',
			] );

			$count      = count( $posts );
			$with_meta  = 0;
			$with_schema = 0;

			foreach ( $posts as $pid ) {
				if ( get_post_meta( $pid, '_jat_search_intel_ai_summary', true ) ) {
					$with_meta++;
				}
				if ( get_post_meta( $pid, '_jat_search_intel_schema_type_extra', true ) ) {
					$with_schema++;
				}
			}

			$coverage[] = [
				'category'        => $cat->name,
				'term_id'         => $cat->term_id,
				'post_count'      => $count,
				'ai_meta_count'   => $with_meta,
				'schema_count'    => $with_schema,
				'meta_coverage'   => $count > 0 ? round( $with_meta / $count * 100 ) : 0,
				'schema_coverage' => $count > 0 ? round( $with_schema / $count * 100 ) : 0,
			];

			// Identify gaps.
			if ( $count === 0 ) {
				$gaps[] = [
					'type'     => 'empty_category',
					'category' => $cat->name,
					'message'  => sprintf( 'Category "%s" has no published content.', $cat->name ),
					'priority' => 'medium',
				];
			} elseif ( $count < 3 ) {
				$gaps[] = [
					'type'     => 'thin_category',
					'category' => $cat->name,
					'message'  => sprintf( 'Category "%s" has only %d post(s) — consider adding more.', $cat->name, $count ),
					'priority' => 'low',
				];
			}

			if ( $count > 0 && $with_meta === 0 ) {
				$gaps[] = [
					'type'     => 'no_ai_meta',
					'category' => $cat->name,
					'message'  => sprintf( 'No posts in "%s" have AI meta tags.', $cat->name ),
					'priority' => 'high',
				];
			}
		}

		// Sort gaps by priority.
		usort( $gaps, function ( $a, $b ) {
			$order = [ 'high' => 0, 'medium' => 1, 'low' => 2 ];
			return ( $order[ $a['priority'] ] ?? 3 ) - ( $order[ $b['priority'] ] ?? 3 );
		} );

		return [
			'gaps'     => $gaps,
			'coverage' => $coverage,
		];
	}

	// ── Cron ─────────────────────────────────────────────────────────────────

	/**
	 * Schedule daily visibility sync.
	 */
	public function schedule_sync(): void {
		if ( ! wp_next_scheduled( 'jat_search_intel_visibility_sync' ) ) {
			wp_schedule_event( time(), 'daily', 'jat_search_intel_visibility_sync' );
		}
	}

	/**
	 * Run daily sync: record score and optionally pull API data.
	 */
	public function run_sync(): void {
		$this->record_daily_score();
	}

	// ── Admin ────────────────────────────────────────────────────────────────

	/**
	 * Register admin submenu page.
	 */
	public function register_admin_page(): void {
		add_submenu_page(
			'jat-search-intel',
			'AI Visibility Dashboard',
			'Visibility',
			'manage_options',
			'jat-search-intel-visibility',
			[ $this, 'render_admin_page' ]
		);
	}

	/**
	 * Render the admin dashboard page.
	 */
	public function render_admin_page(): void {
		$health = $this->calculate_health_score();
		$recs   = $this->get_recommendations();
		$log    = $this->get_llms_access_log( 20 );
		$ai_ref = $this->get_ai_referral_summary( 30 );
		$gaps   = $this->analyze_content_gaps();

		echo '<div class="wrap"><h1>AI Visibility Dashboard</h1>';

		// Score card.
		printf(
			'<div class="si-score-card"><h2>GEO Health Score: <span class="grade-%s">%d/100 (%s)</span></h2></div>',
			esc_attr( strtolower( $health['grade'] ) ),
			absint( $health['score'] ),
			esc_html( $health['grade'] )
		);

		// Factors.
		echo '<h3>Score Breakdown</h3><table class="wp-list-table widefat striped"><thead><tr>';
		echo '<th>Factor</th><th>Score</th><th>Detail</th></tr></thead><tbody>';
		foreach ( $health['factors'] as $factor ) {
			printf(
				'<tr><td>%s</td><td>%s</td><td>%s</td></tr>',
				esc_html( $factor['label'] ),
				$factor['max'] > 0
					? esc_html( sprintf( '%d / %d', $factor['score'], $factor['max'] ) )
					: esc_html__( 'Optional', 'jat-search-intel' ),
				esc_html( $factor['detail'] )
			);
		}
		echo '</tbody></table>';

		// Recommendations.
		if ( ! empty( $recs ) ) {
			echo '<h3>Recommendations</h3><ul>';
			foreach ( $recs as $rec ) {
				printf(
					'<li><strong>[%s]</strong> %s</li>',
					esc_html( strtoupper( $rec['priority'] ) ),
					esc_html( $rec['message'] )
				);
			}
			echo '</ul>';
		}

		// AI Referral summary.
		printf( '<h3>AI Referral Traffic (30d)</h3><p>Total: %d visits</p>', absint( $ai_ref['total'] ) );
		if ( ! empty( $ai_ref['by_source'] ) ) {
			echo '<ul>';
			foreach ( $ai_ref['by_source'] as $src => $cnt ) {
				printf( '<li>%s: %d</li>', esc_html( $src ), absint( $cnt ) );
			}
			echo '</ul>';
		}

		// llms.txt access log.
		if ( ! empty( $log ) ) {
			echo '<h3>llms.txt Access Log</h3><table class="wp-list-table widefat striped"><thead><tr>';
			echo '<th>Time</th><th>Endpoint</th><th>Bot</th></tr></thead><tbody>';
			foreach ( $log as $entry ) {
				printf(
					'<tr><td>%s</td><td>%s</td><td>%s</td></tr>',
					esc_html( $entry['time'] ?? '' ),
					esc_html( $entry['endpoint'] ?? '' ),
					esc_html( $entry['bot'] ?? 'unknown' )
				);
			}
			echo '</tbody></table>';
		}

		// Content gaps.
		if ( ! empty( $gaps['gaps'] ) ) {
			echo '<h3>Content Gaps</h3><ul>';
			foreach ( array_slice( $gaps['gaps'], 0, 10 ) as $gap ) {
				printf(
					'<li><strong>[%s]</strong> %s</li>',
					esc_html( strtoupper( $gap['priority'] ) ),
					esc_html( $gap['message'] )
				);
			}
			echo '</ul>';
		}

		echo '</div>';
	}

	/**
	 * AJAX handler: refresh visibility data.
	 */
	public function ajax_refresh(): void {
		check_ajax_referer( 'si_visibility_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Unauthorized.' ] );
		}

		$this->record_daily_score();

		wp_send_json_success( [
			'health' => $this->calculate_health_score(),
			'trend'  => $this->get_visibility_trend( 30 ),
		] );
	}
}
