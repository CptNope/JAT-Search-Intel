<?php
namespace Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * White-label support for Search Intel.
 *
 * Allows customizing the plugin name, branding (logo, colors),
 * removing "Search Intel" references, and reseller support.
 *
 * @since 3.0.0
 */
class White_Label {

	/** @var array */
	private $settings;

	/**
	 * @param array $settings Plugin settings.
	 */
	public function __construct( array $settings ) {
		$this->settings = $settings;

		if ( empty( $settings['enable_white_label'] ) ) {
			return;
		}

		add_filter( 'all_plugins', [ $this, 'filter_plugin_meta' ] );
		add_filter( 'search_intel_plugin_name', [ $this, 'get_custom_name' ] );
		add_filter( 'search_intel_brand_color', [ $this, 'get_brand_color' ] );
		add_filter( 'search_intel_brand_logo', [ $this, 'get_brand_logo' ] );
		add_action( 'admin_head', [ $this, 'inject_brand_css' ] );
	}

	// ── Configuration ────────────────────────────────────────────────────────

	/**
	 * Get the white-label configuration.
	 *
	 * @return array
	 */
	public function get_config(): array {
		return [
			'enabled'      => ! empty( $this->settings['enable_white_label'] ),
			'plugin_name'  => $this->settings['wl_plugin_name'] ?? 'Search Intel',
			'plugin_uri'   => $this->settings['wl_plugin_uri'] ?? '',
			'author_name'  => $this->settings['wl_author_name'] ?? '',
			'author_uri'   => $this->settings['wl_author_uri'] ?? '',
			'description'  => $this->settings['wl_description'] ?? '',
			'logo_url'     => $this->settings['wl_logo_url'] ?? '',
			'brand_color'  => $this->settings['wl_brand_color'] ?? '#2271b1',
			'hide_branding' => ! empty( $this->settings['wl_hide_branding'] ),
		];
	}

	/**
	 * Save white-label configuration.
	 *
	 * @param array $config Configuration array.
	 * @return bool
	 */
	public function save_config( array $config ): bool {
		$settings = get_option( 'search_intel_settings', [] );
		$settings = is_array( $settings ) ? $settings : [];

		$fields = [
			'wl_plugin_name'  => 'sanitize_text_field',
			'wl_plugin_uri'   => 'esc_url_raw',
			'wl_author_name'  => 'sanitize_text_field',
			'wl_author_uri'   => 'esc_url_raw',
			'wl_description'  => 'sanitize_text_field',
			'wl_logo_url'     => 'esc_url_raw',
			'wl_brand_color'  => 'sanitize_hex_color',
			'wl_hide_branding' => 'boolval',
		];

		foreach ( $fields as $key => $sanitizer ) {
			if ( isset( $config[ $key ] ) ) {
				if ( $sanitizer === 'boolval' ) {
					$settings[ $key ] = (bool) $config[ $key ];
				} else {
					$settings[ $key ] = call_user_func( $sanitizer, $config[ $key ] );
				}
			}
		}

		return update_option( 'search_intel_settings', $settings );
	}

	// ── Plugin Meta Filter ───────────────────────────────────────────────────

	/**
	 * Filter the plugin list to replace Search Intel branding.
	 *
	 * @param array $plugins All plugins data.
	 * @return array
	 */
	public function filter_plugin_meta( array $plugins ): array {
		$basename = defined( 'SEARCH_INTEL_BASENAME' ) ? SEARCH_INTEL_BASENAME : 'search-intel/search-intel.php';

		if ( ! isset( $plugins[ $basename ] ) ) {
			return $plugins;
		}

		$config = $this->get_config();

		if ( ! empty( $config['plugin_name'] ) ) {
			$plugins[ $basename ]['Name']  = $config['plugin_name'];
			$plugins[ $basename ]['Title'] = $config['plugin_name'];
		}

		if ( ! empty( $config['description'] ) ) {
			$plugins[ $basename ]['Description'] = $config['description'];
		}

		if ( ! empty( $config['author_name'] ) ) {
			$plugins[ $basename ]['Author']     = $config['author_name'];
			$plugins[ $basename ]['AuthorName'] = $config['author_name'];
		}

		if ( ! empty( $config['author_uri'] ) ) {
			$plugins[ $basename ]['AuthorURI'] = $config['author_uri'];
		}

		if ( ! empty( $config['plugin_uri'] ) ) {
			$plugins[ $basename ]['PluginURI'] = $config['plugin_uri'];
		}

		return $plugins;
	}

