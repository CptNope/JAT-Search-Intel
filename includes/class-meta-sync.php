<?php
/**
 * Keep the block-editor sidebar's meta keys and the keys the front end reads in step.
 *
 * The sidebar stores some fields under different names from the classic meta box
 * and the output code (for example `_canonical_url` vs `_canonical`). Writing either
 * key now writes its partner, so a value set in one place is used everywhere.
 *
 * @package JAT_Search_Intel
 */

namespace JAT_Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Two-way post meta sync.
 */
class Meta_Sync {

	/** Sidebar key => key read by the front end. */
	public const PAIRS = [
		'_jat_search_intel_canonical_url'   => '_jat_search_intel_canonical',
		'_jat_search_intel_og_title'        => '_jat_search_intel_social_title',
		'_jat_search_intel_og_description'  => '_jat_search_intel_social_description',
		'_jat_search_intel_og_image'        => '_jat_search_intel_social_image',
		'_jat_search_intel_target_audience' => '_jat_search_intel_audience',
		'_jat_search_intel_fact_check_date' => '_jat_search_intel_fact_checked',
		'_jat_search_intel_key_topics'      => '_jat_search_intel_topics',
	];

	/** Keys stored as arrays on the front-end side and as comma-separated text in the sidebar. */
	private const LIST_KEYS = [ '_jat_search_intel_topics' ];

	/** @var bool Re-entrancy guard. */
	private static $syncing = false;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'added_post_meta', [ $this, 'on_write' ], 10, 4 );
		add_action( 'updated_post_meta', [ $this, 'on_write' ], 10, 4 );
		add_action( 'deleted_post_meta', [ $this, 'on_delete' ], 10, 3 );
	}

	/**
	 * Partner key for a key, or ''.
	 *
	 * @param string $key Meta key.
	 * @return string
	 */
	public static function partner( string $key ): string {
		if ( isset( self::PAIRS[ $key ] ) ) {
			return self::PAIRS[ $key ];
		}
		$flipped = array_flip( self::PAIRS );
		return $flipped[ $key ] ?? '';
	}

	/**
	 * Convert a value for storage under $to_key.
	 *
	 * @param mixed  $value  Value.
	 * @param string $to_key Destination key.
	 * @return mixed
	 */
	public static function convert( $value, string $to_key ) {
		if ( in_array( $to_key, self::LIST_KEYS, true ) ) {
			if ( is_array( $value ) ) {
				return array_values( array_filter( array_map( 'trim', array_map( 'strval', $value ) ) ) );
			}
			return array_values( array_filter( array_map( 'trim', explode( ',', (string) $value ) ) ) );
		}
		if ( is_array( $value ) ) {
			return implode( ', ', array_filter( array_map( 'strval', $value ) ) );
		}
		return $value;
	}

	/**
	 * Mirror a write.
	 *
	 * @param int    $meta_id    Meta ID.
	 * @param int    $post_id    Post ID.
	 * @param string $meta_key   Key.
	 * @param mixed  $meta_value Value.
	 */
	public function on_write( $meta_id, $post_id, $meta_key, $meta_value ): void {
		unset( $meta_id );
		$partner = self::partner( (string) $meta_key );
		if ( '' === $partner || self::$syncing ) {
			return;
		}
		self::$syncing = true;
		$value         = self::convert( $meta_value, $partner );
		if ( '' === $value || [] === $value ) {
			delete_post_meta( (int) $post_id, $partner );
		} else {
			update_post_meta( (int) $post_id, $partner, $value );
		}
		self::$syncing = false;
	}

	/**
	 * Mirror a delete.
	 *
	 * @param int[]  $meta_ids Meta IDs.
	 * @param int    $post_id  Post ID.
	 * @param string $meta_key Key.
	 */
	public function on_delete( $meta_ids, $post_id, $meta_key ): void {
		unset( $meta_ids );
		$partner = self::partner( (string) $meta_key );
		if ( '' === $partner || self::$syncing ) {
			return;
		}
		self::$syncing = true;
		delete_post_meta( (int) $post_id, $partner );
		self::$syncing = false;
	}

	/**
	 * One-off backfill: copy sidebar-only values to the front-end keys (and back) where
	 * the other side is empty. Runs on plugin update.
	 */
	public static function backfill(): void {
		global $wpdb;

		foreach ( self::PAIRS as $alias => $canonical ) {
			foreach ( [ [ $alias, $canonical ], [ $canonical, $alias ] ] as list( $from, $to ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off upgrade routine.
				$rows = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT a.post_id, a.meta_value FROM {$wpdb->postmeta} a
						LEFT JOIN {$wpdb->postmeta} b ON b.post_id = a.post_id AND b.meta_key = %s
						WHERE a.meta_key = %s AND a.meta_value <> '' AND ( b.meta_id IS NULL OR b.meta_value = '' )
						LIMIT 5000",
						$to,
						$from
					)
				);
				self::$syncing = true;
				foreach ( (array) $rows as $row ) {
					update_post_meta( (int) $row->post_id, $to, self::convert( maybe_unserialize( $row->meta_value ), $to ) );
				}
				self::$syncing = false;
			}
		}
	}
}
