<?php

declare(strict_types=1);

namespace AllCoach\Database\Migrations;

defined( 'ABSPATH' ) || exit;

use AllCoach\Infrastructure\Database\Migration;

class CreateInitialTables extends Migration {

	public function up(): void {
		$charset = $this->charsetCollate();

		$this->dbDelta(
			"CREATE TABLE IF NOT EXISTS {$this->table('allcoach_orders')} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			payment_method varchar(30) NOT NULL DEFAULT 'manual',
			currency varchar(3) NOT NULL DEFAULT 'USD',
			subtotal decimal(10,2) NOT NULL DEFAULT 0.00,
			discount decimal(10,2) NOT NULL DEFAULT 0.00,
			tax decimal(10,2) NOT NULL DEFAULT 0.00,
			total decimal(10,2) NOT NULL DEFAULT 0.00,
			payment_reference varchar(255) DEFAULT NULL,
			notes text DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime DEFAULT NULL,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY status (status),
			KEY payment_method (payment_method)
		) {$charset};"
		);

		$this->dbDelta(
			"CREATE TABLE IF NOT EXISTS {$this->table('allcoach_order_items')} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			order_id bigint(20) unsigned NOT NULL,
			program_id bigint(20) unsigned NOT NULL,
			price decimal(10,2) NOT NULL DEFAULT 0.00,
			quantity smallint unsigned NOT NULL DEFAULT 1,
			total decimal(10,2) NOT NULL DEFAULT 0.00,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY order_id (order_id),
			KEY program_id (program_id)
		) {$charset};"
		);

		$this->dbDelta(
			"CREATE TABLE IF NOT EXISTS {$this->table('allcoach_enrollments')} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			program_id bigint(20) unsigned NOT NULL,
			order_item_id bigint(20) unsigned NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'active',
			enrolled_at datetime NOT NULL,
			expires_at datetime DEFAULT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY program_id (program_id),
			KEY order_item_id (order_item_id),
			KEY status (status),
			UNIQUE KEY user_program_order_item (user_id, program_id, order_item_id)
		) {$charset};"
		);

		$this->dbDelta(
			"CREATE TABLE IF NOT EXISTS {$this->table('allcoach_session_bookings')} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			enrollment_id bigint(20) unsigned NOT NULL,
			client_id bigint(20) unsigned NOT NULL,
			coach_id bigint(20) unsigned NOT NULL,
			program_id bigint(20) unsigned NOT NULL,
			scheduled_date date NOT NULL,
			start_time time NOT NULL,
			end_time time NOT NULL,
			session_number smallint unsigned NOT NULL DEFAULT 1,
			status varchar(20) NOT NULL DEFAULT 'scheduled',
			notes text DEFAULT NULL,
			timezone varchar(100) DEFAULT '' NOT NULL,
			meeting_url varchar(500) DEFAULT NULL,
			calendar_event_id varchar(255) DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime DEFAULT NULL,
			PRIMARY KEY (id),
			KEY enrollment_id (enrollment_id),
			KEY client_id (client_id),
			KEY coach_id (coach_id),
			KEY program_id (program_id),
			KEY scheduled_date (scheduled_date),
			KEY status (status),
			KEY coach_schedule (coach_id, scheduled_date, start_time, end_time),
			KEY client_schedule (client_id, scheduled_date, start_time, end_time)
		) {$charset};"
		);

		$this->dbDelta(
			"CREATE TABLE IF NOT EXISTS {$this->table('allcoach_activity_completions')} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			enrollment_id bigint(20) unsigned NOT NULL,
			client_id bigint(20) unsigned NOT NULL,
			activity_id bigint(20) unsigned NOT NULL,
			program_id bigint(20) unsigned NOT NULL,
			response_data longtext DEFAULT NULL,
			completed_at datetime NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY client_activity_enrollment (client_id, activity_id, enrollment_id),
			KEY enrollment_id (enrollment_id),
			KEY client_id (client_id),
			KEY activity_id (activity_id),
			KEY program_id (program_id)
		) {$charset};"
		);
	}

	public function down(): void {
		/** @var \wpdb */
		global $wpdb;

		$tables = [
			$this->table( 'allcoach_activity_completions' ),
			$this->table( 'allcoach_session_bookings' ),
			$this->table( 'allcoach_enrollments' ),
			$this->table( 'allcoach_order_items' ),
			$this->table( 'allcoach_orders' ),
		];

		foreach ( $tables as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter
		}
	}
}
