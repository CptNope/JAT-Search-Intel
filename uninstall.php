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
$clean_on_uninstall = get_option( 'search_intel_clean_uninstall', false );

if ( ! $clean_on_uninstall ) {
    return;
}

// Delete plugin options
delete_option( 'search_intel_settings' );
delete_option( 'search_intel_social_profiles' );
delete_option( 'search_intel_ai_permissions' );
delete_option( 'search_intel_clean_uninstall' );
delete_option( 'search_intel_github_token' );

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
    '_search_intel_social_title',
    '_search_intel_social_description',
    '_search_intel_social_image',
    '_search_intel_secondary_keywords',
    '_search_intel_direct_answer',
    '_search_intel_citations',
    '_search_intel_author_expertise_notes',
    '_search_intel_content_intent',
    '_search_intel_audience',
    '_search_intel_local_relevance',
    '_search_intel_local_location',
    '_search_intel_seo_score',
    '_search_intel_aeo_score',
    '_search_intel_geo_score',
    '_search_intel_readability_score',
    '_search_intel_ai_suggestions',
    '_search_intel_failing_checks',
    '_search_intel_ai_snapshot',
    '_search_intel_score_history',
    '_search_intel_score_change_log',
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
