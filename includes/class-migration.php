<?php
namespace Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SEO plugin migration engine.
 *
 * Migrates titles, descriptions, keywords, robots, canonical, OG, schema,
 * redirects, and breadcrumbs from Yoast SEO, Rank Math, All in One SEO,
 * SEOPress, and The SEO Framework.
 *
 * @since 3.0.0
 */
class Migration {

	/** @var array */
	private $settings;

	/** @var array Source plugin definitions. */
	private const SOURCES = [
		'yoast'     => [
			'label'  => 'Yoast SEO',
			'detect' => 'wordpress-seo/wp-seo.php',
			'meta'   => [
				'title'       => '_yoast_wpseo_title',
				'description' => '_yoast_wpseo_metadesc',
				'keyword'     => '_yoast_wpseo_focuskw',
				'robots'      => '_yoast_wpseo_meta-robots-noindex',
				'canonical'   => '_yoast_wpseo_canonical',
				'og_title'    => '_yoast_wpseo_opengraph-title',
				'og_desc'     => '_yoast_wpseo_opengraph-description',
				'og_image'    => '_yoast_wpseo_opengraph-image',
			],
			'options' => [
				'redirects' => 'wpseo-premium-redirects-base',
			],
		],
		'rankmath'  => [
			'label'  => 'Rank Math',
			'detect' => 'seo-by-rank-math/rank-math.php',
			'meta'   => [
				'title'       => 'rank_math_title',
				'description' => 'rank_math_description',
				'keyword'     => 'rank_math_focus_keyword',
				'robots'      => 'rank_math_robots',
				'canonical'   => 'rank_math_canonical_url',
				'schema'      => 'rank_math_schema_Article',
			],
			'options' => [
				'redirects' => 'rank_math_redirections',
				'404_log'   => 'rank_math_404_log',
			],
		],
		'aioseo'    => [
			'label'  => 'All in One SEO',
			'detect' => 'all-in-one-seo-pack/all_in_one_seo_pack.php',
			'meta'   => [
				'title'       => '_aioseo_title',
				'description' => '_aioseo_description',
				'robots'      => '_aioseo_noindex',
				'canonical'   => '_aioseo_canonical_url',
				'og_title'    => '_aioseo_og_title',
				'og_desc'     => '_aioseo_og_description',
				'og_image'    => '_aioseo_og_image',
				'twitter'     => '_aioseo_twitter_title',
			],
			'options' => [],
		],
		'seopress'  => [
			'label'  => 'SEOPress',
			'detect' => 'wp-seopress/seopress.php',
			'meta'   => [
				'title'       => '_seopress_titles_title',
				'description' => '_seopress_titles_desc',
				'robots'      => '_seopress_robots_index',
				'canonical'   => '_seopress_robots_canonical',
				'og_title'    => '_seopress_social_fb_title',
				'og_desc'     => '_seopress_social_fb_desc',
				'og_image'    => '_seopress_social_fb_img',
				'twitter'     => '_seopress_social_twitter_title',
			],
			'options' => [
				'redirects' => 'seopress_pro_redirections',
			],
		],
		'tsf'       => [
			'label'  => 'The SEO Framework',
			'detect' => 'autodescription/autodescription.php',
			'meta'   => [
				'title'       => '_genesis_title',
				'description' => '_genesis_description',
				'robots'      => '_genesis_noindex',
				'canonical'   => '_genesis_canonical_uri',
			],
			'options' => [],
		],
	];

	/**
	 * @param array $settings Plugin settings.
	 */
	public function __construct( array $settings ) {
		$this->settings = $settings;

		add_action( 'admin_menu', [ $this, 'register_admin_menu' ] );
		add_action( 'wp_ajax_search_intel_migration_run', [ $this, 'ajax_run_migration' ] );
		add_action( 'wp_ajax_search_intel_migration_dryrun', [ $this, 'ajax_dry_run' ] );
	}

	// ── Detection ────────────────────────────────────────────────────────────

