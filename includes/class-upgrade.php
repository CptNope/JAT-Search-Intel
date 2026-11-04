<?php
/**
 * Version upgrades and the one-time move from the legacy "search_intel"
 * identifiers (GitHub edition) to the "jat_search_intel" identifiers used
 * by the WordPress.org release.
 *
 * @package JAT_Search_Intel
 */

namespace JAT_Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs schema, rewrite and legacy-data upgrades.
 */
class Upgrade {

	/**
	 * Option recording that legacy data has been migrated (or that there was none).
	 */
	public const LEGACY_FLAG = 'jat_search_intel_legacy_migrated';

	/**
	 * Option recording the last plugin version that ran upgrades.
	 */
	public const VERSION_OPTION = 'jat_search_intel_version';

	/**
	 * Option asking for rewrite rules to be flushed once our rules are registered.
	 */
	public const FLUSH_FLAG = 'jat_search_intel_flush_rewrites';

	/**
	 * Legacy identifiers.
	 */
	private const OLD_PREFIX      = 'search_intel_';
	private const OLD_META_PREFIX = '_search_intel_';
	private const OLD_TABLES      = [ 'search_intel_redirects', 'search_intel_404_log' ];
	private const OLD_ROLE        = 'search_intel_seo_editor';
	private const OLD_CAPS        = [ 'search_intel_manage_settings', 'search_intel_view_scores' ];
	private const OLD_CRON_HOOKS  = [
		'search_intel_broken_link_check',
		'search_intel_check_links',
		'search_intel_cwv_scan',
		'search_intel_daily_aggregate',
		'search_intel_decay_check',
		'search_intel_ga4_fetch',
		'search_intel_process_queue',
		'search_intel_score_drop_check',
		'search_intel_send_scheduled_report',
		'search_intel_visibility_sync',
		'search_intel_weekly_digest',
	];

	/**
	 * Run everything that must happen before modules boot.
	 *
	 * Cheap on normal requests: two autoloaded option reads.
	 */
	public static function run(): void {
		if ( ! get_option( self::LEGACY_FLAG ) ) {
			self::migrate_legacy_data();
		}

		if ( get_option( self::VERSION_OPTION ) !== JAT_SEARCH_INTEL_VERSION ) {
			Redirect_Manager::create_tables();
			Rank_Tracker::create_table();
			update_option( self::VERSION_OPTION, JAT_SEARCH_INTEL_VERSION );
			self::request_rewrite_flush();
		}
	}

	/**
	 * Ask for rewrite rules to be flushed after they are registered on init.
	 */
	public static function request_rewrite_flush(): void {
		update_option( self::FLUSH_FLAG, 1 );
	}

	/**
	 * Flush rewrite rules if requested (hooked late on init).
	 */
	public static function maybe_flush_rewrites(): void {
		if ( get_option( self::FLUSH_FLAG ) ) {
			delete_option( self::FLUSH_FLAG );
			flush_rewrite_rules( false );
		}
	}

