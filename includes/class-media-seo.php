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

	/** Admin page for images without alt text. */
	public const PAGE_SLUG = 'jat-search-intel-image-alt';

	private const NONCE_ACTION = 'jat_search_intel_image_alt';

	/** Casing choices for generated alt text and titles. */
	public const CASES = [ 'sentence', 'title', 'lower', 'none' ];

	/** @var array<string,mixed> */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param array<string,mixed> $settings Plugin settings.
	 */
	public function __construct( array $settings ) {
		$this->settings = $settings;

		if ( ! empty( $settings['enable_image_alt'] ) ) {
			add_action( 'add_attachment', [ $this, 'fill_alt_on_upload' ] );
			add_filter( 'wp_get_attachment_image_attributes', [ $this, 'filter_image_attributes' ], 10, 2 );
			add_filter( 'wp_content_img_tag', [ $this, 'filter_content_img_tag' ], 10, 3 );

			if ( is_admin() ) {
				add_action( 'admin_menu', [ $this, 'register_page' ] );
				add_action( 'admin_post_jat_search_intel_save_alt', [ $this, 'save_alt_text' ] );
			}
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
	 * Turn a file name or title into alt text using the site's rules.
	 *
	 * @param string              $raw      Title or file name.
	 * @param array<string,mixed> $settings Plugin settings (image_case, image_strip_punctuation).
	 * @return string
	 */
	public static function format( string $raw, array $settings ): string {
		$text = self::humanize( $raw );
		if ( '' === $text ) {
			return '';
		}

		if ( ! empty( $settings['image_strip_punctuation'] ) ) {
			// Keep letters, numbers, spaces, apostrophes and ampersands.
			$text = trim( (string) preg_replace( '/\s+/', ' ', (string) preg_replace( "/[^\\p{L}\\p{N}\\s'&]+/u", ' ', $text ) ) );
		}

		switch ( $settings['image_case'] ?? 'sentence' ) {
			case 'title':
				$small = [ 'a', 'an', 'and', 'as', 'at', 'but', 'by', 'for', 'in', 'of', 'on', 'or', 'the', 'to', 'with' ];
				$words = explode( ' ', function_exists( 'mb_strtolower' ) ? mb_strtolower( $text ) : strtolower( $text ) );
				foreach ( $words as $i => $word ) {
					if ( 0 === $i || ! in_array( $word, $small, true ) ) {
						$words[ $i ] = function_exists( 'mb_convert_case' ) ? mb_convert_case( $word, MB_CASE_TITLE ) : ucfirst( $word );
					}
				}
				$text = implode( ' ', $words );
				break;
			case 'lower':
				$text = function_exists( 'mb_strtolower' ) ? mb_strtolower( $text ) : strtolower( $text );
				break;
			case 'none':
				break;
			default:
				$lower = function_exists( 'mb_strtolower' ) ? mb_strtolower( $text ) : strtolower( $text );
				$text  = ucfirst( $lower );
		}

		return $text;
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
		return self::format( $title, $this->settings );
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

		// Tidy the attachment title the same way ("IMG_2041-final" becomes "Final").
		if ( ! empty( $this->settings['image_clean_title'] ) && '' !== $alt ) {
			wp_update_post(
				[
					'ID'         => $attachment_id,
					'post_title' => sanitize_text_field( $alt ),
				]
			);
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

	// ── Bulk alt text screen ─────────────────────────────────────────────────

	/**
	 * Register Media > Missing alt text.
	 */
	public function register_page(): void {
		add_media_page(
			__( 'Images without alt text', 'jat-search-intel' ),
			__( 'Missing alt text', 'jat-search-intel' ),
			'upload_files',
			self::PAGE_SLUG,
			[ $this, 'render_page' ]
		);
	}

	/**
	 * Image IDs without alt text.
	 *
	 * @param int $page     Page number.
	 * @param int $per_page Per page.
	 * @return array{ids:int[],total:int}
	 */
	public function images_without_alt( int $page = 1, int $per_page = 40 ): array {
		$query = new \WP_Query(
			[
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_mime_type' => 'image',
				'posts_per_page' => $per_page,
				'paged'          => max( 1, $page ),
				'fields'         => 'ids',
				'orderby'        => 'date',
				'order'          => 'DESC',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Admin-only screen, paginated.
				'meta_query'     => [
					'relation' => 'OR',
					[
						'key'     => '_wp_attachment_image_alt',
						'compare' => 'NOT EXISTS',
					],
					[
						'key'   => '_wp_attachment_image_alt',
						'value' => '',
					],
				],
			]
		);
		return [
			'ids'   => array_map( 'intval', (array) $query->posts ),
			'total' => (int) $query->found_posts,
		];
	}

	/**
	 * The screen: each image with a suggested alt text to accept or edit.
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'upload_files' ) ) {
			return;
		}
		$paged = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination.
		$list  = $this->images_without_alt( $paged );
		$saved = isset( $_GET['saved'] ) ? absint( $_GET['saved'] ) : -1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice after redirect.

		echo '<div class="wrap"><h1>' . esc_html__( 'Images without alt text', 'jat-search-intel' ) . '</h1>';
		echo '<p>' . esc_html__( 'Alt text describes an image for screen readers and search engines. Each image has a suggestion from its title or file name: edit it to describe what the image shows, then save. Leave a box empty to skip that image.', 'jat-search-intel' ) . '</p>';

		if ( $saved >= 0 ) {
			/* translators: %d: number of images */
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sprintf( _n( 'Saved alt text for %d image.', 'Saved alt text for %d images.', $saved, 'jat-search-intel' ), $saved ) ) . '</p></div>';
		}

		if ( ! $list['ids'] ) {
			echo '<p><strong>' . esc_html__( 'Every image has alt text.', 'jat-search-intel' ) . '</strong></p></div>';
			return;
		}

		/* translators: %d: number of images */
		echo '<p>' . esc_html( sprintf( _n( '%d image needs alt text.', '%d images need alt text.', $list['total'], 'jat-search-intel' ), $list['total'] ) ) . '</p>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="jat_search_intel_save_alt">';
		echo '<input type="hidden" name="paged" value="' . esc_attr( (string) $paged ) . '">';
		wp_nonce_field( self::NONCE_ACTION );
		echo '<table class="widefat striped"><thead><tr><th style="width:110px">' . esc_html__( 'Image', 'jat-search-intel' ) . '</th><th>' . esc_html__( 'File', 'jat-search-intel' ) . '</th><th>' . esc_html__( 'Alt text', 'jat-search-intel' ) . '</th></tr></thead><tbody>';
		foreach ( $list['ids'] as $id ) {
			if ( ! current_user_can( 'edit_post', $id ) ) {
				continue;
			}
			$file = (string) get_attached_file( $id );
			printf(
				'<tr><td>%1$s</td><td>%2$s<br><a href="%3$s">%4$s</a></td><td><label class="screen-reader-text" for="jsi-alt-%5$d">%6$s</label><input type="text" class="large-text" id="jsi-alt-%5$d" name="alt[%5$d]" value="%7$s"></td></tr>',
				wp_get_attachment_image( $id, [ 96, 96 ], false, [ 'alt' => '' ] ),
				esc_html( $file ? wp_basename( $file ) : get_the_title( $id ) ),
				esc_url( (string) get_edit_post_link( $id ) ),
				esc_html__( 'Edit image', 'jat-search-intel' ),
				(int) $id,
				esc_html__( 'Alt text', 'jat-search-intel' ),
				esc_attr( $this->derived_alt( $id ) )
			);
		}
		echo '</tbody></table>';
		submit_button( __( 'Save alt text', 'jat-search-intel' ) );
		echo '</form>';

		$pages = (int) ceil( $list['total'] / 40 );
		if ( $pages > 1 ) {
			echo '<div class="tablenav"><div class="tablenav-pages">';
			echo wp_kses_post( (string) paginate_links( [
				'base'    => add_query_arg( 'paged', '%#%' ),
				'format'  => '',
				'current' => $paged,
				'total'   => $pages,
			] ) );
			echo '</div></div>';
		}
		echo '</div>';
	}

	/**
	 * Save the alt text form.
	 */
	public function save_alt_text(): void {
		check_admin_referer( self::NONCE_ACTION );
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_die( esc_html__( 'You do not have permission to edit images.', 'jat-search-intel' ), 403 );
		}

		$count = 0;
		$items = isset( $_POST['alt'] ) && is_array( $_POST['alt'] ) ? wp_unslash( $_POST['alt'] ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each value is sanitized below.
		foreach ( $items as $id => $alt ) {
			$id  = absint( $id );
			$alt = sanitize_text_field( (string) $alt );
			if ( ! $id || '' === $alt || 'attachment' !== get_post_type( $id ) || ! current_user_can( 'edit_post', $id ) ) {
				continue;
			}
			update_post_meta( $id, '_wp_attachment_image_alt', $alt );
			$count++;
		}

		$paged = isset( $_POST['paged'] ) ? absint( $_POST['paged'] ) : 1;
		wp_safe_redirect( add_query_arg( [ 'page' => self::PAGE_SLUG, 'saved' => $count, 'paged' => $paged ], admin_url( 'upload.php' ) ) );
		exit;
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
