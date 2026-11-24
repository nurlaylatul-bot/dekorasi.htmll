<?php

declare(strict_types=1);

namespace AllCoach\Infrastructure\Database;

defined( 'ABSPATH' ) || exit;

use AllCoach\Domain\Activity\ActivityCompletion;
use AllCoach\Domain\Activity\ActivityCompletionRepository as ActivityCompletionRepositoryInterface;
use Carbon\CarbonImmutable;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter

class ActivityCompletionRepository implements ActivityCompletionRepositoryInterface {

	private string $table;

	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'allcoach_activity_completions';
	}

	public function findById( int $id ): ?ActivityCompletion {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE id = %d LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$id
			),
			ARRAY_A
		);

		return $row ? $this->hydrate( $row ) : null;
	}

	public function findByClientAndActivity( int $clientId, int $activityId, int $enrollmentId ): ?ActivityCompletion {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE client_id = %d AND activity_id = %d AND enrollment_id = %d LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$clientId,
				$activityId,
				$enrollmentId
			),
			ARRAY_A
		);

		return $row ? $this->hydrate( $row ) : null;
	}

	/** @return ActivityCompletion[] */
	public function findByEnrollment( int $enrollmentId ): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE enrollment_id = %d ORDER BY completed_at ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$enrollmentId
			),
			ARRAY_A
		) ?: [];

		return array_map( [ $this, 'hydrate' ], $rows );
	}

	public function findByClient( int $clientId, int $limit = 100, int $offset = 0 ): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE client_id = %d ORDER BY completed_at DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$clientId,
				$limit,
				$offset
			),
			ARRAY_A
		) ?: [];

		return array_map( [ $this, 'hydrate' ], $rows );
	}

	public function countByEnrollment( int $enrollmentId ): int {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table} WHERE enrollment_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$enrollmentId
			)
		);
	}

	public function countByProgram( int $programId, int $clientId ): int {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table} WHERE program_id = %d AND client_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$programId,
				$clientId
			)
		);
	}

	public function isCompleted( int $clientId, int $activityId, int $enrollmentId ): bool {
		return $this->findByClientAndActivity( $clientId, $activityId, $enrollmentId ) !== null;
	}

	public function save( ActivityCompletion $completion ): ActivityCompletion {
		return $completion->isNew()
			? $this->insert( $completion )
			: $this->update( $completion );
	}

	public function delete( int $id ): bool {
		global $wpdb;
		return $wpdb->delete( $this->table, [ 'id' => $id ], [ '%d' ] ) !== false;
	}

	public function deleteByEnrollment( int $enrollmentId ): int {
		global $wpdb;

		$result = $wpdb->delete( $this->table, [ 'enrollment_id' => $enrollmentId ], [ '%d' ] );

		return $result !== false ? $result : 0;
	}

	public function deleteByClient( int $clientId ): int {
		global $wpdb;

		$result = $wpdb->delete( $this->table, [ 'client_id' => $clientId ], [ '%d' ] );

		return $result !== false ? $result : 0;
	}

	private function insert( ActivityCompletion $ac ): ActivityCompletion {
		global $wpdb;

		$enrollmentsTable = $wpdb->prefix . 'allcoach_enrollments';
		$demoRow          = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT is_demo, demo_token, expires_at FROM {$enrollmentsTable} WHERE id = %d LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$ac->getEnrollmentId()
			),
			ARRAY_A
		);

		$isDemo    = (int) ( $demoRow['is_demo'] ?? 0 );
		$demoToken = $demoRow['demo_token'] ?? null;
		$expiresAt = $demoRow['expires_at'] ?? null;

		$result = $wpdb->insert(
			$this->table,
			[
				'enrollment_id' => $ac->getEnrollmentId(),
				'client_id'     => $ac->getClientId(),
				'activity_id'   => $ac->getActivityId(),
				'program_id'    => $ac->getProgramId(),
				'response_data' => wp_json_encode( $ac->getResponseData() ),
				'completed_at'  => $ac->getCompletedAt()->format( 'Y-m-d H:i:s' ),
				'created_at'    => $ac->getCreatedAt()->format( 'Y-m-d H:i:s' ),
				'is_demo'       => $isDemo,
				'demo_token'    => $demoToken,
				'expires_at'    => $expiresAt,
			],
			[ '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s' ]
		);

		if ( false === $result ) {
			throw new \RuntimeException( 'Failed to save activity completion.' );
		}

		return ActivityCompletion::reconstitute(
			id:           $wpdb->insert_id,
			enrollmentId: $ac->getEnrollmentId(),
			clientId:     $ac->getClientId(),
			activityId:   $ac->getActivityId(),
			programId:    $ac->getProgramId(),
			responseData: $ac->getResponseData(),
			completedAt:  $ac->getCompletedAt(),
			createdAt:    $ac->getCreatedAt(),
		);
	}

	private function update( ActivityCompletion $ac ): ActivityCompletion {
		global $wpdb;

		$result = $wpdb->update(
			$this->table,
			[
				'response_data' => wp_json_encode( $ac->getResponseData() ),
				'completed_at'  => $ac->getCompletedAt()->format( 'Y-m-d H:i:s' ),
			],
			[ 'id' => $ac->getId() ],
			[ '%s', '%s' ],
			[ '%d' ]
		);

		if ( $result === false ) {
			throw new \RuntimeException( 'Failed to update activity completion.' );
		}

		return $ac;
	}

	private function hydrate( array $row ): ActivityCompletion {
		$responseData = ! empty( $row['response_data'] )
			? (array) json_decode( $row['response_data'], true )
			: [];

		return ActivityCompletion::reconstitute(
			id:           (int) $row['id'],
			enrollmentId: (int) $row['enrollment_id'],
			clientId:     (int) $row['client_id'],
			activityId:   (int) $row['activity_id'],
			programId:    (int) $row['program_id'],
			responseData: $responseData,
			completedAt:  CarbonImmutable::parse( $row['completed_at'] ),
			createdAt:    CarbonImmutable::parse( $row['created_at'] ),
		);
	}
}