	/**
	 * Detect which SEO plugins have data available for migration.
	 *
	 * @return array Array of detected source keys with post counts.
	 */
	public function detect_sources(): array {
		$detected = [];

		foreach ( self::SOURCES as $key => $source ) {
			$meta_keys = array_values( $source['meta'] );
			$count     = $this->count_posts_with_meta( $meta_keys );

			if ( $count > 0 ) {
				$detected[ $key ] = [
					'label' => $source['label'],
					'posts' => $count,
					'meta'  => array_keys( $source['meta'] ),
				];
			}
		}

		return $detected;
	}

	/**
	 * Count posts that have at least one of the given meta keys.
	 *
	 * @param array $meta_keys Meta keys to check.
	 * @return int
	 */
	private function count_posts_with_meta( array $meta_keys ): int {
		$posts = get_posts( [
			'post_type'      => [ 'post', 'page' ],
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		] );

		$count = 0;
		foreach ( $posts as $post_id ) {
			foreach ( $meta_keys as $meta_key ) {
				$val = get_post_meta( $post_id, $meta_key, true );
				if ( ! empty( $val ) ) {
					$count++;
					break;
				}
			}
		}

		return $count;
	}

	// ── Dry Run ──────────────────────────────────────────────────────────────

	/**
	 * Preview what would be migrated without making changes.
	 *
	 * @param string $source Source plugin key (yoast, rankmath, etc.).
	 * @return array Preview data.
	 */
	public function dry_run( string $source ): array {
		if ( ! isset( self::SOURCES[ $source ] ) ) {
			return [ 'error' => 'Unknown source: ' . $source ];
		}

		$config  = self::SOURCES[ $source ];
		$preview = [
			'source'     => $config['label'],
			'fields'     => [],
			'post_count' => 0,
			'samples'    => [],
		];

		$posts = get_posts( [
			'post_type'      => [ 'post', 'page' ],
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		] );

		$field_counts = [];
		$samples      = [];

		foreach ( $posts as $post_id ) {
			$post_has_data = false;

			foreach ( $config['meta'] as $field => $meta_key ) {
				$val = get_post_meta( $post_id, $meta_key, true );
				if ( ! empty( $val ) ) {
					$field_counts[ $field ] = ( $field_counts[ $field ] ?? 0 ) + 1;
					$post_has_data = true;

					// Collect up to 3 samples per field.
					if ( ! isset( $samples[ $field ] ) ) {
						$samples[ $field ] = [];
					}
					if ( count( $samples[ $field ] ) < 3 ) {
						$samples[ $field ][] = [
							'post_id' => $post_id,
							'title'   => get_the_title( $post_id ),
							'value'   => is_string( $val ) ? mb_strimwidth( $val, 0, 100, '...' ) : '(complex)',
						];
					}
				}
			}

			if ( $post_has_data ) {
				$preview['post_count']++;
			}
		}

		foreach ( $field_counts as $field => $count ) {
			$preview['fields'][ $field ] = [
				'count'   => $count,
				'target'  => $this->get_target_meta_key( $field ),
				'samples' => $samples[ $field ] ?? [],
			];
		}

		return $preview;
	}

	// ── Migration ────────────────────────────────────────────────────────────

