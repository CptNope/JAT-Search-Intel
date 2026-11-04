<?php
/**
 * Keep password-protected content out of front-end output.
 *
 * WordPress hides the body and excerpt of a password-protected post until the
 * visitor enters the password. Descriptions, schema, AI summaries and FAQ
 * markup generated from that body would publish it anyway, so every front-end
 * output reads content through these helpers. Values the author typed
 * explicitly as a meta description are still used.
 *
 * @package JAT_Search_Intel
 */

namespace JAT_Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Content guard.
 */
class Content_Guard {

	/**
	 * Whether a post has a password. Checked regardless of whether the current
	 * visitor has unlocked it, so caches never store protected text.
	 *
	 * @param \WP_Post|int|null $post Post.
	 * @return bool
	 */
	public static function is_protected( $post ): bool {
		$post = $post instanceof \WP_Post || is_object( $post ) ? $post : get_post( $post );
		return $post && '' !== (string) ( $post->post_password ?? '' );
	}

	/**
	 * Post content safe to derive public output from.
	 *
	 * @param \WP_Post|object $post Post.
	 * @return string
	 */
	public static function content( $post ): string {
		return self::is_protected( $post ) ? '' : (string) ( $post->post_content ?? '' );
	}

	/**
	 * Post excerpt safe to publish.
	 *
	 * @param \WP_Post|object $post Post.
	 * @return string
	 */
	public static function excerpt( $post ): string {
		return self::is_protected( $post ) ? '' : (string) ( $post->post_excerpt ?? '' );
	}
}
