<?php
/**
 * AI search (GEO) features:
 * - Markdown version of every public post for AI agents (`/post-slug.md` or `?format=md`).
 * - Per-crawler allow/block rules written to robots.txt (training vs search vs user fetch).
 * - AI crawler visit log and AI-assistant referral analytics (ChatGPT, Perplexity, Claude, Gemini…).
 *
 * @package JAT_Search_Intel
 */

namespace JAT_Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AI search visibility controls and analytics.
 */
class AI_Search {

	/**
	 * Known AI user-agent tokens: token => [ operator, purpose ].
	 * Purpose: training | search | user | signal (robots.txt-only token).
	 */
	public const BOTS = [
		'GPTBot'                => [ 'OpenAI', 'training' ],
		'OAI-SearchBot'         => [ 'OpenAI', 'search' ],
		'ChatGPT-User'          => [ 'OpenAI', 'user' ],
		'ClaudeBot'             => [ 'Anthropic', 'training' ],
		'Claude-SearchBot'      => [ 'Anthropic', 'search' ],
		'Claude-User'           => [ 'Anthropic', 'user' ],
		'PerplexityBot'         => [ 'Perplexity', 'search' ],
		'Perplexity-User'       => [ 'Perplexity', 'user' ],
		'Google-Extended'       => [ 'Google', 'signal' ],
		'GoogleOther'           => [ 'Google', 'training' ],
		'Applebot-Extended'     => [ 'Apple', 'signal' ],
		'meta-externalagent'    => [ 'Meta', 'training' ],
		'meta-webindexer'       => [ 'Meta', 'search' ],
		'meta-externalfetcher'  => [ 'Meta', 'user' ],
		'Amazonbot'             => [ 'Amazon', 'training' ],
		'Amzn-SearchBot'        => [ 'Amazon', 'search' ],
		'Amzn-User'             => [ 'Amazon', 'user' ],
		'MistralAI-User'        => [ 'Mistral', 'user' ],
		'DuckAssistBot'         => [ 'DuckDuckGo', 'search' ],
		'Bytespider'            => [ 'ByteDance', 'training' ],
		'CCBot'                 => [ 'Common Crawl', 'training' ],
	];

	/**
	 * AI assistants that send visitors: referrer host fragment => label.
	 */
	public const REFERRERS = [
		'chatgpt.com'           => 'ChatGPT',
		'chat.openai.com'       => 'ChatGPT',
		'perplexity.ai'         => 'Perplexity',
		'claude.ai'             => 'Claude',
		'gemini.google.com'     => 'Gemini',
		'copilot.microsoft.com' => 'Copilot',
		'chat.deepseek.com'     => 'DeepSeek',
		'meta.ai'               => 'Meta AI',
		'grok.com'              => 'Grok',
		'chat.mistral.ai'       => 'Mistral',
		'you.com'               => 'You.com',
		'phind.com'             => 'Phind',
	];

	public const TRAFFIC_OPTION = 'jat_search_intel_ai_traffic';

	private const RETENTION_DAYS = 90;
	private const MAX_PAGES      = 200;

	/** @var array<string,mixed> */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param array<string,mixed> $settings Plugin settings.
	 */
	public function __construct( array $settings ) {
		$this->settings = $settings;

		if ( $this->get_blocked_bots() ) {
			add_filter( 'robots_txt', [ $this, 'filter_robots_txt' ], 1000, 2 );
		}

		if ( ! empty( $settings['enable_markdown_endpoints'] ) ) {
			add_action( 'template_redirect', [ $this, 'maybe_serve_markdown' ], 0 );
			add_action( 'wp_head', [ $this, 'markdown_alternate_link' ], 2 );
		}

		if ( ! empty( $settings['enable_ai_crawler_log'] ) ) {
			add_action( 'template_redirect', [ $this, 'track_request' ], -10 );
		}

		if ( is_admin() ) {
			add_action( 'admin_menu', [ $this, 'register_admin_page' ], 30 );
		}
	}

	// ── Crawler access rules ────────────────────────────────────────────

	/**
	 * Tokens set to "block".
	 *
	 * @return string[]
	 */
	public function get_blocked_bots(): array {
		$rules = (array) ( $this->settings['ai_crawler_rules'] ?? [] );
		return array_keys( array_filter( $rules, static function ( $rule ) {
			return 'block' === $rule;
		} ) );
	}

