<?php
/**
 * Plugin Updater — GitHub-based self-hosted update mechanism.
 *
 * Hooks into WordPress's native plugin update system so the plugin
 * can be updated from the Dashboard → Updates screen, just like
 * plugins from the wp.org repo. Checks GitHub Releases for new
 * versions and serves the zip download URL to WordPress.
 *
 * Supports **private repositories** by injecting an auth token into
 * both the API check and the download request. The token can be set
 * in two ways (checked in order):
 *
 *   1. `SEARCH_INTEL_GITHUB_TOKEN` constant in wp-config.php (recommended)
 *   2. `github_token` key in the search_intel_settings option
 *
 * @package Search_Intel
 * @since   3.4.0
 */

namespace Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Plugin_Updater {

	/** @var string GitHub owner/repo. */
	private const GITHUB_REPO = 'CptNope/Search-Intel';

	/** @var string GitHub API base. */
	private const API_BASE = 'https://api.github.com/repos/' . self::GITHUB_REPO;

	/** @var string Transient key for caching the latest release. */
	private const CACHE_KEY = 'search_intel_github_release';

	/** @var int Cache TTL in seconds (6 hours). */
	private const CACHE_TTL = 6 * HOUR_IN_SECONDS;

	/** @var string Plugin basename (e.g. Search-Intel/search-intel.php). */
	private $basename;

	/** @var string Current installed version. */
	private $version;

	/** @var string GitHub personal access token (empty = public repo). */
	private $token;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->basename = SEARCH_INTEL_BASENAME;
		$this->version  = SEARCH_INTEL_VERSION;
		$this->token    = $this->resolve_token();

		// Bail silently if no token and repo is private — API will 404.
		// Update checks still register so the admin notice can warn.

		// Inject update info into the transient WordPress checks.
		add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_update' ] );

		// Supply plugin info for the "View details" modal.
		add_filter( 'plugins_api', [ $this, 'plugin_info' ], 20, 3 );

		// Inject auth header when WP downloads our zip from GitHub.
		add_filter( 'http_request_args', [ $this, 'inject_download_auth' ], 10, 2 );

		// Rename extracted folder to match installed plugin directory.
		add_filter( 'upgrader_post_install', [ $this, 'after_install' ], 10, 3 );

