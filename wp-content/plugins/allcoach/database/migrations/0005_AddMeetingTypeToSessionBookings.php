<?php

declare(strict_types=1);

namespace AllCoach\Database\Migrations;

defined( 'ABSPATH' ) || exit;

use AllCoach\Infrastructure\Database\Migration;

// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

class AddMeetingTypeToSessionBookings extends Migration {

	public function up(): void {
		/** @var \wpdb $wpdb */
		global $wpdb;

		$table   = $this->table( 'allcoach_session_bookings' );
		$columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`", 0 );

		if ( ! in_array( 'meeting_type', $columns, true ) ) {
			$wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `meeting_type` VARCHAR(20) NOT NULL DEFAULT 'google_meet'" );
		}

		if ( ! in_array( 'location', $columns, true ) ) {
			$wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `location` VARCHAR(500) NULL DEFAULT NULL" );
		}
	}

	public function down(): void {
		/** @var \wpdb $wpdb */
		global $wpdb;

		$table   = $this->table( 'allcoach_session_bookings' );
		$columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`", 0 );

		if ( in_array( 'meeting_type', $columns, true ) ) {
			$wpdb->query( "ALTER TABLE `{$table}` DROP COLUMN `meeting_type`" );
		}

		if ( in_array( 'location', $columns, true ) ) {
			$wpdb->query( "ALTER TABLE `{$table}` DROP COLUMN `location`" );
		}
	}
}
