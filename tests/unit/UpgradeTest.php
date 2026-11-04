<?php
namespace JAT_Search_Intel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use JAT_Search_Intel\Upgrade;

/**
 * Tests for the legacy search_intel → jat_search_intel data migration.
 */
class UpgradeTest extends TestCase {

	/** @var object */
	private $original_wpdb;

	/** @var object Fake $wpdb backed by in-memory options and a query log. */
	private $db;

	protected function setUp(): void {
		global $si_test_state, $wpdb;
		$si_test_state = [
			'actions'      => [],
			'filters'      => [],
			'options'      => [],
			'transients'   => [],
			'roles'        => [],
			'site_options' => [],
		];

		$this->original_wpdb = $wpdb;

		$this->db = new class {
			public $prefix   = 'wp_';
			public $options  = 'wp_options';
			public $postmeta = 'wp_postmeta';
			public $usermeta = 'wp_usermeta';
			public $posts    = 'wp_posts';
			/** @var string[] */
			public $queries = [];
			/** @var string[] */
			public $tables = [];

			public function esc_like( string $text ): string {
				return addcslashes( $text, '_%\\' );
			}
			public function suppress_errors( $suppress = true ) {
				return false;
			}
			public function get_blog_prefix(): string {
				return 'wp_';
			}
			public function prepare( string $query, ...$args ): string {
				$i = 0;
				return preg_replace_callback( '/%[sdi]/', static function ( $m ) use ( $args, &$i ) {
					$v = $args[ $i++ ] ?? '';
					return '%i' === $m[0] ? '`' . $v . '`' : "'" . $v . "'";
				}, $query );
			}
			public function query( string $q ) {
				$this->queries[] = $q;
				if ( preg_match( '/(?:RENAME TABLE|ALTER TABLE) `([^`]+)` (?:TO|RENAME TO) `([^`]+)`/', $q, $m ) ) {
					$this->tables = array_values( array_diff( $this->tables, [ $m[1] ] ) );
					$this->tables[] = $m[2];
				}
				return 1;
			}
			public function get_var( string $q ) {
				$this->queries[] = $q;
				if ( preg_match( "/SHOW TABLES LIKE '([^']+)'/", $q, $m ) ) {
					$name = stripslashes( $m[1] );
					return in_array( $name, $this->tables, true ) ? $name : null;
				}
				if ( preg_match( "/option_name = '([^']+)'/", $q, $m ) ) {
					return isset( $GLOBALS['si_test_state']['options'][ $m[1] ] ) ? 1 : 0;
				}
				return null;
			}
			public function get_col( string $q ) {
				$this->queries[] = $q;
				if ( false !== strpos( $q, 'FROM `wp_options`' ) ) {
					return array_values( array_filter(
						array_keys( $GLOBALS['si_test_state']['options'] ),
						static function ( $k ) { return 0 === strpos( $k, 'search_intel_' ); }
					) );
				}
				return [];
			}
			public function update( string $table, array $data, array $where ) {
				if ( 'wp_options' === $table ) {
					$opts =& $GLOBALS['si_test_state']['options'];
					$opts[ $data['option_name'] ] = $opts[ $where['option_name'] ];
					unset( $opts[ $where['option_name'] ] );
				}
				return 1;
			}
			public function delete( string $table, array $where ) {
				if ( 'wp_options' === $table ) {
					unset( $GLOBALS['si_test_state']['options'][ $where['option_name'] ] );
				}
				return 1;
			}
		};

		$wpdb = $this->db;
	}

	protected function tearDown(): void {
		global $wpdb;
		$wpdb = $this->original_wpdb;
	}

	public function test_fresh_site_is_marked_without_touching_data(): void {
		global $si_test_state;

		Upgrade::migrate_legacy_data();

		$this->assertSame( 'none', $si_test_state['options'][ Upgrade::LEGACY_FLAG ] );
		foreach ( $this->db->queries as $q ) {
			$this->assertStringNotContainsString( 'UPDATE', $q );
		}
	}

