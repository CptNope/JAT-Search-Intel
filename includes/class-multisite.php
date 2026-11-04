<?php
namespace JAT_Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Multisite support for JAT Search Intel.
 *
 * Provides network-level default settings, per-site overrides,
 * centralized reporting across the network, and a network admin page.
 *
 * @since 3.0.0
 */
class Multisite {

	/** @var array */
	private $settings;

	/**
	 * @param array $settings Plugin settings.
	 */
	public function __construct( array $settings ) {
		$this->settings = $settings;

		if ( ! is_multisite() ) {
			return;
		}

		add_action( 'network_admin_menu', [ $this, 'register_network_menu' ] );
		add_action( 'network_admin_edit_jat_search_intel_network', [ $this, 'save_network_settings' ] );

		// Apply network defaults to new sites.
		add_action( 'wp_initialize_site', [ $this, 'apply_defaults_to_new_site' ], 10, 2 );
	}

	// ── Network Settings ─────────────────────────────────────────────────────

	/**
	 * Get network-level default settings.
	 *
	 * @return array
	 */
	public function get_network_defaults(): array {
		if ( ! is_multisite() ) {
			return [];
		}
		$defaults = get_site_option( 'jat_search_intel_network_defaults', [] );
		return is_array( $defaults ) ? $defaults : [];
	}

	/**
	 * Save network-level default settings.
	 *
	 * @param array $settings Settings to save as network defaults.
	 * @return bool
	 */
	public function save_network_defaults( array $settings ): bool {
		if ( ! is_multisite() ) {
			return false;
		}
		return update_site_option( 'jat_search_intel_network_defaults', $settings );
	}

	/**
	 * Get effective settings for a site, merging network defaults with site overrides.
	 *
	 * @param int $site_id Site ID (0 = current site).
	 * @return array
	 */
	public function get_effective_settings( int $site_id = 0 ): array {
		$network_defaults = $this->get_network_defaults();
		$plugin_defaults  = Core::get_default_settings();

		if ( $site_id > 0 && is_multisite() ) {
			switch_to_blog( $site_id );
		}

		$site_settings = get_option( 'jat_search_intel_settings', [] );
		$site_settings = is_array( $site_settings ) ? $site_settings : [];

		// Check which keys this site has explicitly overridden.
		$site_overrides = get_option( 'jat_search_intel_site_overrides', [] );
		$site_overrides = is_array( $site_overrides ) ? $site_overrides : [];

		if ( $site_id > 0 && is_multisite() ) {
			restore_current_blog();
		}

		// Start with plugin defaults, layer network defaults, then site overrides.
		$effective = $plugin_defaults;

		foreach ( $network_defaults as $key => $value ) {
			if ( array_key_exists( $key, $effective ) ) {
				$effective[ $key ] = $value;
			}
		}

		foreach ( $site_overrides as $key ) {
			if ( isset( $site_settings[ $key ] ) ) {
				$effective[ $key ] = $site_settings[ $key ];
			}
		}

		return $effective;
	}

	/**
	 * Mark a setting key as overridden at the site level.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $value   Setting value.
	 * @param int    $site_id Site ID (0 = current site).
	 * @return bool
	 */
	public function set_site_override( string $key, $value, int $site_id = 0 ): bool {
		if ( $site_id > 0 && is_multisite() ) {
			switch_to_blog( $site_id );
		}

		// Mark as overridden.
		$overrides = get_option( 'jat_search_intel_site_overrides', [] );
		$overrides = is_array( $overrides ) ? $overrides : [];
		if ( ! in_array( $key, $overrides, true ) ) {
			$overrides[] = $key;
			update_option( 'jat_search_intel_site_overrides', $overrides );
		}

		// Save the value.
		$settings = get_option( 'jat_search_intel_settings', [] );
		$settings = is_array( $settings ) ? $settings : [];
		$settings[ $key ] = $value;
		$result = update_option( 'jat_search_intel_settings', $settings );

		if ( $site_id > 0 && is_multisite() ) {
			restore_current_blog();
		}

		return $result;
	}

