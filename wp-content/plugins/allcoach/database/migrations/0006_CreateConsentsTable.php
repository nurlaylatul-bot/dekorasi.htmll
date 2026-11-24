<?php

declare(strict_types=1);

namespace AllCoach\Database\Migrations;

defined( 'ABSPATH' ) || exit;

use AllCoach\Infrastructure\Database\Migration;

class CreateConsentsTable extends Migration {

	public function up(): void {
		$charset = $this->charsetCollate();

		$this->dbDelta(
			"CREATE TABLE IF NOT EXISTS {$this->table('allcoach_consents')} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			consent_type varchar(30) NOT NULL DEFAULT 'checkout',
			policy_version varchar(50) NOT NULL DEFAULT '',
			policy_page_id bigint(20) unsigned DEFAULT NULL,
			consented_at datetime NOT NULL,
			ip_address varchar(45) DEFAULT NULL,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY consent_type (consent_type)
		) {$charset};"
		);
	}

	public function down(): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$this->table('allcoach_consents')}" );
	}
}
