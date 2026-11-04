<?php
/**
 * JAT Search Intel uninstall routine.
 *
 * Runs when the plugin is deleted from the Plugins screen. Data is removed
 * only when the site owner opted in via Settings > General > Data Management
 * ("Delete all plugin data …"), so reinstalling never loses SEO work by
 * accident.
 *
 * @package JAT_Search_Intel
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Whether the current site opted in to data removal.
 *
 * @return bool
 */
function search_intel_uninstall_opted_in() {
	$settings = get_option( 'search_intel_settings', array() );

	return ( is_array( $settings ) && ! empty( $settings['delete_data_on_uninstall'] ) )
		|| get_option( 'search_intel_clean_uninstall', false );
}

/**
 * Remove every option, transient, meta row, table, cron event and role the
 * plugin created on the current site.
 */
function search_intel_uninstall_site() {
	global $wpdb;

	// Scheduled events.
	$hooks = array(
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
	);
	foreach ( $hooks as $hook ) {
		wp_clear_scheduled_hook( $hook );
	}

	// Options and transients (all share the search_intel_ prefix).
	// phpcs:disable WordPress.DB.DirectDatabaseQuery
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( 'search_intel_' ) . '%',
			$wpdb->esc_like( '_transient_search_intel_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_search_intel_' ) . '%'
		)
	);

	// Post meta and user meta.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
			$wpdb->esc_like( '_search_intel_' ) . '%'
		)
	);
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
			$wpdb->esc_like( 'search_intel_' ) . '%'
		)
	);

	// Custom tables.
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}search_intel_redirects" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}search_intel_404_log" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
	// phpcs:enable WordPress.DB.DirectDatabaseQuery

	// Role and capabilities.
	remove_role( 'search_intel_seo_editor' );
	foreach ( wp_roles()->role_objects as $role ) {
		$role->remove_cap( 'search_intel_manage_settings' );
		$role->remove_cap( 'search_intel_view_scores' );
	}
}

if ( is_multisite() ) {
	if ( search_intel_uninstall_opted_in() ) {
		delete_site_option( 'search_intel_network_defaults' );
	}
	$search_intel_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);
	foreach ( $search_intel_site_ids as $search_intel_site_id ) {
		switch_to_blog( $search_intel_site_id );
		if ( search_intel_uninstall_opted_in() ) {
			search_intel_uninstall_site();
		}
		restore_current_blog();
	}
} elseif ( search_intel_uninstall_opted_in() ) {
	search_intel_uninstall_site();
}
