<?php
namespace Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce Integration — product schema, breadcrumbs, sitemap, and metabox.
 *
 * @since 2.0.0
 */
class WooCommerce {

	/** @var array */
	private $settings;

	/**
	 * Constructor — hooks only fire when WooCommerce is active.
	 *
	 * @param array $settings Plugin settings.
	 */
	public function __construct( array $settings ) {
		$this->settings = $settings;

		if ( ! $this->is_woocommerce_active() ) {
			return;
		}

		add_filter( 'search_intel_singular_schema', [ $this, 'inject_product_schema' ], 20, 2 );
		add_filter( 'search_intel_breadcrumbs', [ $this, 'product_breadcrumbs' ], 10, 2 );
		add_filter( 'search_intel_sitemap_post_types', [ $this, 'add_product_sitemap' ] );
		add_action( 'add_meta_boxes', [ $this, 'add_product_metabox' ] );
		add_action( 'save_post_product', [ $this, 'save_product_metabox' ] );
	}

	/**
	 * Check if WooCommerce is active.
	 *
	 * @return bool
	 */
	public function is_woocommerce_active(): bool {
		return class_exists( 'WooCommerce' ) || in_array( 'woocommerce/woocommerce.php', (array) get_option( 'active_plugins', [] ), true );
	}

	/**
	 * Inject Product schema into the JSON-LD graph for WooCommerce products.
	 *
	 * @param array $schema Existing schema.
	 * @param int   $post_id Post ID.
	 * @return array
	 */
	public function inject_product_schema( array $schema, int $post_id ): array {
		if ( 'product' !== get_post_type( $post_id ) ) {
			return $schema;
		}

		$product = $this->get_product( $post_id );
		if ( ! $product ) {
			return $schema;
		}

		$product_schema = [
			'@type'       => 'Product',
			'@id'         => get_permalink( $post_id ) . '#product',
			'name'        => $product->get_name(),
			'description' => wp_strip_all_tags( $product->get_short_description() ?: $product->get_description() ),
			'url'         => get_permalink( $post_id ),
		];

		// Image
		$image_id = $product->get_image_id();
		if ( $image_id ) {
			$image_url = wp_get_attachment_url( $image_id );
			if ( $image_url ) {
				$product_schema['image'] = $image_url;
			}
		}

		// SKU
		$sku = $product->get_sku();
		if ( $sku ) {
			$product_schema['sku'] = $sku;
		}

		// Brand (custom meta or taxonomy)
		$brand = get_post_meta( $post_id, '_search_intel_product_brand', true );
		if ( $brand ) {
			$product_schema['brand'] = [
				'@type' => 'Brand',
				'name'  => $brand,
			];
		}

		// GTIN
		$gtin = get_post_meta( $post_id, '_search_intel_product_gtin', true );
		if ( $gtin ) {
			$product_schema['gtin'] = $gtin;
		}

		// MPN
		$mpn = get_post_meta( $post_id, '_search_intel_product_mpn', true );
		if ( $mpn ) {
			$product_schema['mpn'] = $mpn;
		}

		// Offer schema
		$offer = $this->get_offer_schema( $product );
		if ( $offer ) {
			$product_schema['offers'] = $offer;
		}

		// AggregateRating from WooCommerce reviews
		$rating = $this->get_aggregate_rating( $product );
		if ( $rating ) {
			$product_schema['aggregateRating'] = $rating;
		}

		$schema['@graph'][] = $product_schema;

		return $schema;
	}

