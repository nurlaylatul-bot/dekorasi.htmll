<?php

declare(strict_types=1);

namespace AllCoach\Database\Migrations;

defined( 'ABSPATH' ) || exit;

use AllCoach\Infrastructure\Database\Migration;

// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

class AddConsentToOrders extends Migration {

	public function up(): void {
		/** @var \wpdb $wpdb */
		global $wpdb;

		$table   = $this->table( 'allcoach_orders' );
		$columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`", 0 );

		if ( ! in_array( 'consent', $columns, true ) ) {
			// Existing orders were already gated by the consent check in effect
			// at creation time (if any), so backfill them as consented.
			$wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `consent` TINYINT(1) NOT NULL DEFAULT 1" );
		}
	}

	public function down(): void {
		/** @var \wpdb $wpdb */
		global $wpdb;

		$table   = $this->table( 'allcoach_orders' );
		$columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`", 0 );

		if ( in_array( 'consent', $columns, true ) ) {
			$wpdb->query( "ALTER TABLE `{$table}` DROP COLUMN `consent`" );
		}
	}
}
