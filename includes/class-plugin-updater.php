<?php
/**
 * Plugin Updater — GitHub-based self-hosted update mechanism.
 *
 * Hooks into WordPress's native plugin update system so the plugin
 * can be updated from the Dashboard → Updates screen, just like
 * plugins from the wp.org repo. Checks GitHub Releases for new
 * versions and serves the zip download URL to WordPress.
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

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->basename = SEARCH_INTEL_BASENAME;
		$this->version  = SEARCH_INTEL_VERSION;

		// Inject update info into the transient WordPress checks.
		add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_update' ] );

		// Supply plugin info for the "View details" modal.
		add_filter( 'plugins_api', [ $this, 'plugin_info' ], 20, 3 );

		// Point the download to the GitHub zip after update is confirmed.
		add_filter( 'upgrader_post_install', [ $this, 'after_install' ], 10, 3 );
	}

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

	/**
	 * After install, ensure the plugin directory name is correct.
	 *
	 * GitHub zips extract to "Search-Intel-<branch>" — this renames
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
		if ( $installed && $installed !== $proper_dir ) {
			$wp_filesystem->move( $installed, $proper_dir );
			$result['destination']      = $proper_dir;
			$result['destination_name'] = dirname( $this->basename );
			$result['remote_destination'] = $proper_dir;
		}

		// Re-activate if it was active before the update.
		activate_plugin( $this->basename );

		return $result;
	}

	/**
	 * Fetch the latest GitHub release (cached).
	 *
	 * @return array|null Release data or null on failure.
	 */
	private function get_latest_release(): ?array {
		$cached = get_transient( self::CACHE_KEY );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$url = self::API_BASE . '/releases/latest';

		$args = [
			'timeout' => 10,
			'headers' => [
				'Accept'     => 'application/vnd.github.v3+json',
				'User-Agent' => 'Search-Intel-Updater/' . $this->version,
			],
		];

		// Use a GitHub token if available (for private repos or higher rate limits).
		$settings = get_option( 'search_intel_settings', [] );
		$token    = $settings['github_token'] ?? '';
		if ( $token ) {
			$args['headers']['Authorization'] = 'Bearer ' . $token;
		}

		$response = wp_remote_get( $url, $args );

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
	 * Prefers a .zip asset attached to the release. Falls back to the
	 * auto-generated GitHub source zipball.
	 *
	 * @param array $release GitHub release data.
	 * @return string Download URL.
	 */
	private function get_download_url( array $release ): string {
		// Check for an attached .zip asset first.
		foreach ( $release['assets'] ?? [] as $asset ) {
			if ( str_ends_with( $asset['name'] ?? '', '.zip' ) ) {
				return $asset['browser_download_url'] ?? '';
			}
		}

		// Fall back to GitHub's auto-generated zipball.
		return $release['zipball_url'] ?? '';
	}
}