	public function test_legacy_options_are_renamed(): void {
		global $si_test_state;
		$si_test_state['options']['search_intel_settings']       = [ 'enable_seo' => true ];
		$si_test_state['options']['search_intel_social_profiles'] = [ 'x' => 'https://x.com/a' ];

		Upgrade::migrate_legacy_data();

		$this->assertSame( [ 'enable_seo' => true ], $si_test_state['options']['jat_search_intel_settings'] );
		$this->assertArrayHasKey( 'jat_search_intel_social_profiles', $si_test_state['options'] );
		$this->assertArrayNotHasKey( 'search_intel_settings', $si_test_state['options'] );
	}

	public function test_existing_new_option_wins_over_legacy(): void {
		global $si_test_state;
		$si_test_state['options']['search_intel_settings']     = [ 'old' => 1 ];
		$si_test_state['options']['jat_search_intel_settings'] = [ 'new' => 1 ];

		Upgrade::migrate_legacy_data();

		$this->assertSame( [ 'new' => 1 ], $si_test_state['options']['jat_search_intel_settings'] );
		$this->assertArrayNotHasKey( 'search_intel_settings', $si_test_state['options'] );
	}

	public function test_meta_blocks_and_tables_are_migrated(): void {
		$GLOBALS['si_test_state']['options']['search_intel_settings'] = [];
		$this->db->tables = [ 'wp_search_intel_redirects', 'wp_search_intel_404_log' ];

		Upgrade::migrate_legacy_data();

		$all = implode( "\n", $this->db->queries );
		$this->assertStringContainsString( "UPDATE `wp_postmeta` SET meta_key = CONCAT('_jat', meta_key)", $all );
		$this->assertStringContainsString( "UPDATE `wp_usermeta` SET meta_key = CONCAT('jat_', meta_key)", $all );
		$this->assertStringContainsString( 'wp-block-jat-search-intel-', $all );
		$this->assertStringContainsString( '<!-- wp:jat-search-intel/', $all );
		$this->assertContains( 'wp_jat_search_intel_redirects', $this->db->tables );
		$this->assertContains( 'wp_jat_search_intel_404_log', $this->db->tables );
	}

	public function test_legacy_cron_hooks_are_cleared_and_flush_requested(): void {
		global $si_test_state;
		$si_test_state['options']['search_intel_settings'] = [];

		Upgrade::migrate_legacy_data();

		$this->assertContains( 'search_intel_cwv_scan', $si_test_state['cleared_hooks'] );
		$this->assertSame( 1, $si_test_state['options'][ Upgrade::FLUSH_FLAG ] );
		$this->assertSame( JAT_SEARCH_INTEL_VERSION, $si_test_state['options'][ Upgrade::LEGACY_FLAG ] );
	}

	public function test_legacy_seo_editor_role_is_renamed(): void {
		global $si_test_state;
		$si_test_state['options']['search_intel_settings'] = [];
		add_role( 'search_intel_seo_editor', 'SEO Editor', [ 'read' => true, 'search_intel_view_scores' => true ] );

		Upgrade::migrate_legacy_data();

		$this->assertArrayHasKey( 'jat_search_intel_seo_editor', $si_test_state['roles'] );
		$this->assertArrayNotHasKey( 'search_intel_seo_editor', $si_test_state['roles'] );
		$this->assertArrayHasKey( 'jat_search_intel_view_scores', $si_test_state['roles']['jat_search_intel_seo_editor']->capabilities );
	}

	public function test_run_is_noop_once_migrated_and_current(): void {
		global $si_test_state;
		$si_test_state['options'][ Upgrade::LEGACY_FLAG ]    = 'none';
		$si_test_state['options'][ Upgrade::VERSION_OPTION ] = JAT_SEARCH_INTEL_VERSION;

		Upgrade::run();

		$this->assertSame( [], $this->db->queries );
	}
}
