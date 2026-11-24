<?php

declare(strict_types=1);

namespace AllCoach\Database\Migrations;

defined( 'ABSPATH' ) || exit;

use AllCoach\Infrastructure\Database\Migration;

// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

class AddDemoFlagsToTables extends Migration {

	private const TABLE_NAMES = [
		'allcoach_activity_completions',
		'allcoach_session_bookings',
		'allcoach_enrollments',
		'allcoach_order_items',
		'allcoach_orders',
	];

	public function up(): void {
		/** @var \wpdb $wpdb */
		global $wpdb;

		foreach ( self::TABLE_NAMES as $tableName ) {
			$table   = $this->table( $tableName );
			$columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`", 0 );

			if ( ! in_array( 'is_demo', $columns, true ) ) {
				$wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `is_demo` TINYINT(1) NOT NULL DEFAULT 0" );
			}

			if ( ! in_array( 'demo_token', $columns, true ) ) {
				$wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `demo_token` VARCHAR(64) NULL DEFAULT NULL" );
			}

			if ( ! in_array( 'expires_at', $columns, true ) ) {
				$wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `expires_at` DATETIME NULL DEFAULT NULL" );
			}
		}
	}

	public function down(): void {
		/** @var \wpdb $wpdb */
		global $wpdb;

		foreach ( self::TABLE_NAMES as $tableName ) {
			$table   = $this->table( $tableName );
			$columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`", 0 );

			if ( in_array( 'is_demo', $columns, true ) ) {
				$wpdb->query( "ALTER TABLE `{$table}` DROP COLUMN `is_demo`" );
			}

			if ( in_array( 'demo_token', $columns, true ) ) {
				$wpdb->query( "ALTER TABLE `{$table}` DROP COLUMN `demo_token`" );
			}

			if ( in_array( 'expires_at', $columns, true ) ) {
				$wpdb->query( "ALTER TABLE `{$table}` DROP COLUMN `expires_at`" );
			}
		}
	}
}
