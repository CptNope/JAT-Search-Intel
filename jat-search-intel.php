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
define( 'JAT_SEARCH_INTEL_VERSION', '3.5.0' );
define( 'JAT_SEARCH_INTEL_FILE', __FILE__ );
define( 'JAT_SEARCH_INTEL_DIR', plugin_dir_path( __FILE__ ) );
define( 'JAT_SEARCH_INTEL_URL', plugin_dir_url( __FILE__ ) );
define( 'JAT_SEARCH_INTEL_BASENAME', plugin_basename( __FILE__ ) );

// Autoload classes
spl_autoload_register( function ( $class ) {
    $prefix    = 'JAT_Search_Intel\\';
    $base_dir  = JAT_SEARCH_INTEL_DIR . 'includes/';

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
 * Cron hooks the plugin may schedule; cleared on deactivation.
 *
 * @return string[]
 */
function jat_search_intel_cron_hooks() {
    return [
        'jat_search_intel_broken_link_check',
        'jat_search_intel_check_links',
        'jat_search_intel_cwv_scan',
        'jat_search_intel_daily_aggregate',
        'jat_search_intel_decay_check',
        'jat_search_intel_ga4_fetch',
        'jat_search_intel_process_queue',
        'jat_search_intel_score_drop_check',
        'jat_search_intel_send_scheduled_report',
        'jat_search_intel_visibility_sync',
        'jat_search_intel_weekly_digest',
    ];
}

/**
 * Initialize the plugin.
 */
function jat_search_intel_init() {
    // Legacy-data migration, table creation and rewrite flush on version change.
    JAT_Search_Intel\Upgrade::run();
    return JAT_Search_Intel\Core::get_instance();
}

// Flush rewrite rules once, after every module has registered its rules.
add_action( 'init', [ 'JAT_Search_Intel\\Upgrade', 'maybe_flush_rewrites' ], 9999 );

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

// Activation hook — defaults, tables, and a rewrite flush once our rules exist.
register_activation_hook( __FILE__, function () {
    JAT_Search_Intel\Upgrade::run();
    $options = get_option( 'jat_search_intel_settings', [] );
    if ( empty( $options ) ) {
        update_option( 'jat_search_intel_settings', JAT_Search_Intel\Core::get_default_settings() );
    }
    JAT_Search_Intel\Redirect_Manager::create_tables();
    JAT_Search_Intel\Upgrade::request_rewrite_flush();
});

// Deactivation hook — stop background jobs and drop our rewrite rules.
register_deactivation_hook( __FILE__, function () {
    foreach ( jat_search_intel_cron_hooks() as $hook ) {
        wp_clear_scheduled_hook( $hook );
    }
    flush_rewrite_rules();
});

// Boot
add_action( 'plugins_loaded', 'jat_search_intel_init' );

// WP-CLI commands
if ( defined( 'WP_CLI' ) && WP_CLI ) {
    \WP_CLI::add_command( 'jat-search-intel', 'JAT_Search_Intel\CLI' );
}
