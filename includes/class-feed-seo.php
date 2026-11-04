<?php
/**
 * RSS feed SEO: a backlink to the original post in every feed item, so
 * scraped copies point search engines (and readers) back to the source.
 *
 * @package JAT_Search_Intel
 */

namespace JAT_Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Feed footer.
 */
class Feed_SEO {

	/** @var array<string,mixed> */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param array<string,mixed> $settings Plugin settings.
	 */
	public function __construct( array $settings ) {
		$this->settings = $settings;

		if ( ! empty( $settings['enable_rss_footer'] ) && '' !== trim( (string) ( $settings['rss_after_content'] ?? '' ) ) ) {
			add_filter( 'the_content_feed', [ $this, 'append_footer' ] );
			add_filter( 'the_excerpt_rss', [ $this, 'append_footer' ] );
		}
	}

	/**
	 * Replace %%POSTLINK%%, %%BLOGLINK%%, %%BLOGDESCLINK%% and %%AUTHORLINK%%.
	 *
	 * @param string $template Footer template.
	 * @return string
	 */
	public function render_footer( string $template ): string {
		$post_id = (int) get_the_ID();
		$author  = (int) get_post_field( 'post_author', $post_id );

		$replacements = [
			'%%POSTLINK%%'     => '<a href="' . esc_url( (string) get_permalink( $post_id ) ) . '">' . esc_html( get_the_title( $post_id ) ) . '</a>',
			'%%BLOGLINK%%'     => '<a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html( get_bloginfo( 'name' ) ) . '</a>',
			'%%BLOGDESCLINK%%' => '<a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html( get_bloginfo( 'name' ) . ' - ' . get_bloginfo( 'description' ) ) . '</a>',
			'%%AUTHORLINK%%'   => $author ? '<a href="' . esc_url( get_author_posts_url( $author ) ) . '">' . esc_html( (string) get_the_author_meta( 'display_name', $author ) ) . '</a>' : '',
		];

		return wp_kses_post( strtr( $template, $replacements ) );
	}

	/**
	 * Append the footer to feed content.
	 *
	 * @param string $content Feed item content.
	 * @return string
	 */
	public function append_footer( $content ): string {
		if ( ! is_feed() ) {
			return (string) $content;
		}
		return (string) $content . "\n" . wpautop( $this->render_footer( (string) $this->settings['rss_after_content'] ) );
	}
}
