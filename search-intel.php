<?php
/**
 * Plugin Name:       JAT Search Intel
 * Plugin URI:        https://github.com/CptNope/JAT-Search-Intel
 * Description:       SEO, AEO (Answer Engine Optimization) and GEO (Generative Engine Optimization) for WordPress: meta tags, schema, sitemaps, llms.txt, redirects and real-time content scoring.
 * Version:           3.5.0
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Author:            Jeremy Anderson Tech
 * Author URI:        https://jeremyanderson.tech
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       jat-search-intel
 *
 * @package JAT_Search_Intel
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin constants
define( 'SEARCH_INTEL_VERSION', '3.5.0' );
define( 'SEARCH_INTEL_FILE', __FILE__ );
define( 'SEARCH_INTEL_DIR', plugin_dir_path( __FILE__ ) );
define( 'SEARCH_INTEL_URL', plugin_dir_url( __FILE__ ) );
define( 'SEARCH_INTEL_BASENAME', plugin_basename( __FILE__ ) );

// Autoload classes
spl_autoload_register( function ( $class ) {
    $prefix    = 'Search_Intel\\';
    $base_dir  = SEARCH_INTEL_DIR . 'includes/';

    $len = strlen( $prefix );
    if ( strncmp( $prefix, $class, $len ) !== 0 ) {
        return;
    }

    $relative_class = substr( $class, $len );
    $file = $base_dir . 'class-' . strtolower( str_replace( [ '\\', '_' ], [ '/', '-' ], $relative_class ) ) . '.php';

    if ( file_exists( $file ) ) {
        require $file;
    }
});

/**
 * Database schema version. Bump when a custom table definition changes.
 */
define( 'SEARCH_INTEL_DB_VERSION', '2' );

/**
 * Cron hooks the plugin may schedule; cleared on deactivation.
 *
 * @return string[]
 */
function search_intel_cron_hooks() {
    return [
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
}

/**
 * Create or upgrade custom tables when the schema version changes.
 *
 * Activation hooks do not run on in-place updates, so this also runs on load.
 */
function search_intel_maybe_upgrade() {
    if ( get_option( 'search_intel_db_version' ) === SEARCH_INTEL_DB_VERSION ) {
        return;
    }
    Search_Intel\Redirect_Manager::create_tables();
    update_option( 'search_intel_db_version', SEARCH_INTEL_DB_VERSION );
    update_option( 'search_intel_db_version', SEARCH_INTEL_DB_VERSION );
}

/**
 * Initialize the plugin.
 */
function search_intel_init() {
    search_intel_maybe_upgrade();
    return Search_Intel\Core::get_instance();
}

// Custom cron interval used by the background queue (Scale module).
add_filter( 'cron_schedules', function ( $schedules ) {
    if ( ! isset( $schedules['every_five_minutes'] ) ) {
        $schedules['every_five_minutes'] = [
            'interval' => 5 * MINUTE_IN_SECONDS,
            'display'  => 'Every five minutes', // Not translated: cron schedules can be read before init.
        ];
    }
    return $schedules;
} );

// Activation hook
register_activation_hook( __FILE__, function () {
    $options = get_option( 'search_intel_settings', [] );
    if ( empty( $options ) ) {
        update_option( 'search_intel_settings', Search_Intel\Core::get_default_settings() );
    }
    Search_Intel\Redirect_Manager::create_tables();

    // Register rewrite rules before flushing so WP picks them up.
    add_rewrite_rule( '^sitemap\.xml$', 'index.php?search_intel_sitemap=index', 'top' );
    add_rewrite_rule( '^sitemap-([a-z]+)-?(\d*)\.xml$', 'index.php?search_intel_sitemap=$matches[1]&search_intel_sitemap_page=$matches[2]', 'top' );
    add_rewrite_rule( '^llms\.txt$', 'index.php?search_intel_llms_txt=1', 'top' );
    add_rewrite_rule( '^llms-full\.txt$', 'index.php?search_intel_llms_full_txt=1', 'top' );
    flush_rewrite_rules();
});

// Deactivation hook — stop background jobs and drop our rewrite rules.
register_deactivation_hook( __FILE__, function () {
    foreach ( search_intel_cron_hooks() as $hook ) {
        wp_clear_scheduled_hook( $hook );
    }
    flush_rewrite_rules();
});

// Boot
add_action( 'plugins_loaded', 'search_intel_init' );

// WP-CLI commands
if ( defined( 'WP_CLI' ) && WP_CLI ) {
    \WP_CLI::add_command( 'search-intel', 'Search_Intel\CLI' );
}