	/**
	 * Append Disallow groups for blocked AI crawlers.
	 *
	 * @param string $output robots.txt content.
	 * @param mixed  $public Whether the site is public.
	 * @return string
	 */
	public function filter_robots_txt( $output, $public ): string {
		$output = (string) $output;
		if ( ! $public ) {
			return $output;
		}

		$blocked = $this->get_blocked_bots();
		if ( ! $blocked ) {
			return $output;
		}

		$output = rtrim( $output ) . "\n\n# AI crawler rules (JAT Search Intel)\n";
		foreach ( $blocked as $bot ) {
			$output .= 'User-agent: ' . $bot . "\nDisallow: /\n\n";
		}
		return $output;
	}

	// ── Markdown for AI agents ──────────────────────────────────────────

	/**
	 * Public Markdown URL for a post.
	 *
	 * @param \WP_Post|int $post Post.
	 * @return string
	 */
	public static function markdown_url( $post ): string {
		$post = get_post( $post );
		if ( ! $post ) {
			return '';
		}
		$permalink = (string) get_permalink( $post );
		$is_front  = 'page' === get_option( 'show_on_front' ) && (int) get_option( 'page_on_front' ) === (int) $post->ID;

		if ( $is_front || ! get_option( 'permalink_structure' ) || false !== strpos( $permalink, '?' ) ) {
			return add_query_arg( 'format', 'md', $permalink );
		}
		return untrailingslashit( $permalink ) . '.md';
	}

	/**
	 * Whether a post may be exposed as Markdown.
	 *
	 * @param \WP_Post $post Post.
	 * @return bool
	 */
	private function is_exposable( \WP_Post $post ): bool {
		if ( 'publish' !== $post->post_status || '' !== $post->post_password ) {
			return false;
		}
		if ( ! in_array( $post->post_type, (array) ( $this->settings['enabled_post_types'] ?? [ 'post', 'page' ] ), true ) ) {
			return false;
		}
		return ! get_post_meta( $post->ID, '_jat_search_intel_noindex', true )
			&& ! get_post_meta( $post->ID, '_jat_search_intel_ai_noindex', true );
	}

	/**
	 * `<link rel="alternate" type="text/markdown">` on singular pages.
	 */
	public function markdown_alternate_link(): void {
		if ( ! is_singular() ) {
			return;
		}
		$post = get_post( get_queried_object_id() );
		if ( $post && $this->is_exposable( $post ) ) {
			printf( '<link rel="alternate" type="text/markdown" href="%s" />' . "\n", esc_url( self::markdown_url( $post ) ) );
		}
	}

	/**
	 * Path of the current request (no query string).
	 *
	 * @return string
	 */
	private static function request_path(): string {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		return (string) wp_parse_url( $uri, PHP_URL_PATH );
	}

	/**
	 * Resolve the post a Markdown request is for, if any.
	 *
	 * @return \WP_Post|null
	 */
	public function resolve_markdown_request(): ?\WP_Post {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public read-only format switch.
		$format = isset( $_GET['format'] ) ? sanitize_key( wp_unslash( $_GET['format'] ) ) : '';

		if ( in_array( $format, [ 'md', 'markdown' ], true ) && is_singular() ) {
			$post = get_post( get_queried_object_id() );
			return $post instanceof \WP_Post ? $post : null;
		}

		$path = self::request_path();
		if ( is_404() && '.md' === substr( $path, -3 ) ) {
			$home_path = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
			$relative  = substr( $path, strlen( rtrim( $home_path, '/' ) ) );
			$base      = home_url( substr( $relative, 0, -3 ) );
			$post_id   = url_to_postid( $base ) ?: url_to_postid( trailingslashit( $base ) );
			$post      = $post_id ? get_post( $post_id ) : null;
			return $post instanceof \WP_Post ? $post : null;
		}

		return null;
	}

