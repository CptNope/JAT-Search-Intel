<?php
namespace JAT_Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Link-preview copies of images for Open Graph and X cards.
 *
 * Messaging apps are stricter than Facebook: WhatsApp skips preview images
 * over about 600 KB, and some apps still don't show WebP. A site that shares
 * a 1 MB PNG or a WebP featured image gets a link with no picture. This keeps
 * one copy per image, at most 1200 px on its long side, saved as JPEG (or as
 * PNG when the source has transparency), stored as an extra size of the
 * attachment so WordPress deletes it with the image.
 *
 * Return false from `jat_search_intel_share_image` to use original images.
 */
class Share_Image {

	/** Attachment size key for the copy. */
	const SIZE = 'jat-search-intel-share';

	/** Longest side of the copy, in pixels. */
	const MAX_SIDE = 1200;

	/** A JPEG source this size or smaller, and no wider than MAX_SIDE, is used as it is. */
	const MAX_BYTES = 307200;

	/**
	 * Preview image data for an image URL: the share copy when the URL is an
	 * attachment, else the URL as given.
	 *
	 * @param string $url    Image URL.
	 * @param string $alt    Alt text.
	 * @param bool   $create Make the copy now if it is missing.
	 * @return array{url:string,width:int,height:int,type:string,alt:string}
	 */
	public static function for_url( string $url, string $alt = '', bool $create = true ): array {
		$attachment_id = $url ? attachment_url_to_postid( $url ) : 0;
		if ( $attachment_id ) {
			$image = self::for_attachment( $attachment_id, $alt, $create );
			if ( $image ) {
				return $image;
			}
		}
		return [
			'url'    => $url,
			'width'  => 0,
			'height' => 0,
			'type'   => self::mime_from_url( $url ),
			'alt'    => $alt,
		];
	}