	/**
	 * Whether this site holds data from the legacy GitHub edition.
	 *
	 * @return bool
	 */
	public static function has_legacy_data(): bool {
		global $wpdb;

		if ( false !== get_option( self::OLD_PREFIX . 'settings', false ) ) {
			return true;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time upgrade check.
		$table = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->prefix . self::OLD_TABLES[0] ) ) );
		return ! empty( $table );
	}

	/**
	 * Move legacy options, meta, tables, roles, block markup and cron events
	 * to the new identifiers. Idempotent: anything already present under the
	 * new name is left untouched.
	 */
	public static function migrate_legacy_data(): void {
		if ( ! self::has_legacy_data() ) {
			update_option( self::LEGACY_FLAG, 'none' );
			return;
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- One-time data migration on custom and core tables.
		global $wpdb;

		// 1. Options (renamed one by one so existing new options win).
		$old_options = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT option_name FROM %i WHERE option_name LIKE %s',
				$wpdb->options,
				$wpdb->esc_like( self::OLD_PREFIX ) . '%'
			)
		);
		foreach ( (array) $old_options as $old_name ) {
			$new_name = 'jat_' . $old_name;
			$exists   = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE option_name = %s', $wpdb->options, $new_name ) );
			if ( $exists ) {
				$wpdb->delete( $wpdb->options, [ 'option_name' => $old_name ] );
			} else {
				$wpdb->update( $wpdb->options, [ 'option_name' => $new_name ], [ 'option_name' => $old_name ] );
			}
		}

		// Old transients are disposable caches.
		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i WHERE option_name LIKE %s OR option_name LIKE %s',
				$wpdb->options,
				$wpdb->esc_like( '_transient_' . self::OLD_PREFIX ) . '%',
				$wpdb->esc_like( '_transient_timeout_' . self::OLD_PREFIX ) . '%'
			)
		);

		// 2. Post meta and user meta.
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE %i SET meta_key = CONCAT('_jat', meta_key) WHERE meta_key LIKE %s",
				$wpdb->postmeta,
				$wpdb->esc_like( self::OLD_META_PREFIX ) . '%'
			)
		);
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE %i SET meta_key = CONCAT('jat_', meta_key) WHERE meta_key LIKE %s",
				$wpdb->usermeta,
				$wpdb->esc_like( self::OLD_PREFIX ) . '%'
			)
		);

		// 3. Custom tables.
		foreach ( self::OLD_TABLES as $old_table ) {
			self::migrate_table( $wpdb->prefix . $old_table, $wpdb->prefix . 'jat_' . $old_table );
		}

		// 4. Block markup saved in post content (comment delimiters and generated class names).
		$wpdb->query(
			$wpdb->prepare(
				'UPDATE %i SET post_content = REPLACE(REPLACE(REPLACE(post_content, %s, %s), %s, %s), %s, %s) WHERE post_content LIKE %s',
				$wpdb->posts,
				'<!-- wp:search-intel/',
				'<!-- wp:jat-search-intel/',
				'<!-- /wp:search-intel/',
				'<!-- /wp:jat-search-intel/',
				'wp-block-search-intel-',
				'wp-block-jat-search-intel-',
				'%' . $wpdb->esc_like( 'wp:search-intel/' ) . '%'
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery

		// 5. Roles and capabilities.
		self::migrate_roles_and_caps();

		// 6. Scheduled events (new ones are scheduled by their modules).
		foreach ( self::OLD_CRON_HOOKS as $hook ) {
			wp_clear_scheduled_hook( $hook );
		}

		// 7. Network-wide defaults.
		if ( is_multisite() && is_main_site() ) {
			$network = get_site_option( self::OLD_PREFIX . 'network_defaults', null );
			if ( null !== $network && null === get_site_option( 'jat_search_intel_network_defaults', null ) ) {
				update_site_option( 'jat_search_intel_network_defaults', $network );
			}
			delete_site_option( self::OLD_PREFIX . 'network_defaults' );
		}

		wp_cache_delete( 'alloptions', 'options' );
		wp_cache_delete( 'notoptions', 'options' );
		update_option( self::LEGACY_FLAG, JAT_SEARCH_INTEL_VERSION );
		self::request_rewrite_flush();
	}

	/**
	 * Move a legacy table to its new name.
	 *
	 * Uses ALTER TABLE … RENAME TO (supported by MySQL, MariaDB and SQLite-based
	 * hosts such as WordPress Playground). If that is not possible, the new
	 * table is created and the rows are copied across.
	 *
	 * @param string $old Old table name.
	 * @param string $new New table name.
	 */
	private static function migrate_table( string $old, string $new ): void {
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- One-time schema migration.

		$table_exists = static function ( string $name ) use ( $wpdb ): bool {
			return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $name ) ) );
		};

		if ( ! $table_exists( $old ) ) {
			return;
		}

		if ( ! $table_exists( $new ) ) {
			$suppress = $wpdb->suppress_errors( true );
			$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i RENAME TO %i', $old, $new ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->suppress_errors( $suppress );
			if ( $table_exists( $new ) && ! $table_exists( $old ) ) {
				return;
			}
		}

		// Fallback: make sure the new table exists, copy shared columns, drop the old one.
		Redirect_Manager::create_tables();
		$old_cols = (array) $wpdb->get_col( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $old ) );
		$new_cols = (array) $wpdb->get_col( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $new ) );
		$cols     = array_values( array_intersect( $old_cols, $new_cols ) );

		if ( $cols ) {
			$list = implode( ', ', array_map( static function ( $c ) {
				return '`' . str_replace( '`', '', $c ) . '`';
			}, $cols ) );
			$copied = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $new ) );
			if ( 0 === $copied ) {
				$wpdb->query( $wpdb->prepare( "INSERT INTO %i ({$list}) SELECT {$list} FROM %i", $new, $old ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Column names read from the schema and backtick-quoted.
			}
		}
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $old ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
		// phpcs:enable WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Rename the SEO Editor role and the plugin capabilities on roles and users.
	 */
	private static function migrate_roles_and_caps(): void {
		$map = [];
		foreach ( self::OLD_CAPS as $cap ) {
			$map[ $cap ] = 'jat_' . $cap;
		}

		$old_role = get_role( self::OLD_ROLE );
		if ( $old_role ) {
			$caps = [];
			foreach ( $old_role->capabilities as $cap => $grant ) {
				$caps[ $map[ $cap ] ?? $cap ] = $grant;
			}
			if ( ! get_role( 'jat_' . self::OLD_ROLE ) ) {
				add_role( 'jat_' . self::OLD_ROLE, 'SEO Editor', $caps );
			}
		}

		foreach ( wp_roles()->role_objects as $role ) {
			foreach ( $map as $old => $new ) {
				if ( $role->has_cap( $old ) ) {
					$role->add_cap( $new );
					$role->remove_cap( $old );
				}
			}
		}

		// Users holding the old role or caps directly.
		$users = get_users(
			[
				'fields'   => 'ID',
				'role__in' => $old_role ? [ self::OLD_ROLE ] : [],
				'number'   => -1,
			]
		);
		$users = array_merge( $users, self::user_ids_with_legacy_caps() );

		foreach ( array_unique( array_map( 'intval', $users ) ) as $user_id ) {
			$user = get_userdata( $user_id );
			if ( ! $user ) {
				continue;
			}
			if ( in_array( self::OLD_ROLE, (array) $user->roles, true ) ) {
				$user->add_role( 'jat_' . self::OLD_ROLE );
				$user->remove_role( self::OLD_ROLE );
			}
			foreach ( $map as $old => $new ) {
				if ( ! empty( $user->caps[ $old ] ) ) {
					$user->add_cap( $new );
					$user->remove_cap( $old );
				}
			}
		}

		if ( $old_role ) {
			remove_role( self::OLD_ROLE );
		}
	}

	/**
	 * IDs of users whose own capability list mentions a legacy cap.
	 *
	 * @return int[]
	 */
	private static function user_ids_with_legacy_caps(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time migration.
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT user_id FROM %i WHERE meta_key = %s AND meta_value LIKE %s',
				$wpdb->usermeta,
				$wpdb->get_blog_prefix() . 'capabilities',
				'%' . $wpdb->esc_like( '"' . self::OLD_PREFIX ) . '%'
			)
		);

		return array_map( 'intval', (array) $ids );
	}
}