	/**
	 * Remove a site-level override so the network default applies again.
	 *
	 * @param string $key     Setting key.
	 * @param int    $site_id Site ID (0 = current site).
	 * @return bool
	 */
	public function remove_site_override( string $key, int $site_id = 0 ): bool {
		if ( $site_id > 0 && is_multisite() ) {
			switch_to_blog( $site_id );
		}

		$overrides = get_option( 'jat_search_intel_site_overrides', [] );
		$overrides = is_array( $overrides ) ? $overrides : [];
		$overrides = array_values( array_diff( $overrides, [ $key ] ) );
		$result    = update_option( 'jat_search_intel_site_overrides', $overrides );

		if ( $site_id > 0 && is_multisite() ) {
			restore_current_blog();
		}

		return $result;
	}

	/**
	 * Apply network defaults to a newly created site.
	 *
	 * @param \WP_Site $new_site New site object.
	 * @param array    $args     Arguments.
	 */
	public function apply_defaults_to_new_site( $new_site, array $args ): void {
		$defaults = $this->get_network_defaults();
		if ( empty( $defaults ) ) {
			return;
		}

		switch_to_blog( (int) $new_site->blog_id );
		$plugin_defaults = Core::get_default_settings();
		$merged          = array_merge( $plugin_defaults, $defaults );
		update_option( 'jat_search_intel_settings', $merged );
		restore_current_blog();
	}

	// ── Centralized Reporting ────────────────────────────────────────────────

	/**
	 * Get a summary of scores and feature status across all network sites.
	 *
	 * @return array Array of site summaries.
	 */
	public function get_network_report(): array {
		if ( ! is_multisite() ) {
			return [];
		}

		$sites  = get_sites( [ 'number' => 100 ] );
		$report = [];

		foreach ( $sites as $site ) {
			switch_to_blog( (int) $site->blog_id );

			$settings = get_option( 'jat_search_intel_settings', [] );
			$settings = is_array( $settings ) ? $settings : [];

			$total_obj = wp_count_posts( 'post' );
			$total     = (int) ( $total_obj->publish ?? 0 );

			// Average scores.
			$avg = $this->get_site_average_scores( $total );

			$report[] = [
				'blog_id'    => (int) $site->blog_id,
				'domain'     => $site->domain,
				'path'       => $site->path,
				'url'        => get_home_url(),
				'posts'      => $total,
				'avg_seo'    => $avg['seo'],
				'avg_aeo'    => $avg['aeo'],
				'avg_geo'    => $avg['geo'],
				'features'   => [
					'seo'    => ! empty( $settings['enable_seo'] ),
					'aeo'    => ! empty( $settings['enable_aeo'] ),
					'geo'    => ! empty( $settings['enable_geo'] ),
					'schema' => ! empty( $settings['enable_schema'] ),
				],
				'overrides'  => count( get_option( 'jat_search_intel_site_overrides', [] ) ),
			];

			restore_current_blog();
		}

		return $report;
	}

	/**
	 * Get average SEO/AEO/GEO scores for the current site.
	 *
	 * @param int $total Total published posts.
	 * @return array{seo: int, aeo: int, geo: int}
	 */
	private function get_site_average_scores( int $total ): array {
		if ( $total === 0 ) {
			return [ 'seo' => 0, 'aeo' => 0, 'geo' => 0 ];
		}

		$seo_sum = 0;
		$aeo_sum = 0;
		$geo_sum = 0;
		$limit   = min( $total, 200 );

		$posts = get_posts( [
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'orderby'        => 'ID',
			'order'          => 'DESC',
		] );

		foreach ( $posts as $post ) {
			$seo_sum += (int) get_post_meta( $post->ID, '_jat_search_intel_seo_score', true );
			$aeo_sum += (int) get_post_meta( $post->ID, '_jat_search_intel_aeo_score', true );
			$geo_sum += (int) get_post_meta( $post->ID, '_jat_search_intel_geo_score', true );
		}

		$count = count( $posts );
		return [
			'seo' => $count > 0 ? (int) round( $seo_sum / $count ) : 0,
			'aeo' => $count > 0 ? (int) round( $aeo_sum / $count ) : 0,
			'geo' => $count > 0 ? (int) round( $geo_sum / $count ) : 0,
		];
	}

	// ── Network Admin Menu ───────────────────────────────────────────────────

	/**
	 * Register network admin menu page.
	 */
	public function register_network_menu(): void {
		add_submenu_page(
			'settings.php',
			'JAT Search Intel Network',
			'JAT Search Intel',
			'manage_network_options',
			'jat-search-intel-network',
			[ $this, 'render_network_page' ]
		);
	}

