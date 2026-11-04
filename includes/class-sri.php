<?php
namespace JAT_Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SRI — Subresource Integrity for admin assets.
 *
 * Generates SHA-256/384 integrity hashes for plugin CSS and JS files
 * and adds integrity + crossorigin attributes to script/style tags.
 *
 * @since 2.0.1
 */
class SRI {

	/**
	 * Option key for cached integrity hashes.
	 */
	private const OPTION_KEY = 'jat_search_intel_sri_hashes';

	/**
	 * Hash algorithm.
	 */
	private const ALGO = 'sha256';

	/** @var array */
	private $settings;

	/** @var array Cached hashes. */
	private $hashes = [];

	/**
	 * Constructor.
	 *
	 * @param array $settings Plugin settings.
	 */
	public function __construct( array $settings ) {
		$this->settings = $settings;

		if ( ! empty( $settings['enable_sri'] ) ) {
			$this->hashes = get_option( self::OPTION_KEY, [] );

			add_filter( 'script_loader_tag', [ $this, 'add_script_integrity' ], 10, 3 );
			add_filter( 'style_loader_tag', [ $this, 'add_style_integrity' ], 10, 4 );
		}
	}

	/**
	 * Generate an SRI hash for a file.
	 *
	 * @param string $file_path Absolute file path.
	 * @return string SRI hash string (e.g. "sha256-abc123...") or empty on failure.
	 */
	public function generate_hash( string $file_path ): string {
		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			return '';
		}

		$content = file_get_contents( $file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Hashing a local plugin asset file.
		if ( false === $content ) {
			return '';
		}

		$hash = hash( self::ALGO, $content, true );
		return self::ALGO . '-' . base64_encode( $hash ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- SRI hashes are base64 by specification.
	}

	/**
	 * Get or compute the integrity hash for a plugin asset.
	 *
	 * @param string $handle  Script/style handle.
	 * @param string $src     Source URL.
	 * @return string SRI hash or empty string.
	 */
	public function get_hash( string $handle, string $src ): string {
		// Only process our own assets.
		if ( ! $this->is_plugin_asset( $src ) ) {
			return '';
		}

		// Check cache.
		if ( isset( $this->hashes[ $handle ] ) ) {
			return $this->hashes[ $handle ];
		}

		// Resolve URL to file path.
		$file_path = $this->url_to_path( $src );
		if ( ! $file_path ) {
			return '';
		}

		$hash = $this->generate_hash( $file_path );
		if ( $hash ) {
			$this->hashes[ $handle ] = $hash;
			update_option( self::OPTION_KEY, $this->hashes, false );
		}

		return $hash;
	}

	/**
	 * Add integrity attribute to script tags for plugin scripts.
	 *
	 * @param string $tag    Script HTML tag.
	 * @param string $handle Script handle.
	 * @param string $src    Script source URL.
	 * @return string Modified tag.
	 */
	public function add_script_integrity( string $tag, string $handle, string $src ): string {
		$hash = $this->get_hash( $handle, $src );
		if ( ! $hash ) {
			return $tag;
		}

		// Don't add if already present.
		if ( false !== strpos( $tag, 'integrity=' ) ) {
			return $tag;
		}

		return str_replace(
			' src=',
			' integrity="' . esc_attr( $hash ) . '" crossorigin="anonymous" src=',
			$tag
		);
	}

	/**
	 * Add integrity attribute to style tags for plugin styles.
	 *
	 * @param string $tag    Style HTML tag.
	 * @param string $handle Style handle.
	 * @param string $href   Stylesheet URL.
	 * @param string $media  Media attribute.
	 * @return string Modified tag.
	 */
	public function add_style_integrity( string $tag, string $handle, string $href, string $media ): string {
		$hash = $this->get_hash( $handle, $href );
		if ( ! $hash ) {
			return $tag;
		}

		if ( false !== strpos( $tag, 'integrity=' ) ) {
			return $tag;
		}

		return str_replace(
			' href=',
			' integrity="' . esc_attr( $hash ) . '" crossorigin="anonymous" href=',
			$tag
		);
	}

	/**
	 * Check if a URL belongs to this plugin.
	 *
	 * @param string $src Asset URL.
	 * @return bool
	 */
	public function is_plugin_asset( string $src ): bool {
		return false !== strpos( $src, 'jat-search-intel' );
	}

	/**
	 * Convert a plugin asset URL to its absolute file path.
	 *
	 * @param string $src Asset URL.
	 * @return string Absolute file path, or empty string.
	 */
	public function url_to_path( string $src ): string {
		// Strip query strings.
		$src = strtok( $src, '?' );

		$plugin_url = defined( 'JAT_SEARCH_INTEL_URL' ) ? JAT_SEARCH_INTEL_URL : '';
		$plugin_dir = defined( 'JAT_SEARCH_INTEL_DIR' ) ? JAT_SEARCH_INTEL_DIR : '';

		if ( ! $plugin_url || ! $plugin_dir ) {
			return '';
		}

		if ( 0 === strpos( $src, $plugin_url ) ) {
			$relative = substr( $src, strlen( $plugin_url ) );
			return $plugin_dir . $relative;
		}

		return '';
	}

	/**
	 * Regenerate all hashes (e.g. after plugin update).
	 */
	public function regenerate_hashes(): void {
		delete_option( self::OPTION_KEY );
		$this->hashes = [];
	}
}
