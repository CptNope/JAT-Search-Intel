<?php
/**
 * Crawl cleanup: remove links and headers WordPress adds that search engines
 * don't need, and stop spam searches from producing indexable pages.
 *
 * Every option is off by default. Removing feed links doesn't disable the
 * feeds themselves; readers who know the URL can still use them.
 *
 * @package JAT_Search_Intel
 */

namespace JAT_Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Optional head, header and search cleanup.
 */
class Crawl_Cleanup {

	/** Setting keys, all off by default. Labels live in the settings view. */
	public const OPTIONS = [
		'cleanup_shortlink',
		'cleanup_rsd',
		'cleanup_wlwmanifest',
		'cleanup_generator',
		'cleanup_emoji',
		'cleanup_oembed_links',
		'cleanup_rest_link',
		'cleanup_pingback_header',
		'cleanup_feed_comments',
		'cleanup_feed_post_comments',
		'cleanup_feed_extra',
		'cleanup_search_spam',
	];

	/** Searches longer than this many characters count as spam. */
	public const MAX_SEARCH_LENGTH = 100;

	/** @var array<string,mixed> */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param array<string,mixed> $settings Plugin settings.
	 */
	public function __construct( array $settings ) {
		$this->settings = $settings;
		add_action( 'after_setup_theme', [ $this, 'apply' ], 99 );
	}

	/**
	 * Whether any cleanup option is on (the module is only built then).
	 *
	 * @param array<string,mixed> $settings Plugin settings.
	 * @return bool
	 */
	public static function any_enabled( array $settings ): bool {
		foreach ( self::OPTIONS as $key ) {
			if ( ! empty( $settings[ $key ] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Remove the selected output. Runs after the theme so theme re-additions are caught too.
	 */
	public function apply(): void {
		$on = function ( string $key ): bool {
			return ! empty( $this->settings[ $key ] );
		};

		if ( $on( 'cleanup_shortlink' ) ) {
			remove_action( 'wp_head', 'wp_shortlink_wp_head', 10 );
			remove_action( 'template_redirect', 'wp_shortlink_header', 11 );
		}
		if ( $on( 'cleanup_rsd' ) ) {
			remove_action( 'wp_head', 'rsd_link' );
		}
		if ( $on( 'cleanup_wlwmanifest' ) ) {
			remove_action( 'wp_head', 'wlwmanifest_link' );
		}
		if ( $on( 'cleanup_generator' ) ) {
			remove_action( 'wp_head', 'wp_generator' );
			add_filter( 'the_generator', '__return_empty_string' );
		}
		if ( $on( 'cleanup_emoji' ) ) {
			remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
			remove_action( 'wp_print_styles', 'print_emoji_styles' );
			remove_action( 'wp_enqueue_scripts', 'wp_enqueue_emoji_styles' );
			remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
			remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
			add_filter( 'emoji_svg_url', '__return_false' );
		}
		if ( $on( 'cleanup_oembed_links' ) ) {
			remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
		}
		if ( $on( 'cleanup_rest_link' ) ) {
			remove_action( 'wp_head', 'rest_output_link_wp_head', 10 );
			remove_action( 'template_redirect', 'rest_output_link_header', 11 );
		}
		if ( $on( 'cleanup_pingback_header' ) ) {
			add_filter( 'wp_headers', [ $this, 'remove_pingback_header' ] );
		}
		if ( $on( 'cleanup_feed_comments' ) ) {
			add_filter( 'feed_links_show_comments_feed', '__return_false' );
		}
		if ( $on( 'cleanup_feed_post_comments' ) || $on( 'cleanup_feed_extra' ) ) {
			// feed_links_extra prints both kinds; it is replaced by a version that leaves out what was switched off.
			remove_action( 'wp_head', 'feed_links_extra', 3 );
			add_action( 'wp_head', [ $this, 'feed_links_extra' ], 3 );
		}
		if ( $on( 'cleanup_search_spam' ) ) {
			add_action( 'wp', [ $this, 'block_spam_search' ] );
		}
	}

	/**
	 * Drop the X-Pingback header.
	 *
	 * @param array<string,string> $headers Headers.
	 * @return array<string,string>
	 */
	public function remove_pingback_header( $headers ): array {
		$headers = (array) $headers;
		unset( $headers['X-Pingback'] );
		return $headers;
	}

	/**
	 * Print the extra feed links WordPress would, minus the switched-off ones.
	 */
	public function feed_links_extra(): void {
		$is_post_comments = is_singular();
		if ( $is_post_comments && ! empty( $this->settings['cleanup_feed_post_comments'] ) ) {
			return;
		}
		if ( ! $is_post_comments && ! empty( $this->settings['cleanup_feed_extra'] ) ) {
			return;
		}
		feed_links_extra();
	}

	/**
	 * Whether a search query looks like spam.
	 *
	 * @param string $query Search terms.
	 * @return bool
	 */
	public static function is_spam_search( string $query ): bool {
		$query = trim( $query );
		if ( '' === $query ) {
			return false;
		}
		if ( mb_strlen( $query ) > self::MAX_SEARCH_LENGTH ) {
			return true;
		}
		// Links and domains are the usual payload of search-spam campaigns.
		return (bool) preg_match( '#(https?:|www\.|\.(?:com|net|org|ru|cn|xyz|top|info|biz)\b|<|>)#i', $query );
	}

	/**
	 * Turn spam searches into a 404 so they can't be crawled or indexed.
	 */
	public function block_spam_search(): void {
		global $wp_query;
		if ( ! is_search() || ! $wp_query instanceof \WP_Query ) {
			return;
		}
		if ( self::is_spam_search( (string) get_search_query( false ) ) ) {
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
		}
	}
}
