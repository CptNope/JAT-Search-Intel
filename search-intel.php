<?php
/**
 * Plugin Name: Search Intel
 * Plugin URI:  https://searchintel.io
 * Description: A comprehensive SEO, AEO (Answer Engine Optimization), and GEO (Generative Engine Optimization) plugin for WordPress. Optimize your content for traditional search engines, voice assistants, featured snippets, and AI-powered answer engines.
 * Version:     3.4.0
 * Author:      Search Intel
 * Author URI:  https://searchintel.io
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: search-intel
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin constants
define( 'SEARCH_INTEL_VERSION', '3.4.0' );
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
 * Initialize the plugin.
 */
function search_intel_init() {
    return Search_Intel\Core::get_instance();
}

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

// Deactivation hook
register_deactivation_hook( __FILE__, function () {
    flush_rewrite_rules();
});

// Boot
add_action( 'plugins_loaded', 'search_intel_init' );

// WP-CLI commands
if ( defined( 'WP_CLI' ) && WP_CLI ) {
    \WP_CLI::add_command( 'search-intel', 'Search_Intel\CLI' );
}
