<?php
namespace JAT_Search_Intel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use JAT_Search_Intel\Redirect_Manager;

/**
 * Tests for the compiled redirect lookup index.
 */
class RedirectIndexTest extends TestCase {

	/** @var object Original $wpdb stub. */
	private $original_wpdb;

	/** @var object Fake $wpdb that serves fixture rows and counts queries. */
	private $db;

	private Redirect_Manager $manager;

	protected function setUp(): void {
		global $si_test_state, $wpdb;
		$si_test_state = [
			'actions'    => [],
			'filters'    => [],
			'options'    => [],
			'transients' => [],
		];

		$this->original_wpdb = $wpdb;

		$this->db = new class {
			public $prefix  = 'wp_';
			public $options = 'wp_options';
			public $queries = 0;
			/** @var array<int,object> */
			public $rows = [];

			public function prepare( string $query, ...$args ): string {
				$i = 0;
				return preg_replace_callback( '/%[sdi]/', static function ( $m ) use ( $args, &$i ) {
					$value = $args[ $i++ ] ?? '';
					return '%s' === $m[0] ? "'" . $value . "'" : (string) $value;
				}, $query );
			}
			public function get_var( string $query ) {
				$this->queries++;
				if ( false !== strpos( $query, 'COUNT(*)' ) ) {
					return count( array_filter( $this->rows, static function ( $r ) { return 'exact' === $r->match_type && $r->enabled; } ) );
				}
				return null;
			}
			public function get_results( string $query ) {
				$this->queries++;
				return array_values( array_filter( $this->rows, static function ( $r ) { return (bool) $r->enabled; } ) );
			}
			public function get_row( string $query ) {
				$this->queries++;
				if ( preg_match( '/id = (\d+)/', $query, $m ) ) {
					return $this->rows[ (int) $m[1] ] ?? null;
				}
				return null;
			}
		};

		$this->db->rows = [
			1 => (object) [ 'id' => 1, 'source_url' => '/old-page', 'target_url' => '/new-page', 'match_type' => 'exact', 'enabled' => 1, 'status_code' => 301 ],
			2 => (object) [ 'id' => 2, 'source_url' => '/blog/', 'target_url' => '/news/', 'match_type' => 'prefix', 'enabled' => 1, 'status_code' => 301 ],
			3 => (object) [ 'id' => 3, 'source_url' => '/blog/2019/', 'target_url' => '/archive/', 'match_type' => 'prefix', 'enabled' => 1, 'status_code' => 301 ],
			4 => (object) [ 'id' => 4, 'source_url' => '^/p/(\d+)$', 'target_url' => '/?p=$1', 'match_type' => 'regex', 'enabled' => 1, 'status_code' => 302 ],
			5 => (object) [ 'id' => 5, 'source_url' => '/disabled', 'target_url' => '/x', 'match_type' => 'exact', 'enabled' => 0, 'status_code' => 301 ],
		];

		$wpdb = $this->db;

		$this->manager = new Redirect_Manager( [ 'enable_redirects' => true ] );
	}

	protected function tearDown(): void {
		global $wpdb;
		$wpdb = $this->original_wpdb;
	}

	public function test_exact_match_is_case_insensitive(): void {
		$this->assertSame( 1, (int) $this->manager->find_matching_redirect( '/Old-Page' )->id );
	}

	public function test_longest_prefix_wins(): void {
		$this->assertSame( 3, (int) $this->manager->find_matching_redirect( '/blog/2019/hello' )->id );
		$this->assertSame( 2, (int) $this->manager->find_matching_redirect( '/blog/2024/hello' )->id );
	}

	public function test_regex_match(): void {
		$this->assertSame( 4, (int) $this->manager->find_matching_redirect( '/p/42' )->id );
	}

	public function test_disabled_rules_are_ignored(): void {
		$this->assertNull( $this->manager->find_matching_redirect( '/disabled' ) );
	}

	public function test_non_matching_request_costs_no_queries_once_indexed(): void {
		$this->manager->find_matching_redirect( '/warm-up' ); // Builds the index.
		$this->db->queries = 0;

		$this->assertNull( $this->manager->find_matching_redirect( '/about' ) );
		$this->assertSame( 0, $this->db->queries );
	}

	public function test_small_index_is_autoloaded(): void {
		global $si_test_state;
		$this->manager->rebuild_index();
		$this->assertTrue( $si_test_state['autoload']['jat_search_intel_redirect_index'] );
	}

	public function test_stale_index_entry_is_dropped(): void {
		global $si_test_state;
		$this->manager->rebuild_index();
		unset( $this->db->rows[1] ); // Row deleted behind the plugin's back.

		$this->assertNull( $this->manager->find_matching_redirect( '/old-page' ) );
		$this->assertArrayNotHasKey( 'jat_search_intel_redirect_index', $si_test_state['options'] );
	}

	public function test_probe_paths_are_not_logged_as_404s(): void {
		foreach ( [ '/wp-login.php', '/.env', '/backup.sql', '/.git/config', '/wp-admin/setup-config.php', '/vendor/phpunit/x' ] as $probe ) {
			$this->assertTrue( Redirect_Manager::is_probe_path( $probe ), $probe );
		}
		foreach ( [ '/old-blog-post/', '/products/blue-shoes', '/2019/05/hello/' ] as $real ) {
			$this->assertFalse( Redirect_Manager::is_probe_path( $real ), $real );
		}
	}
}
