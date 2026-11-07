<?php
/**
 * Freeze a post's "last modified" date.
 *
 * Fixing a typo or a link shouldn't tell search engines the post was updated.
 * While "Don't update the last-modified date" is on for a post, saving it keeps
 * the previous modified date, which feeds dateModified in schema, the sitemap
 * lastmod and the article:modified_time tag.
 *
 * @package JAT_Search_Intel
 */

namespace JAT_Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Modified-date freeze.
 */
class Modified_Date {

	/** Post meta key ('1' = frozen). */
	public const META_KEY = '_jat_search_intel_freeze_modified';

	/** @var array<int,bool> Freeze value sent with a REST save, by post ID. */
	private static $rest_values = [];

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'wp_insert_post_data', [ $this, 'keep_modified_date' ], 99, 4 );
		add_filter( 'rest_request_before_callbacks', [ $this, 'capture_rest_value' ], 10, 3 );
	}

	/**
	 * Remember the freeze value the block editor sends with a save. Post meta from a
	 * REST request is written after the post itself, so it can't be read back in time.
	 *
	 * @param mixed            $response Response so far.
	 * @param mixed            $handler  Route handler.
	 * @param \WP_REST_Request $request  Request.
	 * @return mixed
	 */
	public function capture_rest_value( $response, $handler, $request ) {
		unset( $handler );
		if ( $request instanceof \WP_REST_Request && in_array( $request->get_method(), [ 'POST', 'PUT', 'PATCH' ], true ) ) {
			$meta = $request->get_param( 'meta' );
			$id   = (int) $request->get_param( 'id' );
			if ( $id > 0 && is_array( $meta ) && array_key_exists( self::META_KEY, $meta ) ) {
				self::$rest_values[ $id ] = ! empty( $meta[ self::META_KEY ] );
			}
		}
		return $response;
	}

	/**
	 * Whether this save of a post should keep its modified date.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	private function is_frozen( int $post_id ): bool {
		if ( isset( self::$rest_values[ $post_id ] ) ) {
			return self::$rest_values[ $post_id ];
		}

		// Classic editor: the meta box checkbox, trusted only with a valid meta box nonce.
		if ( isset( $_POST['jat_search_intel_metabox_nonce'] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['jat_search_intel_metabox_nonce'] ) ), 'jat_search_intel_metabox' )
			&& current_user_can( 'edit_post', $post_id ) ) {
			return ! empty( $_POST['si_freeze_modified'] );
		}

		return '1' === (string) get_post_meta( $post_id, self::META_KEY, true );
	}

	/**
	 * Keep the stored modified date when the post is frozen.
	 *
	 * @param array<string,mixed> $data                Slashed post data to be saved.
	 * @param array<string,mixed> $postarr             Raw post array.
	 * @param array<string,mixed> $unsanitized_postarr Unsanitized post array.
	 * @param bool                $update              Whether this updates an existing post.
	 * @return array<string,mixed>
	 */
	public function keep_modified_date( $data, $postarr, $unsanitized_postarr = [], $update = false ): array {
		unset( $unsanitized_postarr );
		$data    = (array) $data;
		$post_id = (int) ( $postarr['ID'] ?? 0 );
		if ( ! $update || $post_id <= 0 || ! $this->is_frozen( $post_id ) ) {
			return $data;
		}

		$old = get_post( $post_id );
		if ( ! $old instanceof \WP_Post || 'publish' !== $old->post_status ) {
			return $data;
		}

		$data['post_modified']     = $old->post_modified;
		$data['post_modified_gmt'] = $old->post_modified_gmt;
		return $data;
	}
}
