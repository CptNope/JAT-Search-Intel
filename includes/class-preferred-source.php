<?php
/**
 * "Add as a preferred source on Google" button.
 *
 * Google lets readers pick preferred sources, which then show up more often
 * in Top Stories and AI Mode. This links readers straight to your site in
 * Google's source preferences page. Available as a shortcode, a block, and
 * optionally at the end of every post.
 *
 * @see https://developers.google.com/search/docs/appearance/preferred-sources
 *
 * @package JAT_Search_Intel
 */

namespace JAT_Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Preferred source link.
 */
class Preferred_Source {

	/** Google's source preferences page. */
	public const BASE_URL = 'https://www.google.com/preferences/source';

	/** @var array<string,mixed> */
	private $settings;

	/**
	 * Constructor. Built only when the button is added to posts automatically.
	 *
	 * @param array<string,mixed> $settings Plugin settings.
	 */
	public function __construct( array $settings ) {
		$this->settings = $settings;
		add_filter( 'the_content', [ $this, 'append' ], 20 );
	}

	/**
	 * The site's host, as Google expects it in the deep link.
	 *
	 * @return string
	 */
	public static function site_host(): string {
		return (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST );
	}

	/**
	 * Whether the site can be a preferred source: Google only accepts whole domains
	 * and subdomains, not sites in a subdirectory.
	 *
	 * @return bool
	 */
	public static function is_eligible(): bool {
		$path = trim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
		return '' === $path && '' !== self::site_host();
	}

	/**
	 * Deep link to this site in Google's source preferences.
	 *
	 * @return string
	 */
	public static function url(): string {
		return add_query_arg( 'q', rawurlencode( self::site_host() ), self::BASE_URL );
	}

	/**
	 * Button HTML.
	 *
	 * @param string $label Optional label.
	 * @return string
	 */
	public static function render( string $label = '' ): string {
		if ( '' === $label ) {
			/* translators: %s: site name. */
			$label = sprintf( __( 'Add %s as a preferred source on Google', 'jat-search-intel' ), get_bloginfo( 'name' ) );
		}
		return sprintf(
			'<p class="jsi-preferred-source"><a href="%1$s" target="_blank" rel="noopener" style="display:inline-flex;align-items:center;gap:.5em;padding:.55em 1.1em;border:1px solid currentColor;border-radius:999px;text-decoration:none;font-weight:600;line-height:1.3"><span aria-hidden="true">&#9733;</span>%2$s</a></p>',
			esc_url( self::url() ),
			esc_html( $label )
		);
	}

	/**
	 * [jat_search_intel_preferred_source label="..."] shortcode.
	 *
	 * @param array<string,string>|string $atts Attributes.
	 * @return string
	 */
	public static function shortcode( $atts ): string {
		$atts = shortcode_atts( [ 'label' => '' ], (array) $atts, 'jat_search_intel_preferred_source' );
		return self::render( sanitize_text_field( $atts['label'] ) );
	}

	/**
	 * Render callback for the jat-search-intel/preferred-source block.
	 *
	 * @param array<string,mixed> $attributes Block attributes.
	 * @return string
	 */
	public static function render_block( $attributes ): string {
		$label = is_array( $attributes ) && isset( $attributes['label'] ) ? sanitize_text_field( (string) $attributes['label'] ) : '';
		return self::render( $label );
	}

	/**
	 * Add the button after the content of single posts.
	 *
	 * @param string $content Content.
	 * @return string
	 */
	public function append( $content ): string {
		$content = (string) $content;
		if ( ! is_singular( (array) ( $this->settings['preferred_source_post_types'] ?? [ 'post' ] ) ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		// Skip posts that already show the button.
		if ( false !== strpos( $content, 'jsi-preferred-source' ) ) {
			return $content;
		}
		return $content . self::render();
	}
}
