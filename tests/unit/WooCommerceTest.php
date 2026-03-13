<?php
namespace Search_Intel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Search_Intel\WooCommerce;

class WooCommerceTest extends TestCase {

	private WooCommerce $woo;

	protected function setUp(): void {
		global $si_test_state;
		$si_test_state = [
			'actions'        => [],
			'filters'        => [],
			'options'        => [ 'active_plugins' => [] ],
			'post_meta'      => [],
			'user_meta'      => [],
			'posts'          => [],
			'transients'     => [],
			'current_user'   => 1,
			'active_plugins' => [],
			'post_terms'     => [],
		];

		$this->woo = new WooCommerce( [] );
	}

	// ── Activation detection ─────────────────────────────────────────────

	public function test_is_woocommerce_active_false_by_default(): void {
		$this->assertFalse( $this->woo->is_woocommerce_active() );
	}

	public function test_is_woocommerce_active_via_active_plugins(): void {
		global $si_test_state;
		$si_test_state['options']['active_plugins'] = [ 'woocommerce/woocommerce.php' ];
		$woo = new WooCommerce( [] );
		$this->assertTrue( $woo->is_woocommerce_active() );
	}

	// ── Product schema injection ─────────────────────────────────────────

	public function test_inject_product_schema_skips_non_product(): void {
		global $si_test_state;
		$si_test_state['posts'][1] = [ 'ID' => 1, 'post_type' => 'post' ];

		$schema = $this->woo->inject_product_schema( [ '@graph' => [] ], 1 );
		$this->assertEmpty( $schema['@graph'] );
	}

	// ── Product breadcrumbs ──────────────────────────────────────────────

	public function test_product_breadcrumbs_skips_non_product(): void {
		global $si_test_state;
		$si_test_state['posts'][1] = [ 'ID' => 1, 'post_type' => 'post' ];

		$crumbs = $this->woo->product_breadcrumbs( [], 1 );
		$this->assertEmpty( $crumbs );
	}

	public function test_product_breadcrumbs_builds_for_product(): void {
		global $si_test_state;
		$si_test_state['posts'][5] = [
			'ID'         => 5,
			'post_type'  => 'product',
			'post_title' => 'Widget',
		];

		$crumbs = $this->woo->product_breadcrumbs( [], 5 );
		$this->assertNotEmpty( $crumbs );
		$this->assertEquals( 'Home', $crumbs[0]['name'] );
		$last = end( $crumbs );
		$this->assertEquals( 'Widget', $last['name'] );
	}

	// ── Sitemap ──────────────────────────────────────────────────────────

	public function test_add_product_sitemap_appends_product(): void {
		$types = $this->woo->add_product_sitemap( [ 'post', 'page' ] );
		$this->assertContains( 'product', $types );
	}

	public function test_add_product_sitemap_does_not_duplicate(): void {
		$types = $this->woo->add_product_sitemap( [ 'post', 'product' ] );
		$count = array_count_values( $types );
		$this->assertEquals( 1, $count['product'] );
	}

	// ── Offer schema ─────────────────────────────────────────────────────

	public function test_get_offer_schema_returns_null_for_empty_price(): void {
		$mock = new class {
			public function get_price() { return ''; }
		};
		$this->assertNull( $this->woo->get_offer_schema( $mock ) );
	}

	public function test_get_offer_schema_builds_correct_structure(): void {
		$mock = new class {
			public function get_id() { return 5; }
			public function get_price() { return '19.99'; }
			public function get_stock_status() { return 'instock'; }
			public function get_date_on_sale_from() { return null; }
			public function get_date_on_sale_to() { return null; }
		};

		$offer = $this->woo->get_offer_schema( $mock );

		$this->assertNotNull( $offer );
		$this->assertEquals( 'Offer', $offer['@type'] );
		$this->assertEquals( '19.99', $offer['price'] );
		$this->assertEquals( 'USD', $offer['priceCurrency'] );
		$this->assertStringContainsString( 'InStock', $offer['availability'] );
	}

	public function test_get_offer_schema_out_of_stock(): void {
		$mock = new class {
			public function get_id() { return 6; }
			public function get_price() { return '29.99'; }
			public function get_stock_status() { return 'outofstock'; }
			public function get_date_on_sale_from() { return null; }
			public function get_date_on_sale_to() { return null; }
		};

		$offer = $this->woo->get_offer_schema( $mock );
		$this->assertStringContainsString( 'OutOfStock', $offer['availability'] );
	}

	// ── Aggregate rating ─────────────────────────────────────────────────

	public function test_get_aggregate_rating_returns_null_no_reviews(): void {
		$mock = new class {
			public function get_review_count() { return 0; }
			public function get_average_rating() { return '0'; }
		};
		$this->assertNull( $this->woo->get_aggregate_rating( $mock ) );
	}

	public function test_get_aggregate_rating_builds_correct_structure(): void {
		$mock = new class {
			public function get_review_count() { return 15; }
			public function get_average_rating() { return '4.5'; }
		};

		$rating = $this->woo->get_aggregate_rating( $mock );
		$this->assertNotNull( $rating );
		$this->assertEquals( 'AggregateRating', $rating['@type'] );
		$this->assertEquals( 4.5, $rating['ratingValue'] );
		$this->assertEquals( 15, $rating['reviewCount'] );
	}
}