	// ── Brand Filters ────────────────────────────────────────────────────────

	/**
	 * Get the custom plugin name.
	 *
	 * @param string $name Default name.
	 * @return string
	 */
	public function get_custom_name( string $name = '' ): string {
		$custom = $this->settings['wl_plugin_name'] ?? '';
		return ! empty( $custom ) ? $custom : $name;
	}

	/**
	 * Get the brand color.
	 *
	 * @param string $color Default color.
	 * @return string
	 */
	public function get_brand_color( string $color = '' ): string {
		$custom = $this->settings['wl_brand_color'] ?? '';
		return ! empty( $custom ) ? $custom : $color;
	}

	/**
	 * Get the brand logo URL.
	 *
	 * @param string $logo Default logo URL.
	 * @return string
	 */
	public function get_brand_logo( string $logo = '' ): string {
		$custom = $this->settings['wl_logo_url'] ?? '';
		return ! empty( $custom ) ? $custom : $logo;
	}

	// ── CSS Injection ────────────────────────────────────────────────────────

	/**
	 * Inject brand CSS into admin pages.
	 */
	public function inject_brand_css(): void {
		$screen = get_current_screen();
		if ( ! $screen || strpos( $screen->id, 'search-intel' ) === false ) {
			return;
		}

		$color = $this->settings['wl_brand_color'] ?? '#2271b1';
		$logo  = $this->settings['wl_logo_url'] ?? '';

		echo '<style>';
		printf(
			'.search-intel-brand-color { color: %s; } .search-intel-brand-bg { background-color: %s; }',
			esc_attr( $color ),
			esc_attr( $color )
		);

		if ( ! empty( $logo ) ) {
			printf(
				'.search-intel-logo { background-image: url(%s); background-size: contain; background-repeat: no-repeat; }',
				esc_url( $logo )
			);
		}

		// Hide default branding if configured.
		if ( ! empty( $this->settings['wl_hide_branding'] ) ) {
			echo '.search-intel-branding { display: none !important; }';
		}

		echo '</style>';
	}

	// ── Reseller Support ─────────────────────────────────────────────────────

	/**
	 * Generate a reseller configuration export.
	 *
	 * @return array Reseller config suitable for importing on client sites.
	 */
	public function export_reseller_config(): array {
		return [
			'white_label' => $this->get_config(),
			'version'     => SEARCH_INTEL_VERSION,
			'exported'    => gmdate( 'Y-m-d\TH:i:s\Z' ),
		];
	}

	/**
	 * Import a reseller configuration.
	 *
	 * @param array $config Reseller config.
	 * @return bool
	 */
	public function import_reseller_config( array $config ): bool {
		if ( empty( $config['white_label'] ) || ! is_array( $config['white_label'] ) ) {
			return false;
		}

		$wl = $config['white_label'];

		$settings = get_option( 'search_intel_settings', [] );
		$settings = is_array( $settings ) ? $settings : [];

		$settings['enable_white_label'] = true;

		$map = [
			'plugin_name'   => 'wl_plugin_name',
			'plugin_uri'    => 'wl_plugin_uri',
			'author_name'   => 'wl_author_name',
			'author_uri'    => 'wl_author_uri',
			'description'   => 'wl_description',
			'logo_url'      => 'wl_logo_url',
			'brand_color'   => 'wl_brand_color',
			'hide_branding' => 'wl_hide_branding',
		];

		foreach ( $map as $src => $dest ) {
			if ( isset( $wl[ $src ] ) ) {
				$settings[ $dest ] = is_bool( $wl[ $src ] ) ? $wl[ $src ] : sanitize_text_field( $wl[ $src ] );
			}
		}

		return update_option( 'search_intel_settings', $settings );
	}
}