	/**
	 * Handle saving network settings.
	 */
	public function save_network_settings(): void {
		check_admin_referer( 'jat_search_intel_network_settings' );

		if ( ! current_user_can( 'manage_network_options' ) ) {
			wp_die( 'Unauthorized.' );
		}

		$defaults = Core::get_default_settings();
		$input    = isset( $_POST['jat_search_intel_network'] ) ? wp_unslash( $_POST['jat_search_intel_network'] ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each field is sanitized individually below.
		$input    = is_array( $input ) ? $input : [];

		$sanitized = [];
		foreach ( $input as $key => $value ) {
			if ( ! array_key_exists( $key, $defaults ) ) {
				continue;
			}
			$default_val = $defaults[ $key ];
			if ( is_bool( $default_val ) ) {
				$sanitized[ $key ] = (bool) $value;
			} elseif ( is_int( $default_val ) ) {
				$sanitized[ $key ] = absint( $value );
			} else {
				$sanitized[ $key ] = sanitize_text_field( $value );
			}
		}

		$this->save_network_defaults( $sanitized );

		wp_safe_redirect(
			add_query_arg( [
				'page'    => 'jat-search-intel-network',
				'updated' => 'true',
			], network_admin_url( 'settings.php' ) )
		);
		exit;
	}

	/**
	 * Render the network admin page.
	 */
	public function render_network_page(): void {
		$defaults = $this->get_network_defaults();
		$report   = $this->get_network_report();

		echo '<div class="wrap">';
		echo '<h1>JAT Search Intel — Network Settings</h1>';

		if ( isset( $_GET['updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view/pagination parameter.
			echo '<div class="notice notice-success"><p>Network defaults saved.</p></div>';
		}

		// Network defaults form.
		echo '<h2>Network Default Settings</h2>';
		echo '<form method="post" action="' . esc_url( network_admin_url( 'edit.php?action=jat_search_intel_network' ) ) . '">';
		wp_nonce_field( 'jat_search_intel_network_settings' );

		$plugin_defaults = Core::get_default_settings();
		echo '<table class="form-table">';
		foreach ( $plugin_defaults as $key => $default_val ) {
			if ( is_bool( $default_val ) ) {
				$checked = ! empty( $defaults[ $key ] ) ? ' checked' : '';
				printf(
					'<tr><th><label for="%1$s">%1$s</label></th><td><input type="checkbox" name="jat_search_intel_network[%1$s]" id="%1$s" value="1"%2$s></td></tr>',
					esc_attr( $key ),
					esc_attr( $checked )
				);
			} elseif ( is_string( $default_val ) ) {
				$value = $defaults[ $key ] ?? '';
				printf(
					'<tr><th><label for="%1$s">%1$s</label></th><td><input type="text" name="jat_search_intel_network[%1$s]" id="%1$s" value="%2$s" class="regular-text"></td></tr>',
					esc_attr( $key ),
					esc_attr( $value )
				);
			}
		}
		echo '</table>';

		submit_button( 'Save Network Defaults' );
		echo '</form>';

		// Network report.
		echo '<h2>Network Report</h2>';
		if ( empty( $report ) ) {
			echo '<p>No sites found.</p>';
		} else {
			echo '<table class="widefat striped">';
			echo '<thead><tr><th>Site</th><th>Posts</th><th>Avg SEO</th><th>Avg AEO</th><th>Avg GEO</th><th>Overrides</th></tr></thead>';
			echo '<tbody>';
			foreach ( $report as $site ) {
				printf(
					'<tr><td><a href="%s">%s%s</a></td><td>%d</td><td>%d</td><td>%d</td><td>%d</td><td>%d</td></tr>',
					esc_url( $site['url'] . '/wp-admin/' ),
					esc_html( $site['domain'] ),
					esc_html( $site['path'] ),
					esc_html( $site['posts'] ),
					esc_html( $site['avg_seo'] ),
					esc_html( $site['avg_aeo'] ),
					esc_html( $site['avg_geo'] ),
					esc_html( $site['overrides'] )
				);
			}
			echo '</tbody></table>';
		}

		echo '</div>';
	}
}
