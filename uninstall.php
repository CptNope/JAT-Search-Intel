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
function jat_search_intel_uninstall_opted_in() {
	$settings = get_option( 'jat_search_intel_settings', array() );

	return ( is_array( $settings ) && ! empty( $settings['delete_data_on_uninstall'] ) )
		|| get_option( 'jat_search_intel_clean_uninstall', false );
}

/**
 * Remove every option, transient, meta row, table, cron event and role the
 * plugin created on the current site.
 */
function jat_search_intel_uninstall_site() {
	global $wpdb;

	// Scheduled events.
	$hooks = array(
		'jat_search_intel_broken_link_check',
		'jat_search_intel_check_links',
		'jat_search_intel_citation_batch',
		'jat_search_intel_citation_check',
		'jat_search_intel_cwv_scan',
		'jat_search_intel_daily_aggregate',
		'jat_search_intel_decay_check',
		'jat_search_intel_ga4_fetch',
		'jat_search_intel_index_status',
		'jat_search_intel_meta_backfill',
		'jat_search_intel_process_queue',
		'jat_search_intel_rank_tracking',
		'jat_search_intel_score_drop_check',
		'jat_search_intel_send_scheduled_report',
		'jat_search_intel_visibility_sync',
		'jat_search_intel_weekly_digest',
	);
	foreach ( $hooks as $hook ) {
		wp_clear_scheduled_hook( $hook );
	}

	// Options and transients (all share the jat_search_intel_ prefix).
	// phpcs:disable WordPress.DB.DirectDatabaseQuery
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( 'jat_search_intel_' ) . '%',
			$wpdb->esc_like( '_transient_jat_search_intel_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_jat_search_intel_' ) . '%'
		)
	);

	// Post meta and user meta.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
			$wpdb->esc_like( '_jat_search_intel_' ) . '%'
		)
	);
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->termmeta} WHERE meta_key LIKE %s",
			$wpdb->esc_like( '_jat_search_intel_' ) . '%'
		)
	);
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
			$wpdb->esc_like( 'jat_search_intel_' ) . '%'
		)
	);

	// Custom tables.
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}jat_search_intel_redirects" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}jat_search_intel_404_log" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}jat_search_intel_rankings" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
	// phpcs:enable WordPress.DB.DirectDatabaseQuery

	// Role and capabilities.
	remove_role( 'jat_search_intel_seo_editor' );
	foreach ( wp_roles()->role_objects as $role ) {
		$role->remove_cap( 'jat_search_intel_manage_settings' );
		$role->remove_cap( 'jat_search_intel_view_scores' );
	}
}

if ( is_multisite() ) {
	if ( jat_search_intel_uninstall_opted_in() ) {
		delete_site_option( 'jat_search_intel_network_defaults' );
	}
	$jat_search_intel_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);
	foreach ( $jat_search_intel_site_ids as $jat_search_intel_site_id ) {
		switch_to_blog( $jat_search_intel_site_id );
		if ( jat_search_intel_uninstall_opted_in() ) {
			jat_search_intel_uninstall_site();
		}
		restore_current_blog();
	}
} elseif ( jat_search_intel_uninstall_opted_in() ) {
	jat_search_intel_uninstall_site();
}