	/**
	 * Run a full migration from a source plugin.
	 *
	 * @param string $source    Source plugin key.
	 * @param bool   $overwrite Whether to overwrite existing Search Intel values.
	 * @return array Migration result.
	 */
	public function migrate( string $source, bool $overwrite = false ): array {
		if ( ! isset( self::SOURCES[ $source ] ) ) {
			return [ 'error' => 'Unknown source: ' . $source ];
		}

		$config  = self::SOURCES[ $source ];
		$result  = [
			'source'   => $config['label'],
			'migrated' => 0,
			'skipped'  => 0,
			'fields'   => [],
		];

		$posts = get_posts( [
			'post_type'      => [ 'post', 'page' ],
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		] );

		foreach ( $posts as $post_id ) {
			$post_migrated = false;

			foreach ( $config['meta'] as $field => $meta_key ) {
				$source_val = get_post_meta( $post_id, $meta_key, true );
				if ( empty( $source_val ) ) {
					continue;
				}

				$target_key = $this->get_target_meta_key( $field );
				if ( empty( $target_key ) ) {
					continue;
				}

				$existing = get_post_meta( $post_id, $target_key, true );
				if ( ! empty( $existing ) && ! $overwrite ) {
					$result['skipped']++;
					continue;
				}

				$sanitized = $this->sanitize_field( $field, $source_val );
				update_post_meta( $post_id, $target_key, $sanitized );

				$result['fields'][ $field ] = ( $result['fields'][ $field ] ?? 0 ) + 1;
				$post_migrated = true;
			}

			if ( $post_migrated ) {
				$result['migrated']++;
			}
		}

		// Migrate redirects if supported.
		if ( ! empty( $config['options']['redirects'] ) ) {
			$redirect_count = $this->migrate_redirects( $source, $config );
			$result['redirects'] = $redirect_count;
		}

		return $result;
	}

	/**
	 * Map field names to Search Intel meta keys.
	 *
	 * @param string $field Field name.
	 * @return string Target meta key.
	 */
	private function get_target_meta_key( string $field ): string {
		$map = [
			'title'       => '_search_intel_title',
			'description' => '_search_intel_description',
			'keyword'     => '_search_intel_focus_keyword',
			'robots'      => '_search_intel_robots',
			'canonical'   => '_search_intel_canonical',
			'og_title'    => '_search_intel_og_title',
			'og_desc'     => '_search_intel_og_description',
			'og_image'    => '_search_intel_og_image',
			'twitter'     => '_search_intel_twitter_title',
			'schema'      => '_search_intel_schema',
		];

		return $map[ $field ] ?? '';
	}

	/**
	 * Sanitize a field value based on its type.
	 *
	 * @param string $field Field name.
	 * @param mixed  $value Raw value.
	 * @return string Sanitized value.
	 */
	private function sanitize_field( string $field, $value ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}

		if ( in_array( $field, [ 'canonical', 'og_image' ], true ) ) {
			return esc_url_raw( $value );
		}