	/**
	 * Serve the Markdown representation and stop.
	 */
	public function maybe_serve_markdown(): void {
		$post = $this->resolve_markdown_request();
		if ( ! $post || ! $this->is_exposable( $post ) ) {
			return;
		}

		status_header( 200 );
		nocache_headers();
		header( 'Content-Type: text/markdown; charset=utf-8' );
		header( 'X-Robots-Tag: noindex, follow' );
		header( 'Link: <' . esc_url_raw( (string) get_permalink( $post ) ) . '>; rel="canonical"' );

		echo $this->render_markdown( $post ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- text/markdown response built from sanitized content.
		exit;
	}

	/**
	 * Markdown document for a post.
	 *
	 * @param \WP_Post $post Post.
	 * @return string
	 */
	public function render_markdown( \WP_Post $post ): string {
		$GLOBALS['post'] = $post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Needed so the_content filters render blocks for this post.
		setup_postdata( $post );

		$title       = wp_strip_all_tags( get_the_title( $post ) );
		$description = (string) get_post_meta( $post->ID, '_jat_search_intel_ai_summary', true );
		if ( '' === $description ) {
			$description = (string) get_post_meta( $post->ID, '_jat_search_intel_description', true );
		}
		$author = (string) get_the_author_meta( 'display_name', (int) $post->post_author );

		$md  = '# ' . $title . "\n\n";
		if ( '' !== $description ) {
			$md .= '> ' . wp_strip_all_tags( $description ) . "\n\n";
		}
		$md .= '- URL: ' . get_permalink( $post ) . "\n";
		if ( $author ) {
			$md .= '- Author: ' . $author . "\n";
		}
		$md .= '- Published: ' . get_the_date( 'Y-m-d', $post ) . "\n";
		$md .= '- Updated: ' . get_the_modified_date( 'Y-m-d', $post ) . "\n\n";

		$content = apply_filters( 'the_content', $post->post_content ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core filter.
		$md     .= Markdown::from_html( (string) $content );

		$faqs = get_post_meta( $post->ID, '_jat_search_intel_faqs', true );
		if ( is_array( $faqs ) && $faqs ) {
			$md .= "\n## FAQ\n\n";
			foreach ( $faqs as $faq ) {
				if ( ! empty( $faq['question'] ) && ! empty( $faq['answer'] ) ) {
					$md .= '### ' . wp_strip_all_tags( $faq['question'] ) . "\n\n" . trim( Markdown::from_html( (string) $faq['answer'] ) ) . "\n\n";
				}
			}
		}

		wp_reset_postdata();

		/**
		 * Filters the Markdown served to AI agents for a post.
		 *
		 * @param string   $md   Markdown.
		 * @param \WP_Post $post Post.
		 */
		return (string) apply_filters( 'jat_search_intel_markdown', $md, $post );
	}

	// ── AI traffic analytics ────────────────────────────────────────────

	/**
	 * Identify an AI crawler from a user agent (robots.txt-only tokens excluded).
	 *
	 * @param string $user_agent User agent.
	 * @return string Token, or '' when not an AI crawler.
	 */
	public static function match_bot( string $user_agent ): string {
		if ( '' === $user_agent ) {
			return '';
		}
		foreach ( self::BOTS as $token => $info ) {
			if ( 'signal' !== $info[1] && false !== stripos( $user_agent, $token ) ) {
				return $token;
			}
		}
		return '';
	}

	/**
	 * Identify an AI assistant from the referrer or utm_source.
	 *
	 * @param string $referrer   HTTP referrer.
	 * @param string $utm_source utm_source query value.
	 * @return string Label, or ''.
	 */
	public static function match_referrer( string $referrer, string $utm_source = '' ): string {
		$host = strtolower( (string) wp_parse_url( $referrer, PHP_URL_HOST ) );
		$utm  = strtolower( $utm_source );

		foreach ( self::REFERRERS as $domain => $label ) {
			if ( ( '' !== $host && ( $host === $domain || substr( $host, -strlen( '.' . $domain ) ) === '.' . $domain ) )
				|| ( '' !== $utm && false !== strpos( $utm, $domain ) ) ) {
				return $label;
			}
		}
		if ( '' !== $utm ) {
			foreach ( self::REFERRERS as $label ) {
				if ( strtolower( $label ) === $utm ) {
					return $label;
				}
			}
		}
		return '';
	}

	/**
	 * Record AI crawler visits and AI-assistant referrals for front-end requests.
	 */
	public function track_request(): void {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		$path = substr( self::request_path(), 0, 200 );
		$ua   = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

		$bot = self::match_bot( $ua );
		if ( '' !== $bot ) {
			self::record( 'crawlers', $bot, $path );
			return;
		}

		if ( is_user_logged_in() ) {
			return;
		}

		$referrer = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only analytics parameter.
		$utm    = isset( $_GET['utm_source'] ) ? sanitize_text_field( wp_unslash( $_GET['utm_source'] ) ) : '';
		$source = self::match_referrer( $referrer, $utm );
		if ( '' !== $source ) {
			self::record( 'referrals', $source, $path );
		}
	}

	/**
	 * Increment daily and per-page counters.
	 *
	 * @param string $kind  crawlers|referrals.
	 * @param string $label Bot token or assistant label.
	 * @param string $path  Request path.
	 */
	public static function record( string $kind, string $label, string $path ): void {
		$data = get_option( self::TRAFFIC_OPTION, [] );
		$data = is_array( $data ) ? $data : [];
		$day  = gmdate( 'Y-m-d' );

		$data[ $kind ]['days'][ $day ][ $label ] = ( $data[ $kind ]['days'][ $day ][ $label ] ?? 0 ) + 1;
		$data[ $kind ]['pages'][ $label ][ $path ] = ( $data[ $kind ]['pages'][ $label ][ $path ] ?? 0 ) + 1;

		// Retention.
		$cutoff = gmdate( 'Y-m-d', time() - self::RETENTION_DAYS * DAY_IN_SECONDS );
		foreach ( array_keys( $data[ $kind ]['days'] ) as $d ) {
			if ( $d < $cutoff ) {
				unset( $data[ $kind ]['days'][ $d ] );
			}
		}
		if ( count( $data[ $kind ]['pages'][ $label ] ) > self::MAX_PAGES ) {
			arsort( $data[ $kind ]['pages'][ $label ] );
			$data[ $kind ]['pages'][ $label ] = array_slice( $data[ $kind ]['pages'][ $label ], 0, (int) ( self::MAX_PAGES * 0.75 ), true );
		}

		update_option( self::TRAFFIC_OPTION, $data, false );
	}

	/**
	 * Totals over the last N days.
	 *
	 * @param string $kind crawlers|referrals.
	 * @param int    $days Lookback.
	 * @return array{total:int, by_label:array<string,int>, by_day:array<string,int>, pages:array<string,array<string,int>>}
	 */
	public static function summary( string $kind, int $days = 30 ): array {
		$data   = get_option( self::TRAFFIC_OPTION, [] );
		$data   = is_array( $data ) ? $data : [];
		$cutoff = gmdate( 'Y-m-d', time() - $days * DAY_IN_SECONDS );
		$out    = [
			'total'    => 0,
			'by_label' => [],
			'by_day'   => [],
			'pages'    => (array) ( $data[ $kind ]['pages'] ?? [] ),
		];

		foreach ( (array) ( $data[ $kind ]['days'] ?? [] ) as $day => $labels ) {
			if ( $day < $cutoff ) {
				continue;
			}
			foreach ( (array) $labels as $label => $count ) {
				$out['total']               += (int) $count;
				$out['by_label'][ $label ]   = ( $out['by_label'][ $label ] ?? 0 ) + (int) $count;
				$out['by_day'][ $day ]       = ( $out['by_day'][ $day ] ?? 0 ) + (int) $count;
			}
		}
		arsort( $out['by_label'] );
		ksort( $out['by_day'] );
		foreach ( $out['pages'] as $label => $pages ) {
			arsort( $pages );
			$out['pages'][ $label ] = array_slice( (array) $pages, 0, 10, true );
		}

		return $out;
	}

	// ── Admin report ────────────────────────────────────────────────────

	/**
	 * Register the AI Search report page.
	 */
	public function register_admin_page(): void {
		add_submenu_page(
			'jat-search-intel',
			__( 'AI Search', 'jat-search-intel' ),
			__( 'AI Search', 'jat-search-intel' ),
			'manage_options',
			'jat-search-intel-ai-search',
			[ $this, 'render_admin_page' ]
		);
	}

	/**
	 * Purpose label.
	 *
	 * @param string $purpose Purpose key.
	 * @return string
	 */
	public static function purpose_label( string $purpose ): string {
		switch ( $purpose ) {
			case 'training':
				return __( 'Model training', 'jat-search-intel' );
			case 'search':
				return __( 'AI search index', 'jat-search-intel' );
			case 'user':
				return __( 'Live fetch for a user', 'jat-search-intel' );
			default:
				return __( 'robots.txt signal only', 'jat-search-intel' );
		}
	}

	/**
	 * Render the report.
	 */
	public function render_admin_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$crawlers  = self::summary( 'crawlers' );
		$referrals = self::summary( 'referrals' );
		$rules     = (array) ( $this->settings['ai_crawler_rules'] ?? [] );
		$geo_url   = admin_url( 'admin.php?page=jat-search-intel-geo' );

		echo '<div class="wrap"><h1>' . esc_html__( 'AI Search', 'jat-search-intel' ) . '</h1>';

		if ( empty( $this->settings['enable_ai_crawler_log'] ) ) {
			echo '<div class="notice notice-info inline"><p>' . esc_html__( 'AI traffic analytics are off. Turn them on under GEO settings to record AI crawler visits and visitors sent by AI assistants.', 'jat-search-intel' )
				. ' <a href="' . esc_url( $geo_url ) . '">' . esc_html__( 'Open GEO settings', 'jat-search-intel' ) . '</a></p></div>';
		}

		// Referrals.
		echo '<h2>' . esc_html__( 'Visitors from AI assistants (30 days)', 'jat-search-intel' ) . '</h2>';
		/* translators: %s: number of visits. */
		echo '<p>' . esc_html( sprintf( __( '%s visits', 'jat-search-intel' ), number_format_i18n( $referrals['total'] ) ) ) . '</p>';
		$this->render_label_table( $referrals, __( 'Assistant', 'jat-search-intel' ) );

		// Crawlers.
		echo '<h2>' . esc_html__( 'AI crawler visits (30 days)', 'jat-search-intel' ) . '</h2>';
		echo '<table class="widefat striped" style="max-width:900px"><thead><tr><th>' . esc_html__( 'Crawler', 'jat-search-intel' ) . '</th><th>' . esc_html__( 'Operator', 'jat-search-intel' ) . '</th><th>' . esc_html__( 'Purpose', 'jat-search-intel' ) . '</th><th>' . esc_html__( 'Access', 'jat-search-intel' ) . '</th><th>' . esc_html__( 'Visits', 'jat-search-intel' ) . '</th><th>' . esc_html__( 'Top pages', 'jat-search-intel' ) . '</th></tr></thead><tbody>';
		foreach ( self::BOTS as $token => $info ) {
			$pages = array_keys( $crawlers['pages'][ $token ] ?? [] );
			echo '<tr><td><code>' . esc_html( $token ) . '</code></td><td>' . esc_html( $info[0] ) . '</td><td>' . esc_html( self::purpose_label( $info[1] ) ) . '</td><td>'
				. ( 'block' === ( $rules[ $token ] ?? 'allow' ) ? '<strong>' . esc_html__( 'Blocked', 'jat-search-intel' ) . '</strong>' : esc_html__( 'Allowed', 'jat-search-intel' ) )
				. '</td><td>' . esc_html( number_format_i18n( (int) ( $crawlers['by_label'][ $token ] ?? 0 ) ) ) . '</td><td>' . esc_html( implode( ', ', array_slice( $pages, 0, 3 ) ) ) . '</td></tr>';
		}
		echo '</tbody></table>';
		echo '<p><a class="button" href="' . esc_url( $geo_url ) . '">' . esc_html__( 'Change crawler access', 'jat-search-intel' ) . '</a></p>';

		echo '<p class="description">' . esc_html__( 'Counts come from requests that reach WordPress. Visits answered by a full-page cache or CDN without touching WordPress are not counted.', 'jat-search-intel' ) . '</p>';
		echo '</div>';
	}

	/**
	 * Table of counts by label with top pages.
	 *
	 * @param array  $summary Summary from summary().
	 * @param string $heading First column heading.
	 */
	private function render_label_table( array $summary, string $heading ): void {
		if ( empty( $summary['by_label'] ) ) {
			echo '<p><em>' . esc_html__( 'No data yet.', 'jat-search-intel' ) . '</em></p>';
			return;
		}
		echo '<table class="widefat striped" style="max-width:900px"><thead><tr><th>' . esc_html( $heading ) . '</th><th>' . esc_html__( 'Visits', 'jat-search-intel' ) . '</th><th>' . esc_html__( 'Top landing pages', 'jat-search-intel' ) . '</th></tr></thead><tbody>';
		foreach ( $summary['by_label'] as $label => $count ) {
			$pages = array_keys( $summary['pages'][ $label ] ?? [] );
			echo '<tr><td>' . esc_html( $label ) . '</td><td>' . esc_html( number_format_i18n( (int) $count ) ) . '</td><td>' . esc_html( implode( ', ', array_slice( $pages, 0, 5 ) ) ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}
}
