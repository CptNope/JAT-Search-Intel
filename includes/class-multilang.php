<?php
namespace JAT_Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Multi-language support for JAT Search Intel.
 *
 * Integrates with WPML and Polylang for per-language SEO metadata,
 * hreflang tag generation, per-language sitemaps, and per-language llms.txt.
 *
 * @since 3.0.0
 */
class Multilang {

	/** @var array */
	private $settings;

	/**
	 * @param array $settings Plugin settings.
	 */
	public function __construct( array $settings ) {
		$this->settings = $settings;

		if ( empty( $settings['enable_multilang'] ) ) {
			return;
		}

		add_action( 'wp_head', [ $this, 'render_hreflang' ], 1 );
		add_filter( 'jat_search_intel_meta_fields', [ $this, 'add_language_fields' ], 10, 2 );
	}

	// ── Plugin Detection ─────────────────────────────────────────────────────

	/**
	 * Detect which multilingual plugin is active.
	 *
	 * @return string 'wpml', 'polylang', or '' if none.
	 */
	public function detect_plugin(): string {
		if ( defined( 'ICL_SITEPRESS_VERSION' ) ) {
			return 'wpml';
		}
		if ( function_exists( 'pll_languages_list' ) ) {
			return 'polylang';
		}
		return '';
	}

	/**
	 * Get all active languages.
	 *
	 * @return array Array of language codes.
	 */
	public function get_languages(): array {
		$plugin = $this->detect_plugin();

		if ( 'wpml' === $plugin ) {
			return $this->get_wpml_languages();
		}

		if ( 'polylang' === $plugin ) {
			return $this->get_polylang_languages();
		}

		return [ $this->get_site_locale() ];
	}

	/**
	 * Get the default language code.
	 *
	 * @return string
	 */
	public function get_default_language(): string {
		$plugin = $this->detect_plugin();

		if ( 'wpml' === $plugin && function_exists( 'wpml_get_default_language' ) ) {
			return wpml_get_default_language();
		}

		if ( 'polylang' === $plugin && function_exists( 'pll_default_language' ) ) {
			return pll_default_language( 'slug' );
		}

		return substr( get_locale(), 0, 2 );
	}