	/**
	 * Build Offer schema from a WooCommerce product.
	 *
	 * @param object $product WC_Product instance.
	 * @return array|null
	 */
	public function get_offer_schema( $product ): ?array {
		$price = $product->get_price();
		if ( '' === $price ) {
			return null;
		}

		$offer = [
			'@type'         => 'Offer',
			'price'         => $price,
			'priceCurrency' => get_woocommerce_currency(),
			'url'           => get_permalink( $product->get_id() ),
		];

		// Availability
		$stock_status = $product->get_stock_status();
		$availability_map = [
			'instock'     => 'https://schema.org/InStock',
			'outofstock'  => 'https://schema.org/OutOfStock',
			'onbackorder' => 'https://schema.org/BackOrder',
		];
		$offer['availability'] = $availability_map[ $stock_status ] ?? 'https://schema.org/InStock';

		// Condition (custom meta, default to new)
		$condition = get_post_meta( $product->get_id(), '_search_intel_product_condition', true );
		$condition_map = [
			'new'         => 'https://schema.org/NewCondition',
			'used'        => 'https://schema.org/UsedCondition',
			'refurbished' => 'https://schema.org/RefurbishedCondition',
		];
		$offer['itemCondition'] = $condition_map[ $condition ] ?? 'https://schema.org/NewCondition';

		// Seller
		$offer['seller'] = [
			'@type' => 'Organization',
			'name'  => get_bloginfo( 'name' ),
		];

		// Sale price dates
		$sale_start = $product->get_date_on_sale_from();
		$sale_end   = $product->get_date_on_sale_to();
		if ( $sale_start ) {
			$offer['validFrom'] = $sale_start->date( 'c' );
		}
		if ( $sale_end ) {
			$offer['priceValidUntil'] = $sale_end->date( 'c' );
		}

		return $offer;
	}

	/**
	 * Build AggregateRating schema from WooCommerce reviews.
	 *
	 * @param object $product WC_Product instance.
	 * @return array|null
	 */
	public function get_aggregate_rating( $product ): ?array {
		$count  = $product->get_review_count();
		$rating = $product->get_average_rating();

		if ( $count < 1 || empty( $rating ) ) {
			return null;
		}

		return [
			'@type'       => 'AggregateRating',
			'ratingValue' => (float) $rating,
			'reviewCount' => (int) $count,
		];
	}

	/**
	 * Generate product breadcrumbs: Home > Shop > Category > Product.
	 *
	 * @param array $breadcrumbs Existing breadcrumbs.
	 * @param int   $post_id     Post ID.
	 * @return array
	 */
	public function product_breadcrumbs( array $breadcrumbs, int $post_id ): array {
		if ( 'product' !== get_post_type( $post_id ) ) {
			return $breadcrumbs;
		}

		$crumbs = [];

		// Home
		$crumbs[] = [
			'name' => __( 'Home', 'jat-search-intel' ),
			'url'  => home_url( '/' ),
		];

		// Shop page
		$shop_id = function_exists( 'wc_get_page_id' ) ? wc_get_page_id( 'shop' ) : 0;
		if ( $shop_id > 0 ) {
			$crumbs[] = [
				'name' => get_the_title( $shop_id ),
				'url'  => get_permalink( $shop_id ),
			];
		}

		// Product category (primary)
		$terms = get_the_terms( $post_id, 'product_cat' );
		if ( $terms && ! is_wp_error( $terms ) ) {
			$primary = $terms[0];
			// Include ancestors
			$ancestors = get_ancestors( $primary->term_id, 'product_cat', 'taxonomy' );
			$ancestors = array_reverse( $ancestors );
			foreach ( $ancestors as $ancestor_id ) {
				$ancestor = get_term( $ancestor_id, 'product_cat' );
				if ( $ancestor && ! is_wp_error( $ancestor ) ) {
					$crumbs[] = [
						'name' => $ancestor->name,
						'url'  => get_term_link( $ancestor ),
					];
				}
			}
			$crumbs[] = [
				'name' => $primary->name,
				'url'  => get_term_link( $primary ),
			];
		}

		// Product itself
		$crumbs[] = [
			'name' => get_the_title( $post_id ),
			'url'  => get_permalink( $post_id ),
		];

		return $crumbs;
	}

