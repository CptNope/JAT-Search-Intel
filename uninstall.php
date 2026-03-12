<?php
/**
 * Search Intel Uninstall
 *
 * Fired when the plugin is uninstalled. Cleans up all plugin data from the database.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Only clean up if the user opted in (or always clean for a full uninstall)
$clean_on_uninstall = get_option( 'search_intel_clean_uninstall', true );

if ( ! $clean_on_uninstall ) {
    return;
}

// Delete plugin options
delete_option( 'search_intel_settings' );
delete_option( 'search_intel_social_profiles' );
delete_option( 'search_intel_ai_permissions' );
delete_option( 'search_intel_clean_uninstall' );

// Delete all post meta created by the plugin
global $wpdb;

$meta_keys = [
    '_search_intel_title',
    '_search_intel_description',
    '_search_intel_focus_keyword',
    '_search_intel_canonical',
    '_search_intel_noindex',
    '_search_intel_nofollow',
    '_search_intel_og_image',
    '_search_intel_schema_type',
    '_search_intel_faqs',
    '_search_intel_howto',
    '_search_intel_speakable_selectors',
    '_search_intel_ai_summary',
    '_search_intel_ai_noindex',
    '_search_intel_topics',
    '_search_intel_entities',
    '_search_intel_expertise_level',
    '_search_intel_content_type',
    '_search_intel_fact_checked',
];

foreach ( $meta_keys as $key ) {
    $wpdb->delete( $wpdb->postmeta, [ 'meta_key' => $key ] );
}

// Delete user meta
$user_meta_keys = [
    'search_intel_credentials',
    'search_intel_expertise_areas',
    'search_intel_years_experience',
    'search_intel_social_profiles',
];

foreach ( $user_meta_keys as $key ) {
    $wpdb->delete( $wpdb->usermeta, [ 'meta_key' => $key ] );
}

// Flush rewrite rules
flush_rewrite_rules();