	/**
	 * Get the language of a specific post.
	 *
	 * @param int $post_id Post ID.
	 * @return string Language code.
	 */
	public function get_post_language( int $post_id ): string {
		$plugin = $this->detect_plugin();

		if ( 'wpml' === $plugin ) {
			$info = apply_filters( 'wpml_post_language_details', null, $post_id ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML public API filter.
			return is_array( $info ) ? ( $info['language_code'] ?? '' ) : '';
		}

		if ( 'polylang' === $plugin && function_exists( 'pll_get_post_language' ) ) {
			return pll_get_post_language( $post_id, 'slug' ) ?: '';
		}

		return $this->get_default_language();
	}

	/**
	 * Get translations of a post.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, int> Map of language code => translated post ID.
	 */
	public function get_post_translations( int $post_id ): array {
		$plugin = $this->detect_plugin();

		if ( 'wpml' === $plugin ) {
			$type         = get_post_type( $post_id );
			$trid         = apply_filters( 'wpml_element_trid', null, $post_id, 'post_' . $type ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML public API filter.
			$translations = apply_filters( 'wpml_get_element_translations', null, $trid, 'post_' . $type ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML public API filter.

			$result = [];
			if ( is_array( $translations ) ) {
				foreach ( $translations as $lang => $info ) {
					if ( is_object( $info ) && isset( $info->element_id ) ) {
						$result[ $lang ] = (int) $info->element_id;
					}
				}
			}
			return $result;
		}

		if ( 'polylang' === $plugin && function_exists( 'pll_get_post_translations' ) ) {
			$translations = pll_get_post_translations( $post_id );
			return is_array( $translations ) ? array_map( 'intval', $translations ) : [];
		}

		return [ $this->get_default_language() => $post_id ];
	}

	// ── Hreflang Tags ────────────────────────────────────────────────────────

	/**
	 * Render hreflang link tags on singular pages.
	 */
	public function render_hreflang(): void {
		if ( ! is_singular() ) {
			return;
		}

		$post_id      = get_the_ID();
		$translations = $this->get_post_translations( $post_id );

		if ( count( $translations ) <= 1 ) {
			return;
		}

		echo "\n<!-- JAT Search Intel hreflang -->\n";
		foreach ( $translations as $lang => $tid ) {
			$url    = get_permalink( $tid );
			$locale = $this->lang_to_locale( $lang );
			printf(
				'<link rel="alternate" hreflang="%s" href="%s" />' . "\n",
				esc_attr( $locale ),
				esc_url( $url )
			);
		}

		// x-default points to the default language version.
		$default_lang = $this->get_default_language();
		if ( isset( $translations[ $default_lang ] ) ) {
			printf(
				'<link rel="alternate" hreflang="x-default" href="%s" />' . "\n",
				esc_url( get_permalink( $translations[ $default_lang ] ) )
			);
		}
	}

	// ── Per-Language Meta Fields ─────────────────────────────────────────────

	/**
	 * Filter to add language-specific fields for metabox.
	 *
	 * @param array $fields Current meta fields.
	 * @param int   $post_id Post ID.
	 * @return array
	 */
	public function add_language_fields( array $fields, int $post_id ): array {
		$lang = $this->get_post_language( $post_id );
		if ( ! empty( $lang ) ) {
			$fields['language'] = $lang;
		}
		return $fields;
	}

	/**
	 * Get per-language meta value.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $meta_key Base meta key (e.g., '_jat_search_intel_title').
	 * @param string $lang     Language code (empty = current post language).
	 * @return string
	 */
	public function get_lang_meta( int $post_id, string $meta_key, string $lang = '' ): string {
		if ( empty( $lang ) ) {
			$lang = $this->get_post_language( $post_id );
		}

		// Try language-specific key first.
		$lang_key = $meta_key . '_' . $lang;
		$value    = get_post_meta( $post_id, $lang_key, true );

		if ( ! empty( $value ) ) {
			return (string) $value;
		}

		// Fall back to base key.
		return (string) get_post_meta( $post_id, $meta_key, true );
	}

	/**
	 * Save per-language meta value.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $meta_key Base meta key.
	 * @param string $value    Value to save.
	 * @param string $lang     Language code (empty = current post language).
	 * @return bool
	 */
	public function save_lang_meta( int $post_id, string $meta_key, string $value, string $lang = '' ): bool {
		if ( empty( $lang ) ) {
			$lang = $this->get_post_language( $post_id );
		}

		$lang_key = $meta_key . '_' . $lang;
		return (bool) update_post_meta( $post_id, $lang_key, sanitize_text_field( $value ) );
	}

	// ── Per-Language Sitemap ─────────────────────────────────────────────────

	/**
	 * Get sitemap URLs for a specific language.
	 *
	 * @param string $lang Language code.
	 * @return array Array of URL entries.
	 */
	public function get_language_sitemap_urls( string $lang ): array {
		$posts = get_posts( [
			'post_type'      => [ 'post', 'page' ],
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		] );

		$urls = [];
		foreach ( $posts as $post_id ) {
			$post_lang = $this->get_post_language( $post_id );
			if ( $post_lang !== $lang ) {
				continue;
			}

			$translations = $this->get_post_translations( $post_id );

			$entry = [
				'loc'     => get_permalink( $post_id ),
				'lastmod' => get_the_modified_date( 'c', $post_id ),
			];

			// Add xhtml:link alternates.
			if ( count( $translations ) > 1 ) {
				$alternates = [];
				foreach ( $translations as $t_lang => $t_id ) {
					$alternates[] = [
						'hreflang' => $this->lang_to_locale( $t_lang ),
						'href'     => get_permalink( $t_id ),
					];
				}
				$entry['alternates'] = $alternates;
			}

			$urls[] = $entry;
		}

		return $urls;
	}

	// ── Per-Language llms.txt ─────────────────────────────────────────────────

	/**
	 * Get llms.txt content for a specific language.
	 *
	 * @param string $lang Language code.
	 * @return string
	 */
	public function get_language_llms_txt( string $lang ): string {
		$site_name = get_bloginfo( 'name' );
		$site_desc = get_bloginfo( 'description' );

		$output  = "# {$site_name}\n";
		$output .= "> {$site_desc}\n\n";
		$output .= "## Language: {$lang}\n\n";

		$posts = get_posts( [
			'post_type'      => [ 'post', 'page' ],
			'post_status'    => 'publish',
			'posts_per_page' => 50,
			'orderby'        => 'date',
			'order'          => 'DESC',
		] );

		foreach ( $posts as $post ) {
			$post_lang = $this->get_post_language( $post->ID );
			if ( $post_lang !== $lang ) {
				continue;
			}

			$url  = get_permalink( $post->ID );
			$desc = get_post_meta( $post->ID, '_jat_search_intel_description', true );
			if ( empty( $desc ) ) {
				$desc = wp_trim_words( wp_strip_all_tags( $post->post_content ), 30, '...' );
			}

			$output .= "- [{$post->post_title}]({$url}): {$desc}\n";
		}

		return $output;
	}

	// ── Helpers ──────────────────────────────────────────────────────────────

	/**
	 * Convert a short language code to a locale-style code for hreflang.
	 *
	 * @param string $lang Short language code (e.g., 'en', 'fr').
	 * @return string Locale code (e.g., 'en', 'fr').
	 */
	private function lang_to_locale( string $lang ): string {
		// If it already contains a dash/underscore, normalize.
		$lang = str_replace( '_', '-', $lang );
		return strtolower( $lang );
	}

	/**
	 * Get the site locale as a short code.
	 *
	 * @return string
	 */
	private function get_site_locale(): string {
		return substr( get_locale(), 0, 2 );
	}

	/**
	 * Get WPML active languages.
	 *
	 * @return array
	 */
	private function get_wpml_languages(): array {
		$languages = apply_filters( 'wpml_active_languages', null, [ 'skip_missing' => 0 ] ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML public API filter.
		if ( is_array( $languages ) ) {
			return array_keys( $languages );
		}
		return [ $this->get_site_locale() ];
	}

	/**
	 * Get Polylang active languages.
	 *
	 * @return array
	 */
	private function get_polylang_languages(): array {
		if ( function_exists( 'pll_languages_list' ) ) {
			$langs = pll_languages_list( [ 'fields' => 'slug' ] );
			return is_array( $langs ) ? $langs : [ $this->get_site_locale() ];
		}
		return [ $this->get_site_locale() ];
	}
}