	/**
	 * Add product post type to sitemap.
	 *
	 * @param array $post_types Post types for sitemap.
	 * @return array
	 */
	public function add_product_sitemap( array $post_types ): array {
		if ( ! in_array( 'product', $post_types, true ) ) {
			$post_types[] = 'product';
		}
		return $post_types;
	}

	/**
	 * Add product-specific metabox fields.
	 */
	public function add_product_metabox(): void {
		add_meta_box(
			'search-intel-product',
			__( 'Search Intel — Product Data', 'jat-search-intel' ),
			[ $this, 'render_product_metabox' ],
			'product',
			'side',
			'default'
		);
	}

	/**
	 * Render product metabox.
	 *
	 * @param \WP_Post $post Post object.
	 */
	public function render_product_metabox( $post ): void {
		wp_nonce_field( 'si_product_meta', 'si_product_meta_nonce' );

		$brand     = get_post_meta( $post->ID, '_search_intel_product_brand', true );
		$gtin      = get_post_meta( $post->ID, '_search_intel_product_gtin', true );
		$mpn       = get_post_meta( $post->ID, '_search_intel_product_mpn', true );
		$condition = get_post_meta( $post->ID, '_search_intel_product_condition', true ) ?: 'new';
		?>
		<p>
			<label for="si-product-brand"><strong><?php esc_html_e( 'Brand', 'jat-search-intel' ); ?></strong></label><br>
			<input type="text" id="si-product-brand" name="si_product_brand" value="<?php echo esc_attr( $brand ); ?>" class="widefat" />
		</p>
		<p>
			<label for="si-product-gtin"><strong><?php esc_html_e( 'GTIN / EAN / UPC', 'jat-search-intel' ); ?></strong></label><br>
			<input type="text" id="si-product-gtin" name="si_product_gtin" value="<?php echo esc_attr( $gtin ); ?>" class="widefat" />
		</p>
		<p>
			<label for="si-product-mpn"><strong><?php esc_html_e( 'MPN', 'jat-search-intel' ); ?></strong></label><br>
			<input type="text" id="si-product-mpn" name="si_product_mpn" value="<?php echo esc_attr( $mpn ); ?>" class="widefat" />
		</p>
		<p>
			<label for="si-product-condition"><strong><?php esc_html_e( 'Condition', 'jat-search-intel' ); ?></strong></label><br>
			<select id="si-product-condition" name="si_product_condition" class="widefat">
				<option value="new" <?php selected( $condition, 'new' ); ?>><?php esc_html_e( 'New', 'jat-search-intel' ); ?></option>
				<option value="used" <?php selected( $condition, 'used' ); ?>><?php esc_html_e( 'Used', 'jat-search-intel' ); ?></option>
				<option value="refurbished" <?php selected( $condition, 'refurbished' ); ?>><?php esc_html_e( 'Refurbished', 'jat-search-intel' ); ?></option>
			</select>
		</p>
		<?php
	}

	/**
	 * Save product metabox data.
	 *
	 * @param int $post_id Post ID.
	 */
	public function save_product_metabox( int $post_id ): void {
		if ( ! isset( $_POST['si_product_meta_nonce'] ) ||
			 ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['si_product_meta_nonce'] ) ), 'si_product_meta' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$fields = [
			'si_product_brand'     => '_search_intel_product_brand',
			'si_product_gtin'      => '_search_intel_product_gtin',
			'si_product_mpn'       => '_search_intel_product_mpn',
			'si_product_condition' => '_search_intel_product_condition',
		];

		foreach ( $fields as $post_key => $meta_key ) {
			if ( isset( $_POST[ $post_key ] ) ) {
				update_post_meta( $post_id, $meta_key, sanitize_text_field( wp_unslash( $_POST[ $post_key ] ) ) );
			}
		}
	}

	/**
	 * Get WC_Product from post ID.
	 *
	 * @param int $post_id Post ID.
	 * @return object|null
	 */
	private function get_product( int $post_id ) {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return null;
		}
		$product = wc_get_product( $post_id );
		return $product ?: null;
	}
}
