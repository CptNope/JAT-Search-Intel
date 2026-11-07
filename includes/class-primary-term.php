<?php
/**
 * Primary category (or primary term of any hierarchical taxonomy) for a post.
 *
 * When a post is in several categories, the primary one is used for its
 * permalink (%category%), breadcrumbs, the article section in Open Graph and
 * schema, and the %category% title variable. Without a choice, the category
 * WordPress itself would put in the permalink (lowest term ID) is used.
 *
 * @package JAT_Search_Intel
 */

namespace JAT_Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Primary term lookups and permalink filters.
 */
class Primary_Term {

	/** Post meta key prefix; the taxonomy name follows it. */
	public const META_PREFIX = '_jat_search_intel_primary_';

	/**
	 * Meta key for a taxonomy.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @return string
	 */
	public static function meta_key( string $taxonomy ): string {
		return self::META_PREFIX . sanitize_key( $taxonomy );
	}

	/**
	 * The primary term of a post in a taxonomy.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $taxonomy Taxonomy.
	 * @return \WP_Term|null Null when the post has no terms in the taxonomy.
	 */
	public static function get_term( int $post_id, string $taxonomy = 'category' ): ?\WP_Term {
		if ( $post_id <= 0 ) {
			return null;
		}
		// get_the_category() applies the get_the_categories filter, like core's own templates.
		$terms = 'category' === $taxonomy ? get_the_category( $post_id ) : get_the_terms( $post_id, $taxonomy );
		if ( ! is_array( $terms ) ) {
			return null;
		}
		$terms = array_values(
			array_filter(
				$terms,
				static function ( $term ): bool {
					return $term instanceof \WP_Term;
				}
			)
		);
		if ( ! $terms ) {
			return null;
		}

		$chosen = (int) get_post_meta( $post_id, self::meta_key( $taxonomy ), true );
		foreach ( $terms as $term ) {
			if ( $chosen && (int) $term->term_id === $chosen ) {
				return $term;
			}
		}

		// No (valid) choice: the term WordPress uses in permalinks, the one with the lowest ID.
		usort(
			$terms,
			static function ( \WP_Term $a, \WP_Term $b ): int {
				return (int) $a->term_id <=> (int) $b->term_id;
			}
		);
		return $terms[0];
	}

	/**
	 * Use the primary category for %category% in post permalinks.
	 *
	 * @param \WP_Term|mixed $category Category WordPress picked.
	 * @param \WP_Term[]     $cats     The post's categories.
	 * @param \WP_Post|mixed $post     Post.
	 * @return \WP_Term|mixed
	 */
	public static function filter_post_link_category( $category, $cats, $post ) {
		return self::pick( $category, $cats, $post, 'category' );
	}

	/**
	 * Use the primary product category in WooCommerce product permalinks.
	 *
	 * @param \WP_Term|mixed $term  Term WooCommerce picked.
	 * @param \WP_Term[]     $terms The product's categories.
	 * @param \WP_Post|mixed $post  Product post.
	 * @return \WP_Term|mixed
	 */
	public static function filter_product_link_category( $term, $terms, $post ) {
		return self::pick( $term, $terms, $post, 'product_cat' );
	}

	/**
	 * The primary term from a list, or the default when none is chosen.
	 *
	 * @param \WP_Term|mixed $default  Default term.
	 * @param mixed          $terms    Candidate terms.
	 * @param \WP_Post|mixed $post     Post.
	 * @param string         $taxonomy Taxonomy.
	 * @return \WP_Term|mixed
	 */
	private static function pick( $default, $terms, $post, string $taxonomy ) {
		if ( ! $post instanceof \WP_Post || ! is_array( $terms ) ) {
			return $default;
		}
		$chosen = (int) get_post_meta( $post->ID, self::meta_key( $taxonomy ), true );
		if ( ! $chosen ) {
			return $default;
		}
		foreach ( $terms as $term ) {
			if ( $term instanceof \WP_Term && (int) $term->term_id === $chosen ) {
				return $term;
			}
		}
		return $default;
	}
}