		// Admin-only hooks.
		if ( is_admin() ) {
			// Show admin notice when token is missing.
			if ( empty( $this->token ) ) {
				add_action( 'admin_notices', [ $this, 'missing_token_notice' ] );
			}

			// Force-check action — clears cache and redirects to updates page.
			add_action( 'admin_post_search_intel_force_update_check', [ $this, 'force_update_check' ] );
		}
	}

	/*----------------------------------------------------------------------
	 * Token resolution
	 *--------------------------------------------------------------------*/

	/**
	 * Resolve the GitHub token from constant or encrypted option.
	 *
	 * Delegates to Admin::get_github_token() which checks:
	 *   1. SEARCH_INTEL_GITHUB_TOKEN constant in wp-config.php
	 *   2. Encrypted option saved via the admin UI
	 *
	 * @return string Token or empty string.
	 */
	private function resolve_token(): string {
		return Admin::get_github_token();
	}

	/**
	 * Get the common HTTP headers for GitHub API requests.
	 *
	 * @return array Headers array.
	 */
	private function api_headers(): array {
		$headers = [
			'Accept'     => 'application/vnd.github.v3+json',
			'User-Agent' => 'Search-Intel-Updater/' . $this->version,
		];

		if ( $this->token ) {
			$headers['Authorization'] = 'Bearer ' . $this->token;
		}

		return $headers;
	}

	/*----------------------------------------------------------------------
	 * Update check
	 *--------------------------------------------------------------------*/

	/**
	 * Check GitHub for a newer release and inject into WP update transient.
	 *
	 * @param object $transient The update_plugins transient value.
	 * @return object Modified transient.
	 */
	public function check_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$release = $this->get_latest_release();
		if ( ! $release ) {
			return $transient;
		}

		$remote_version = ltrim( $release['tag_name'] ?? '', 'vV' );
		if ( ! $remote_version || version_compare( $this->version, $remote_version, '>=' ) ) {
			// No update available — add to no_update so WP knows we checked.
			$transient->no_update[ $this->basename ] = (object) [
				'id'            => $this->basename,
				'slug'          => dirname( $this->basename ),
				'plugin'        => $this->basename,
				'new_version'   => $this->version,
				'url'           => 'https://github.com/' . self::GITHUB_REPO,
				'package'       => '',
			];
			return $transient;
		}

		$download_url = $this->get_download_url( $release );
		if ( ! $download_url ) {
			return $transient;
		}

		$transient->response[ $this->basename ] = (object) [
			'id'            => $this->basename,
			'slug'          => dirname( $this->basename ),
			'plugin'        => $this->basename,
			'new_version'   => $remote_version,
			'url'           => $release['html_url'] ?? '',
			'package'       => $download_url,
			'icons'         => [],
			'banners'       => [],
			'requires'      => '6.0',
			'requires_php'  => '7.4',
			'tested'        => '',
			'compatibility' => new \stdClass(),
		];

		return $transient;
	}

	/*----------------------------------------------------------------------
	 * Plugin info modal
	 *--------------------------------------------------------------------*/

	/**
	 * Supply plugin information for the "View details" modal in the dashboard.
	 *
	 * @param false|object|array $result The result object.
	 * @param string             $action API action.
	 * @param object             $args   API arguments.
	 * @return false|object
	 */
	public function plugin_info( $result, $action, $args ) {
		if ( $action !== 'plugin_information' ) {
			return $result;
		}

		if ( ! isset( $args->slug ) || $args->slug !== dirname( $this->basename ) ) {
			return $result;
		}

		$release = $this->get_latest_release();
		if ( ! $release ) {
			return $result;
		}

		$remote_version = ltrim( $release['tag_name'] ?? '', 'vV' );

		return (object) [
			'name'            => 'Search Intel',
			'slug'            => dirname( $this->basename ),
			'version'         => $remote_version,
			'author'          => '<a href="https://searchintel.io">Search Intel</a>',
			'homepage'        => 'https://searchintel.io',
			'requires'        => '6.0',
			'requires_php'    => '7.4',
			'download_link'   => $this->get_download_url( $release ),
			'trunk'           => $this->get_download_url( $release ),
			'last_updated'    => $release['published_at'] ?? '',
			'sections'        => [
				'description' => 'SEO, AEO & GEO optimization for WordPress.',
				'changelog'   => nl2br( esc_html( $release['body'] ?? 'See GitHub for details.' ) ),
			],
		];
	}

	/*----------------------------------------------------------------------
	 * Download auth injection
	 *--------------------------------------------------------------------*/

	/**
	 * Inject the auth header when WordPress downloads a zip from GitHub.
	 *
	 * WP's upgrader uses wp_remote_get() without custom headers.
	 * For private repos the download URL (zipball or asset) returns
	 * 404 without authentication. This filter adds the Bearer token
	 * to any request going to api.github.com for our repo.
	 *
	 * @param array  $args HTTP request args.
	 * @param string $url  Request URL.
	 * @return array Modified args.
	 */
	public function inject_download_auth( $args, $url ) {
		if ( empty( $this->token ) ) {
			return $args;
		}

		// Only inject auth for GitHub API URLs related to our repo.
		if ( strpos( $url, 'api.github.com/repos/' . self::GITHUB_REPO ) === false
			&& strpos( $url, 'github.com/' . self::GITHUB_REPO ) === false
			&& strpos( $url, 'codeload.github.com/' . self::GITHUB_REPO ) === false
		) {
			return $args;
		}

		if ( ! isset( $args['headers'] ) || ! is_array( $args['headers'] ) ) {
			$args['headers'] = [];
		}

		$args['headers']['Authorization'] = 'Bearer ' . $this->token;

		// For GitHub API asset downloads, request the binary directly.
		if ( strpos( $url, '/releases/assets/' ) !== false ) {
			$args['headers']['Accept'] = 'application/octet-stream';
		}

		return $args;
	}

	/*----------------------------------------------------------------------
	 * Post-install folder rename
	 *--------------------------------------------------------------------*/

	/**
	 * After install, ensure the plugin directory name is correct.
	 *
	 * GitHub zips extract to "Search-Intel-<tag>" — this renames
	 * the folder to match the installed plugin directory so WP
	 * recognises it as the same plugin (preserving all data).
	 *
	 * @param bool  $response   Install response.
	 * @param array $hook_extra Extra hook data.
	 * @param array $result     Installation result.
	 * @return array Modified result.
	 */
	public function after_install( $response, $hook_extra, $result ) {
		// Only act on our plugin.
		if ( ! isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->basename ) {
			return $result;
		}

		global $wp_filesystem;

		$proper_dir = WP_PLUGIN_DIR . '/' . dirname( $this->basename );
		$installed  = $result['destination'] ?? '';

		// If the extracted folder name doesn't match, rename it.
		if ( $installed && rtrim( $installed, '/\\' ) !== rtrim( $proper_dir, '/\\' ) ) {
			$wp_filesystem->move( $installed, $proper_dir );
			$result['destination']        = $proper_dir;
			$result['destination_name']   = dirname( $this->basename );
			$result['remote_destination'] = $proper_dir;
		}

		// Re-activate if it was active before the update.
		activate_plugin( $this->basename );

		return $result;
	}

	/*----------------------------------------------------------------------
	 * GitHub API
	 *--------------------------------------------------------------------*/

	/**
	 * Fetch the latest GitHub release (cached).
	 *
	 * @return array|null Release data or null on failure.
	 */
	private function get_latest_release(): ?array {
		$cached = get_transient( self::CACHE_KEY );
		if ( is_array( $cached ) && ! empty( $cached ) ) {
			return $cached;
		}

		// Need a token for the private repo.
		if ( empty( $this->token ) ) {
			return null;
		}

		$url = self::API_BASE . '/releases/latest';

		$response = wp_remote_get( $url, [
			'timeout' => 10,
			'headers' => $this->api_headers(),
		] );

		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
			// Cache a failure for 30 minutes to avoid hammering GitHub.
			set_transient( self::CACHE_KEY, [], 30 * MINUTE_IN_SECONDS );
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || empty( $body['tag_name'] ) ) {
			return null;
		}

		set_transient( self::CACHE_KEY, $body, self::CACHE_TTL );

		return $body;
	}

	/**
	 * Get the zip download URL from a release.
	 *
	 * For private repos, we use the API asset URL (which our
	 * inject_download_auth filter will authenticate). For assets,
	 * this returns the API URL; for zipball, the authenticated
	 * zipball_url works via the same filter.
	 *
	 * @param array $release GitHub release data.
	 * @return string Download URL.
	 */
	private function get_download_url( array $release ): string {
		// Check for an attached .zip asset first — use the API URL.
		foreach ( $release['assets'] ?? [] as $asset ) {
			if ( str_ends_with( $asset['name'] ?? '', '.zip' ) ) {
				// Use the API URL so our auth header gets injected.
				return $asset['url'] ?? ( $asset['browser_download_url'] ?? '' );
			}
		}

		// Fall back to GitHub's auto-generated zipball (auth injected by filter).
		return $release['zipball_url'] ?? '';
	}

	/*----------------------------------------------------------------------
	 * Admin notice
	 *--------------------------------------------------------------------*/

	/**
	 * Show a notice when the GitHub token is not configured.
	 */
	public function missing_token_notice(): void {
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->id, [ 'plugins', 'update-core', 'dashboard' ], true ) ) {
			return;
		}

		echo '<div class="notice notice-warning"><p>';
		echo '<strong>Search Intel:</strong> ';
		echo esc_html__(
			'Automatic updates are disabled — no GitHub token configured. '
			. 'Add this to your wp-config.php to enable updates:',
			'search-intel'
		);
		echo ' <code>define( \'SEARCH_INTEL_GITHUB_TOKEN\', \'ghp_your_token_here\' );</code>';
		echo '</p></div>';
	}

	/*----------------------------------------------------------------------
	 * Force update check
	 *--------------------------------------------------------------------*/

	/**
	 * Handle the force-check admin action.
	 *
	 * Clears the cached release transient, deletes WP's plugin update
	 * transient to force a fresh check, and redirects to the updates page.
	 */
	public function force_update_check(): void {
		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'search-intel' ) );
		}

		check_admin_referer( 'search_intel_force_update_check' );

		// Clear our GitHub release cache.
		delete_transient( self::CACHE_KEY );

		// Clear WP's plugin update transient to force a fresh check.
		delete_site_transient( 'update_plugins' );

		// Redirect to the WordPress updates page.
		wp_safe_redirect( admin_url( 'update-core.php' ) );
		exit;
	}

	/**
	 * Get the URL for the force-check action (for use in admin UI).
	 *
	 * @return string Nonce-protected admin-post URL.
	 */
	public static function get_force_check_url(): string {
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=search_intel_force_update_check' ),
			'search_intel_force_update_check'
		);
	}
}
