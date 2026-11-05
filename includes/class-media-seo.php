<?php
/**
 * Image and attachment SEO: automatic alt text and attachment-page redirects.
 *
 * @package JAT_Search_Intel
 */

namespace JAT_Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Media SEO helpers.
 */
class Media_SEO {

	/**
	 * Constructor.
	 *
	 * @param array<string,mixed> $settings Plugin settings.
	 */
	public function __construct( array $settings ) {
		if ( ! empty( $settings['enable_image_alt'] ) ) {
			add_action( 'add_attachment', [ $this, 'fill_alt_on_upload' ] );
			add_filter( 'wp_get_attachment_image_attributes', [ $this, 'filter_image_attributes' ], 10, 2 );
			add_filter( 'wp_content_img_tag', [ $this, 'filter_content_img_tag' ], 10, 3 );
		}

		if ( ! empty( $settings['redirect_attachments'] ) ) {
			add_action( 'template_redirect', [ $this, 'redirect_attachment_page' ], 1 );
		}
	}

	/**
	 * Turn a file name or title into readable alt text.
	 *
	 * @param string $raw Title or file name.
	 * @return string
	 */
	public static function humanize( string $raw ): string {
		$text = preg_replace( '/\.[a-z0-9]{2,5}$/i', '', $raw );
		$text = preg_replace( '/[-_]+/', ' ', (string) $text );
		$text = preg_replace( '/\b(?:img|image|dsc|screenshot|scaled|\d{2,4}x\d{2,4})\b/i', ' ', (string) $text );
		$text = preg_replace( '/\s+/', ' ', (string) $text );
		$text = trim( (string) $text );

		return '' === $text ? '' : ucfirst( $text );
	}

	/**
	 * Alt text derived from an attachment's title.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string
	 */
	private function derived_alt( int $attachment_id ): string {
		$title = (string) get_the_title( $attachment_id );
		if ( '' === $title ) {
			$file  = (string) get_attached_file( $attachment_id );
			$title = $file ? wp_basename( $file ) : '';
		}
		return self::humanize( $title );
	}

	/**
	 * Store alt text for new images that were uploaded without one.
	 *
	 * @param int $attachment_id Attachment ID.
	 */
	public function fill_alt_on_upload( int $attachment_id ): void {
		if ( ! wp_attachment_is_image( $attachment_id ) ) {
			return;
		}
		if ( '' !== (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ) {
			return;
		}
		$alt = $this->derived_alt( $attachment_id );
		if ( '' !== $alt ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $alt ) );
		}
	}

	/**
	 * Add alt text to wp_get_attachment_image() output when it is missing.
	 *
	 * @param array<string,string> $attr       Image attributes.
	 * @param \WP_Post             $attachment Attachment post.
	 * @return array<string,string>
	 */
	public function filter_image_attributes( $attr, $attachment ): array {
		$attr = (array) $attr;
		if ( empty( $attr['alt'] ) && $attachment instanceof \WP_Post ) {
			$alt = $this->derived_alt( $attachment->ID );
			if ( '' !== $alt ) {
				$attr['alt'] = $alt;
			}
		}
		return $attr;
	}

	/**
	 * Add alt text to images inside post content when it is missing or empty.
	 *
	 * @param string $filtered_image Full img tag.
	 * @param string $context        Context.
	 * @param int    $attachment_id  Attachment ID (0 if unknown).
	 * @return string
	 */
	public function filter_content_img_tag( $filtered_image, $context, $attachment_id ): string {
		$filtered_image = (string) $filtered_image;
		$attachment_id  = (int) $attachment_id;

		if ( ! $attachment_id || preg_match( '/\salt="[^"]+"/i', $filtered_image ) ) {
			return $filtered_image;
		}

		$alt = (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
		if ( '' === $alt ) {
			$alt = $this->derived_alt( $attachment_id );
		}
		if ( '' === $alt ) {
			return $filtered_image;
		}

		$attr = ' alt="' . esc_attr( $alt ) . '"';
		if ( preg_match( '/\salt=""/i', $filtered_image ) ) {
			return (string) preg_replace( '/\salt=""/i', $attr, $filtered_image, 1 );
		}
		return (string) preg_replace( '/^<img\b/i', '<img' . $attr, $filtered_image, 1 );
	}

	/**
	 * Send attachment pages to their parent post (or the file itself).
	 */
	public function redirect_attachment_page(): void {
		if ( ! is_attachment() ) {
			return;
		}

		$attachment_id = get_queried_object_id();
		$parent_id     = (int) wp_get_post_parent_id( $attachment_id );
		$target        = '';

		if ( $parent_id && 'publish' === get_post_status( $parent_id ) ) {
			$target = (string) get_permalink( $parent_id );
		}
		if ( '' === $target ) {
			$target = (string) wp_get_attachment_url( $attachment_id );
		}

		if ( '' !== $target ) {
			wp_safe_redirect( $target, 301, 'JAT Search Intel' );
			exit;
		}
	}
}
