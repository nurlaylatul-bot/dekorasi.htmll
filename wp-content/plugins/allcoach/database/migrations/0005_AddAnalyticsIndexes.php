<?php

declare(strict_types=1);

namespace AllCoach\Database\Migrations;

defined( 'ABSPATH' ) || exit;

use AllCoach\Infrastructure\Database\Migration;

/**
 * Add the date indexes the analytics dashboard relies on.
 *
 * The KPI totals, time-series and status breakdowns all filter orders by
 * `created_at` and enrollments by `enrolled_at`, but neither column was
 * indexed — so on sites with a lot of history every dashboard load did a full
 * table scan. `session_bookings.scheduled_date` is already indexed.
 *
 * Uses guarded ALTERs (not dbDelta, which only manages CREATE TABLE bodies)
 * so it is safe to re-run and easy to roll back.
 */
class AddAnalyticsIndexes extends Migration {

	/** @var array<int, array{table: string, index: string, columns: string}> */
	private const INDEXES = [
		[ 'table' => 'allcoach_orders', 'index' => 'analytics_created_at', 'columns' => 'created_at' ],
		[ 'table' => 'allcoach_enrollments', 'index' => 'analytics_enrolled_at', 'columns' => 'enrolled_at' ],
	];

	public function up(): void {
		foreach ( self::INDEXES as $idx ) {
			$this->addIndexIfMissing( $idx['table'], $idx['index'], $idx['columns'] );
		}
	}

	public function down(): void {
		foreach ( self::INDEXES as $idx ) {
			$this->dropIndexIfExists( $idx['table'], $idx['index'] );
		}
	}

	/**
	 * This runs on every `admin_init` until it completes without throwing
	 * (Migrator only marks a migration "ran" after `up()` returns) — so a
	 * fatal here doesn't just fail once, it fails every admin page load until
	 * fixed. Never let an ALTER error propagate: log it and move on.
	 */
	private function addIndexIfMissing( string $table, string $index, string $columns ): void {
		if ( $this->indexExists( $table, $index ) ) {
			return;
		}
		global $wpdb;
		$tableName = $this->table( $table );
		try {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$tableName} ADD INDEX {$index} ({$columns})" );
		} catch ( \Throwable $e ) {
			$this->logFailure( "Failed to add index {$index} on {$tableName}: " . $e->getMessage() );
		}
	}

	private function dropIndexIfExists( string $table, string $index ): void {
		if ( ! $this->indexExists( $table, $index ) ) {
			return;
		}
		global $wpdb;
		$tableName = $this->table( $table );
		try {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$tableName} DROP INDEX {$index}" );
		} catch ( \Throwable $e ) {
			$this->logFailure( "Failed to drop index {$index} on {$tableName}: " . $e->getMessage() );
		}
	}

	/**
	 * `SHOW INDEX` only needs privilege on this one table — unlike
	 * `information_schema.statistics`, which some hosts restrict and which can
	 * then silently report "missing" for an index that already exists,
	 * leading straight into the duplicate-key ALTER failure above.
	 */
	private function indexExists( string $table, string $index ): bool {
		global $wpdb;
		$tableName = $this->table( $table );
		try {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					"SHOW INDEX FROM {$tableName} WHERE Key_name = %s",
					$index
				)
			);
			return ! empty( $rows );
		} catch ( \Throwable $e ) {
			// Can't confirm either way — assume it exists so we skip the ALTER
			// rather than risk a duplicate-key error on a table we can't inspect.
			$this->logFailure( "Failed to check index {$index} on {$tableName}: " . $e->getMessage() );
			return true;
		}
	}

	private function logFailure( string $message ): void {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( '[AllCoach] ' . $message );
	}
}
