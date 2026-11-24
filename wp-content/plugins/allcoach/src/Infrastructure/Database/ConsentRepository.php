<?php

declare(strict_types=1);

namespace AllCoach\Infrastructure\Database;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

/**
 * Stores GDPR consent records (user, timestamp, policy version) so they can be
 * queried and exported for compliance.
 */
class ConsentRepository {

	private string $table;

	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'allcoach_consents';
	}

	/**
	 * Record a consent event.
	 *
	 * @return int Inserted row id (0 on failure).
	 */
	public function record(
		int $userId,
		string $consentType,
		string $policyVersion,
		?int $policyPageId = null,
		?string $ipAddress = null
	): int {
		global $wpdb;

		$result = $wpdb->insert(
			$this->table,
			[
				'user_id'        => $userId,
				'consent_type'   => $consentType,
				'policy_version' => $policyVersion,
				'policy_page_id' => $policyPageId,
				'consented_at'   => current_time( 'mysql', true ),
				'ip_address'     => $ipAddress,
			],
			[ '%d', '%s', '%s', '%d', '%s', '%s' ]
		);

		return $result !== false ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Return all consent records for a user, newest first.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function findByUser( int $userId ): array {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE user_id = %d ORDER BY consented_at DESC",
				$userId
			),
			ARRAY_A
		) ?: [];
	}

	/**
	 * Delete all consent records for a user.
	 *
	 * @return int Number of records deleted.
	 */
	public function deleteByUser( int $userId ): int {
		global $wpdb;
		$result = $wpdb->delete( $this->table, [ 'user_id' => $userId ], [ '%d' ] );
		return $result !== false ? $result : 0;
	}
}