		return sanitize_text_field( $value );
	}

	/**
	 * Migrate redirects from a source plugin.
	 *
	 * @param string $source Source key.
	 * @param array  $config Source configuration.
	 * @return int Number of redirects migrated.
	 */
	private function migrate_redirects( string $source, array $config ): int {
		$option_key = $config['options']['redirects'] ?? '';
		if ( empty( $option_key ) ) {
			return 0;
		}

		$source_redirects = get_option( $option_key, [] );
		if ( ! is_array( $source_redirects ) || empty( $source_redirects ) ) {
			return 0;
		}

		$existing = get_option( 'search_intel_redirects', [] );
		$existing = is_array( $existing ) ? $existing : [];
		$count    = 0;

		foreach ( $source_redirects as $redirect ) {
			$from = $this->extract_redirect_url( $redirect, $source, 'from' );
			$to   = $this->extract_redirect_url( $redirect, $source, 'to' );
			$code = $this->extract_redirect_code( $redirect, $source );

			if ( empty( $from ) || empty( $to ) ) {
				continue;
			}

			// Skip duplicates.
			$dupe = false;
			foreach ( $existing as $ex ) {
				if ( ( $ex['from'] ?? '' ) === $from ) {
					$dupe = true;
					break;
				}
			}
			if ( $dupe ) {
				continue;
			}

			$existing[] = [
				'from' => $from,
				'to'   => $to,
				'code' => $code,
				'type' => 'migrated',
			];
			$count++;
		}

		if ( $count > 0 ) {
			update_option( 'search_intel_redirects', $existing, false );
		}

		return $count;
	}

	/**
	 * Extract a redirect URL from source-specific format.
	 *
	 * @param mixed  $redirect Redirect data.
	 * @param string $source   Source key.
	 * @param string $dir      Direction: 'from' or 'to'.
	 * @return string
	 */
	private function extract_redirect_url( $redirect, string $source, string $dir ): string {
		if ( ! is_array( $redirect ) ) {
			return '';
		}

		switch ( $source ) {
			case 'yoast':
				return $dir === 'from' ? ( $redirect['origin'] ?? '' ) : ( $redirect['url'] ?? '' );
			case 'rankmath':
				$sources = $redirect['sources'] ?? [];
				if ( $dir === 'from' && ! empty( $sources[0]['pattern'] ) ) {
					return $sources[0]['pattern'];
				}
				return $dir === 'to' ? ( $redirect['url_to'] ?? '' ) : '';
			case 'seopress':
				return $dir === 'from' ? ( $redirect['from'] ?? '' ) : ( $redirect['to'] ?? '' );
			default:
				return $dir === 'from' ? ( $redirect['from'] ?? '' ) : ( $redirect['to'] ?? '' );
		}
	}

	/**
	 * Extract redirect status code from source-specific format.
	 *
	 * @param mixed  $redirect Redirect data.
	 * @param string $source   Source key.
	 * @return int
	 */
	private function extract_redirect_code( $redirect, string $source ): int {
		if ( ! is_array( $redirect ) ) {
			return 301;
		}

		switch ( $source ) {
			case 'yoast':
				return (int) ( $redirect['type'] ?? 301 );
			case 'rankmath':
				return (int) ( $redirect['header_code'] ?? 301 );
			case 'seopress':
				return (int) ( $redirect['status'] ?? 301 );
			default:
				return (int) ( $redirect['code'] ?? 301 );
		}
	}

	// ── AJAX Handlers ────────────────────────────────────────────────────────

	/**
	 * AJAX handler for running migration.
	 */
	public function ajax_run_migration(): void {
		check_ajax_referer( 'search_intel_migration', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions.' );
		}

		$source    = sanitize_text_field( $_POST['source'] ?? '' );
		$overwrite = ! empty( $_POST['overwrite'] );

		$result = $this->migrate( $source, $overwrite );
		wp_send_json_success( $result );
	}

	/**
	 * AJAX handler for dry run preview.
	 */
	public function ajax_dry_run(): void {
		check_ajax_referer( 'search_intel_migration', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions.' );
		}

		$source = sanitize_text_field( $_POST['source'] ?? '' );
		$result = $this->dry_run( $source );
		wp_send_json_success( $result );
	}

	// ── Admin Page ───────────────────────────────────────────────────────────

	/**
	 * Register admin menu.
	 */
	public function register_admin_menu(): void {
		add_submenu_page(
			'search-intel',
			'Plugin Migration',
			'Migration',
			'manage_options',
			'search-intel-migration',
			[ $this, 'render_admin_page' ]
		);
	}

	/**
	 * Render migration admin page.
	 */
	public function render_admin_page(): void {
		$detected = $this->detect_sources();

		echo '<div class="wrap"><h1>SEO Plugin Migration</h1>';

		if ( empty( $detected ) ) {
			echo '<div class="notice notice-info"><p>No SEO plugin data detected. Migration is available for Yoast SEO, Rank Math, All in One SEO, SEOPress, and The SEO Framework.</p></div>';
			echo '</div>';
			return;
		}

		echo '<p>The following SEO plugins have data that can be migrated to Search Intel:</p>';

		foreach ( $detected as $key => $info ) {
			printf(
				'<div class="card" style="max-width:600px;margin-bottom:16px"><h2>%s</h2><p><strong>%d</strong> posts with data</p><p>Fields: %s</p><button class="button" data-action="dryrun" data-source="%s">Preview Migration</button> <button class="button button-primary" data-action="migrate" data-source="%s">Migrate Now</button> <label style="margin-left:12px"><input type="checkbox" data-overwrite="%s"> Overwrite existing</label><div class="si-migration-result" data-result="%s"></div></div>',
				esc_html( $info['label'] ),
				absint( $info['posts'] ),
				esc_html( implode( ', ', $info['meta'] ) ),
				esc_attr( $key ),
				esc_attr( $key ),
				esc_attr( $key ),
				esc_attr( $key )
			);
		}

		echo '</div>';
	}
}
