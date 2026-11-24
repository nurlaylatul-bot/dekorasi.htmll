<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

( function () {
	global $wpdb;

	$settings = get_option( '_allcoach_settings', [] );

	if ( empty( $settings['delete_data_on_uninstall'] ) ) {
		return;
	}

	// Every table any AllCoach migration can create, Pro's included: opting into
	// delete_data_on_uninstall is an explicit request to leave nothing behind, and
	// a site that once ran Pro still has those tables after reverting to Free.
	// DROP TABLE IF EXISTS makes the Pro entries a no-op when they were never made.
	// This file is shared with the Pro repo — keep both copies identical.
	$tables = [
		$wpdb->prefix . 'allcoach_consents',
		$wpdb->prefix . 'allcoach_activity_completions',
		$wpdb->prefix . 'allcoach_session_bookings',
		$wpdb->prefix . 'allcoach_enrollments',
		$wpdb->prefix . 'allcoach_order_items',
		$wpdb->prefix . 'allcoach_orders',
		$wpdb->prefix . 'allcoach_demo_sessions',
		$wpdb->prefix . 'allcoach_notes',
		$wpdb->prefix . 'allcoach_contract_signatures',
	];

	foreach ( $tables as $table ) {
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
	}

	$post_types = [
		'allcoach_program',
		'allcoach_session',
		'allcoach_activity',
		'allcoach_section',
		'allcoach_avail',
		'allcoach_act_type',
		'allcoach_contract',
	];

	foreach ( $post_types as $post_type ) {
		$posts = get_posts(
			[
				'post_type'      => $post_type,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			]
		);

		foreach ( $posts as $post_id ) {
			wp_delete_post( $post_id, true );
		}
	}

	$taxonomies = [ 'allcoach_program_cat', 'allcoach_act_group' ];

	foreach ( $taxonomies as $taxonomy ) {
		$terms = get_terms(
			[
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'fields'     => 'ids',
			]
		);

		if ( is_array( $terms ) ) {
			foreach ( $terms as $term_id ) {
				wp_delete_term( $term_id, $taxonomy );
			}
		}
	}

	$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE '\_allcoach\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.SlowDBQuery.slow_db_query_meta_key

	remove_role( 'allcoach_coach' );
	remove_role( 'allcoach_client' );

	$admin_role = get_role( 'administrator' );
	if ( $admin_role ) {
		foreach ( $admin_role->capabilities as $cap => $granted ) {
			if ( str_starts_with( $cap, 'allcoach_' ) || str_starts_with( $cap, 'edit_allcoach_' ) || str_starts_with( $cap, 'delete_allcoach_' ) || str_starts_with( $cap, 'publish_allcoach_' ) || str_starts_with( $cap, 'read_allcoach_' ) || str_starts_with( $cap, 'read_private_allcoach_' ) || str_starts_with( $cap, 'edit_others_allcoach_' ) || str_starts_with( $cap, 'delete_others_allcoach_' ) || str_starts_with( $cap, 'delete_private_allcoach_' ) || str_starts_with( $cap, 'delete_published_allcoach_' ) || str_starts_with( $cap, 'manage_allcoach_' ) || str_starts_with( $cap, 'view_allcoach_' ) || str_starts_with( $cap, 'complete_allcoach_' ) ) {
				$admin_role->remove_cap( $cap );
			}
		}
	}

	$options = [
		'_allcoach_settings',
		'_allcoach_features',
		'_allcoach_enabled_modules',
		'_allcoach_migrations',
		'_allcoach_db_version',
		'_allcoach_google_credentials',
		'_allcoach_page_ids',
		'_allcoach_addon_states',
		'_allcoach_known_addons',
		'allcoach_rewrite_version',
	];

	foreach ( $options as $option ) {
		delete_option( $option );
	}

	$upload_dir = wp_upload_dir();
	$logs_dir   = $upload_dir['basedir'] . '/allcoach-logs';

	if ( is_dir( $logs_dir ) ) {
		$files = glob( $logs_dir . '/*' );
		if ( $files ) {
			foreach ( $files as $file ) {
				if ( is_file( $file ) ) {
					wp_delete_file( $file );
				}
			}
		}
		rmdir( $logs_dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
	}

	flush_rewrite_rules();
} )();