	/**
	 * Preview image data for an attachment.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $alt           Alt text; the attachment's own when empty.
	 * @param bool   $create        Make the copy now if it is missing.
	 * @return array{url:string,width:int,height:int,type:string,alt:string}|null
	 */
	public static function for_attachment( int $attachment_id, string $alt = '', bool $create = true ): ?array {
		if ( '' === $alt ) {
			$alt = (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
		}

		$meta = wp_get_attachment_metadata( $attachment_id );
		$meta = is_array( $meta ) ? $meta : [];

		if ( apply_filters( 'jat_search_intel_share_image', true, $attachment_id ) ) {
			$copy = self::existing_copy( $attachment_id, $meta );
			if ( ! $copy && $create ) {
				$copy = self::create( $attachment_id, $meta );
			}
			if ( $copy ) {
				$copy['alt'] = $alt;
				return $copy;
			}
		}

		$full = wp_get_attachment_image_src( $attachment_id, 'full' );
		if ( ! $full ) {
			return null;
		}
		return [
			'url'    => $full[0],
			'width'  => (int) $full[1],
			'height' => (int) $full[2],
			'type'   => (string) ( get_post_mime_type( $attachment_id ) ?: self::mime_from_url( $full[0] ) ),
			'alt'    => $alt,
		];
	}

	/**
	 * Whether the source can be shared as it is.
	 *
	 * @param string $mime   Source MIME type.
	 * @param int    $width  Width.
	 * @param int    $height Height.
	 * @param int    $bytes  File size.
	 * @return bool
	 */
	public static function source_is_fine( string $mime, int $width, int $height, int $bytes ): bool {
		return 'image/jpeg' === $mime
			&& $width > 0 && $height > 0
			&& max( $width, $height ) <= self::MAX_SIDE
			&& $bytes > 0 && $bytes <= self::MAX_BYTES;
	}

	/**
	 * Whether a PNG file has an alpha channel or a transparent colour.
	 *
	 * @param string $head The first bytes of the file (64 KB is plenty).
	 * @return bool
	 */
	public static function png_has_alpha( string $head ): bool {
		if ( strlen( $head ) < 26 || "\x89PNG\r\n\x1a\n" !== substr( $head, 0, 8 ) ) {
			return false;
		}
		// IHDR colour type: 4 = grey + alpha, 6 = RGBA.
		$color_type = ord( $head[25] );
		if ( 4 === $color_type || 6 === $color_type ) {
			return true;
		}
		// Palette or grey images can still mark a colour transparent.
		return false !== strpos( $head, 'tRNS' );
	}

	/**
	 * The share copy recorded in the attachment's metadata, if its file exists.
	 *
	 * @param int   $attachment_id Attachment ID.
	 * @param array $meta          Attachment metadata.
	 * @return array{url:string,width:int,height:int,type:string,alt:string}|null
	 */
	private static function existing_copy( int $attachment_id, array $meta ): ?array {
		$size = $meta['sizes'][ self::SIZE ] ?? null;
		if ( ! is_array( $size ) || empty( $size['file'] ) ) {
			return null;
		}
		$file = (string) get_attached_file( $attachment_id );
		if ( '' === $file || ! file_exists( dirname( $file ) . '/' . $size['file'] ) ) {
			return null;
		}
		$url = (string) wp_get_attachment_url( $attachment_id );
		if ( '' === $url ) {
			return null;
		}
		return [
			'url'    => dirname( $url ) . '/' . rawurlencode( (string) $size['file'] ),
			'width'  => (int) ( $size['width'] ?? 0 ),
			'height' => (int) ( $size['height'] ?? 0 ),
			'type'   => (string) ( $size['mime-type'] ?? 'image/jpeg' ),
			'alt'    => '',
		];
	}

	/**
	 * Make the share copy and record it in the attachment's metadata.
	 *
	 * @param int   $attachment_id Attachment ID.
	 * @param array $meta          Attachment metadata.
	 * @return array{url:string,width:int,height:int,type:string,alt:string}|null Null when the source is fine as it is or the copy can't be made.
	 */
	public static function create( int $attachment_id, array $meta ): ?array {
		if ( ! function_exists( 'wp_get_image_editor' ) ) {
			return null;
		}

		$attached = (string) get_attached_file( $attachment_id );
		$source   = function_exists( 'wp_get_original_image_path' ) ? (string) wp_get_original_image_path( $attachment_id ) : '';
		if ( '' === $source || ! file_exists( $source ) ) {
			$source = $attached;
		}
		if ( '' === $source || ! file_exists( $source ) ) {
			return null;
		}

		$mime   = (string) get_post_mime_type( $attachment_id );
		$width  = (int) ( $meta['width'] ?? 0 );
		$height = (int) ( $meta['height'] ?? 0 );
		if ( self::source_is_fine( $mime, $width, $height, (int) filesize( $attached ) ) ) {
			return null;
		}

		// One request makes the copy; others share the original meanwhile.
		$lock = 'jat_si_share_' . $attachment_id;
		if ( get_transient( $lock ) ) {
			return null;
		}
		set_transient( $lock, 1, MINUTE_IN_SECONDS );

		$editor = wp_get_image_editor( $source );
		if ( is_wp_error( $editor ) ) {
			delete_transient( $lock );
			return null;
		}

		$out_mime = 'image/jpeg';
		if ( 'image/png' === $mime || 'image/gif' === $mime || 'image/webp' === $mime ) {
			$fh   = fopen( $source, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
			$head = $fh ? (string) fread( $fh, 65536 ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
			if ( $fh ) {
				fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			}
			if ( 'image/png' === $mime && self::png_has_alpha( $head ) ) {
				$out_mime = 'image/png';
			} elseif ( 'image/png' !== $mime && self::has_transparency_hint( $mime, $head ) ) {
				$out_mime = 'image/png';
			}
		}

		$size = $editor->get_size();
		if ( max( (int) $size['width'], (int) $size['height'] ) > self::MAX_SIDE ) {
			$editor->resize( self::MAX_SIDE, self::MAX_SIDE, false );
		}
		if ( 'image/jpeg' === $out_mime ) {
			$editor->set_quality( 82 );
		}

		$ext  = 'image/png' === $out_mime ? 'png' : 'jpg';
		$dest = dirname( $attached ) . '/' . pathinfo( $attached, PATHINFO_FILENAME ) . '-share.' . $ext;

		// Keep the format asked for, even when a theme maps JPEG and PNG to WebP.
		$keep = static function ( $formats ) use ( $out_mime ) {
			if ( is_array( $formats ) ) {
				unset( $formats[ $out_mime ] );
			}
			return $formats;
		};
		add_filter( 'image_editor_output_format', $keep, PHP_INT_MAX );
		$saved = $editor->save( $dest, $out_mime );
		remove_filter( 'image_editor_output_format', $keep, PHP_INT_MAX );
		delete_transient( $lock );

		if ( is_wp_error( $saved ) || empty( $saved['file'] ) ) {
			return null;
		}

		$meta['sizes']                = isset( $meta['sizes'] ) && is_array( $meta['sizes'] ) ? $meta['sizes'] : [];
		$meta['sizes'][ self::SIZE ] = [
			'file'      => wp_basename( (string) $saved['path'] ),
			'width'     => (int) $saved['width'],
			'height'    => (int) $saved['height'],
			'mime-type' => (string) $saved['mime-type'],
			'filesize'  => file_exists( (string) $saved['path'] ) ? (int) filesize( (string) $saved['path'] ) : 0,
		];
		wp_update_attachment_metadata( $attachment_id, $meta );

		return self::existing_copy( $attachment_id, $meta );
	}

	/**
	 * Make the copy when a post's featured image or social image is set, so the
	 * first visitor or link crawler doesn't wait for it.
	 *
	 * @param int    $meta_id    Meta ID.
	 * @param int    $object_id  Post ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value New value.
	 */
	public static function on_meta_change( $meta_id, $object_id, $meta_key, $meta_value ): void {
		unset( $meta_id, $object_id );
		if ( '_thumbnail_id' === $meta_key ) {
			$attachment_id = (int) $meta_value;
		} elseif ( in_array( $meta_key, [ '_jat_search_intel_social_image', '_jat_search_intel_og_image', '_jat_search_intel_twitter_image' ], true ) && is_string( $meta_value ) && '' !== $meta_value ) {
			$attachment_id = attachment_url_to_postid( $meta_value );
		} else {
			return;
		}
		if ( $attachment_id > 0 && apply_filters( 'jat_search_intel_share_image', true, $attachment_id ) ) {
			$meta = wp_get_attachment_metadata( $attachment_id );
			if ( is_array( $meta ) && ! self::existing_copy( $attachment_id, $meta ) ) {
				self::create( $attachment_id, $meta );
			}
		}
	}

	/**
	 * GIF and WebP sources: keep transparency when the file says it has some.
	 *
	 * @param string $mime MIME type.
	 * @param string $head First bytes of the file.
	 * @return bool
	 */
	private static function has_transparency_hint( string $mime, string $head ): bool {
		if ( 'image/webp' === $mime ) {
			// VP8X header flag bit 4 marks alpha; VP8L (lossless) may carry alpha too.
			if ( 'VP8X' === substr( $head, 12, 4 ) && strlen( $head ) > 20 ) {
				return (bool) ( ord( $head[20] ) & 0x10 );
			}
			return 'VP8L' === substr( $head, 12, 4 );
		}
		if ( 'image/gif' === $mime ) {
			return true;
		}
		return false;
	}

	/**
	 * MIME type guessed from a URL's extension.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	public static function mime_from_url( string $url ): string {
		$ext = strtolower( (string) pathinfo( (string) wp_parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
		$map = [
			'jpg'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'png'  => 'image/png',
			'gif'  => 'image/gif',
			'webp' => 'image/webp',
			'avif' => 'image/avif',
		];
		return $map[ $ext ] ?? '';
	}
}
