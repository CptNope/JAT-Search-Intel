<?php
/**
 * Estimated reading time.
 *
 * Used for Article schema (timeRequired), the "Est. reading time" label that
 * Slack, X and other apps show in link previews, the editor, and the
 * [jat_search_intel_reading_time] shortcode.
 *
 * @package JAT_Search_Intel
 */

namespace JAT_Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reading time estimates.
 */
class Reading_Time {

	/** Average silent reading speed for non-fiction in English (Brysbaert, 2019). */
	public const WORDS_PER_MINUTE = 238;

	/** Characters per minute for Chinese, Japanese and Korean text. */
	public const CJK_CHARS_PER_MINUTE = 500;

	/**
	 * Minutes needed to read some HTML or text (at least 1 for any content).
	 *
	 * @param string $content Content.
	 * @return int
	 */
	public static function minutes_for( string $content ): int {
		$text = trim( wp_strip_all_tags( strip_shortcodes( $content ) ) );
		if ( '' === $text ) {
			return 0;
		}

		$cjk = (int) preg_match_all( '/[\x{3040}-\x{30FF}\x{3400}-\x{4DBF}\x{4E00}-\x{9FFF}\x{AC00}-\x{D7AF}]/u', $text );
		if ( $cjk > 0 ) {
			// Count CJK characters, plus words in any other script mixed in.
			$rest  = (string) preg_replace( '/[\x{3040}-\x{30FF}\x{3400}-\x{4DBF}\x{4E00}-\x{9FFF}\x{AC00}-\x{D7AF}]/u', ' ', $text );
			$words = count( preg_split( '/\s+/u', trim( $rest ), -1, PREG_SPLIT_NO_EMPTY ) ?: [] );
			return max( 1, (int) ceil( $cjk / self::CJK_CHARS_PER_MINUTE + $words / self::WORDS_PER_MINUTE ) );
		}

		$words = count( preg_split( '/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY ) ?: [] );
		return max( 1, (int) ceil( $words / self::WORDS_PER_MINUTE ) );
	}

	/**
	 * Minutes needed to read a post (0 when it is empty or password protected).
	 *
	 * @param \WP_Post|int $post Post.
	 * @return int
	 */
	public static function minutes( $post ): int {
		$post = get_post( $post );
		if ( ! $post instanceof \WP_Post ) {
			return 0;
		}
		return self::minutes_for( Content_Guard::content( $post ) );
	}

	/**
	 * "5 min read" label.
	 *
	 * @param int $minutes Minutes.
	 * @return string
	 */
	public static function label( int $minutes ): string {
		/* translators: %d: number of minutes. */
		return sprintf( _n( '%d minute', '%d minutes', $minutes, 'jat-search-intel' ), $minutes );
	}

	/**
	 * [jat_search_intel_reading_time] shortcode: "4 min read".
	 *
	 * @param array<string,string>|string $atts Attributes: before, after.
	 * @return string
	 */
	public static function shortcode( $atts ): string {
		$atts    = shortcode_atts(
			[
				'before' => '',
				/* translators: Shown after the reading time, as in "4 min read". */
				'after'  => __( 'read', 'jat-search-intel' ),
			],
			(array) $atts,
			'jat_search_intel_reading_time'
		);
		$minutes = self::minutes( get_the_ID() ?: 0 );
		if ( $minutes < 1 ) {
			return '';
		}
		/* translators: %d: number of minutes, abbreviated as in "4 min". */
		$text = trim( $atts['before'] . ' ' . sprintf( __( '%d min', 'jat-search-intel' ), $minutes ) . ' ' . $atts['after'] );
		return '<span class="jsi-reading-time">' . esc_html( $text ) . '</span>';
	}
}
