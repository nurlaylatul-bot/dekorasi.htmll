<?php

declare(strict_types=1);

namespace AllCoach\Modules\Demo;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

class DemoSessionStore {

	private const TTL_SECONDS = 8 * HOUR_IN_SECONDS;

	private string $table;

	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'allcoach_demo_sessions';
	}

	public function create( int $demoUserId, int $originalUserId, int $ttl = self::TTL_SECONDS ): void {
		global $wpdb;

		$wpdb->replace(
			$this->table,
			[
				'demo_user_id'     => $demoUserId,
				'original_user_id' => $originalUserId,
				'created_at'       => current_time( 'mysql', true ),
				'expires_at'       => gmdate( 'Y-m-d H:i:s', time() + $ttl ),
			],
			[ '%d', '%d', '%s', '%s' ]
		);
	}

	public function findOriginalUserId( int $demoUserId ): ?int {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT original_user_id FROM {$this->table}
				WHERE demo_user_id = %d AND expires_at > %s
				LIMIT 1",
				$demoUserId,
				current_time( 'mysql', true )
			),
			ARRAY_A
		);

		return $row ? (int) $row['original_user_id'] : null;
	}

	public function delete( int $demoUserId ): void {
		global $wpdb;
		$wpdb->delete( $this->table, [ 'demo_user_id' => $demoUserId ], [ '%d' ] );
	}

	public function purgeExpired(): void {
		global $wpdb;

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$this->table} WHERE expires_at < %s",
				current_time( 'mysql', true )
			)
		);
	}
}
