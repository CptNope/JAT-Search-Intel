<?php
namespace JAT_Search_Intel\Tests\Unit;

use JAT_Search_Intel\Crawl_Cleanup;

class CrawlCleanupTest extends ParityTestCase {

	public function test_any_enabled(): void {
		$this->assertFalse( Crawl_Cleanup::any_enabled( [] ) );
		$this->assertFalse( Crawl_Cleanup::any_enabled( [ 'cleanup_rsd' => false ] ) );
		$this->assertTrue( Crawl_Cleanup::any_enabled( [ 'cleanup_rsd' => true ] ) );
	}

	/**
	 * @dataProvider spam_queries
	 */
	public function test_is_spam_search( string $query, bool $spam ): void {
		$this->assertSame( $spam, Crawl_Cleanup::is_spam_search( $query ) );
	}

	public static function spam_queries(): array {
		return [
			'empty'           => [ '', false ],
			'normal'          => [ 'best hiking boots', false ],
			'with dot'        => [ 'node.js tutorial', false ],
			'url'             => [ 'cheap pills https://spam.example', true ],
			'www'             => [ 'visit www.spam', true ],
			'bare domain'     => [ 'casino-bonus.xyz', true ],
			'html'            => [ '<a href=x>', true ],
			'too long'        => [ str_repeat( 'a', 101 ), true ],
			'long but ok'     => [ str_repeat( 'a', 100 ), false ],
		];
	}

	public function test_apply_registers_only_enabled_cleanups(): void {
		$cleanup = new Crawl_Cleanup( [ 'cleanup_generator' => true, 'cleanup_pingback_header' => true, 'cleanup_search_spam' => true ] );
		$cleanup->apply();
		$filters = $GLOBALS['si_test_state']['filters'];
		$actions = $GLOBALS['si_test_state']['actions'];
		$this->assertArrayHasKey( 'the_generator', $filters );
		$this->assertArrayHasKey( 'wp_headers', $filters );
		$this->assertArrayHasKey( 'wp', $actions );
		$this->assertArrayNotHasKey( 'feed_links_show_comments_feed', $filters );
		$this->assertArrayNotHasKey( 'emoji_svg_url', $filters );
	}

	public function test_feed_cleanup_replaces_feed_links_extra(): void {
		( new Crawl_Cleanup( [ 'cleanup_feed_extra' => true ] ) )->apply();
		$hooked = array_column( $GLOBALS['si_test_state']['actions']['wp_head'] ?? [], 'priority' );
		$this->assertContains( 3, $hooked );
	}

	public function test_remove_pingback_header(): void {
		$headers = ( new Crawl_Cleanup( [] ) )->remove_pingback_header( [ 'X-Pingback' => 'x', 'Content-Type' => 'text/html' ] );
		$this->assertSame( [ 'Content-Type' => 'text/html' ], $headers );
	}
}
