<?php

declare(strict_types=1);

namespace AllCoach\Database\Migrations;

defined( 'ABSPATH' ) || exit;

use AllCoach\Infrastructure\Database\Migration;

class CreateDemoSessionsTable extends Migration {

	public function up(): void {
		$charset = $this->charsetCollate();

		$this->dbDelta(
			"CREATE TABLE IF NOT EXISTS {$this->table('allcoach_demo_sessions')} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			demo_user_id bigint(20) unsigned NOT NULL,
			original_user_id bigint(20) unsigned NOT NULL,
			created_at datetime NOT NULL,
			expires_at datetime NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY demo_user_id (demo_user_id),
			KEY expires_at (expires_at)
		) {$charset};"
		);
	}

	public function down(): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$this->table('allcoach_demo_sessions')}" );
	}
}
