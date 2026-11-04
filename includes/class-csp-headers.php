<?php
namespace Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CSP Headers — Content Security Policy for plugin endpoints.
 *
 * Adds Content-Security-Policy headers on Search Intel admin pages
 * and REST API endpoints to mitigate XSS and injection attacks.
 *
 * @since 2.0.1
 */
class CSP_Headers {

	/**
	 * Admin page slugs that belong to this plugin.
	 */
	private const PLUGIN_PAGES = [
		'search-intel',
		'search-intel-bulk',
		'search-intel-redirects',
		'search-intel-broken-links',
		'search-intel-robots-txt',
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

		if ( ! empty( $settings['enable_csp_headers'] ) ) {
			add_action( 'admin_init', [ $this, 'send_admin_csp' ] );
			add_filter( 'rest_pre_serve_request', [ $this, 'send_rest_csp' ], 10, 4 );
		}
	}

	/**
	 * Build the Content-Security-Policy header value.
	 *
	 * @param string $context Either 'admin' or 'rest'.
	 * @return string
	 */
	public function build_policy( string $context = 'admin' ): string {
		$directives = [
			"default-src 'self'",
			"script-src 'self' 'unsafe-inline'",
			"style-src 'self' 'unsafe-inline'",
			"img-src 'self' data: https:",
			"font-src 'self' data:",
			"connect-src 'self'",
			"frame-ancestors 'self'",
			"base-uri 'self'",
			"form-action 'self'",
		];

		if ( 'rest' === $context ) {
			// REST endpoints should be stricter.
			$directives = [
				"default-src 'none'",
				"frame-ancestors 'none'",
				"base-uri 'none'",
			];
		}

		return implode( '; ', $directives );
	}

	/**
	 * Send CSP header on plugin admin pages.
	 */
	public function send_admin_csp(): void {
		if ( ! $this->is_plugin_admin_page() ) {
			return;
		}

		$policy = $this->build_policy( 'admin' );

		if ( ! headers_sent() ) {
			header( 'Content-Security-Policy: ' . $policy );
			header( 'X-Content-Type-Options: nosniff' );
			header( 'X-Frame-Options: SAMEORIGIN' );
			header( 'Referrer-Policy: strict-origin-when-cross-origin' );
		}
	}

	/**
	 * Send CSP header on REST API responses for our namespace.
	 *
	 * @param bool   $served  Whether the request has been served.
	 * @param object $result  REST response.
	 * @param object $request REST request.
	 * @param object $server  REST server.
	 * @return bool
	 */
	public function send_rest_csp( bool $served, $result, $request, $server ): bool {
		$route = '';
		if ( is_object( $request ) && method_exists( $request, 'get_route' ) ) {
			$route = $request->get_route();
		}

		if ( 0 === strpos( $route, '/search-intel/' ) ) {
			$policy = $this->build_policy( 'rest' );

			if ( ! headers_sent() ) {
				header( 'Content-Security-Policy: ' . $policy );
				header( 'X-Content-Type-Options: nosniff' );
			}
		}

		return $served;
	}

	/**
	 * Check if the current admin page belongs to this plugin.
	 *
	 * @return bool
	 */
	public function is_plugin_admin_page(): bool {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view/pagination parameter.
		return in_array( $page, self::PLUGIN_PAGES, true );
	}
}
