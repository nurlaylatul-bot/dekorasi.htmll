<?php

declare(strict_types=1);

namespace AllCoach\Domain\Activity;

defined( 'ABSPATH' ) || exit;

interface ActivityCompletionRepository {

	public function findById( int $id ): ?ActivityCompletion;

	public function findByClientAndActivity( int $clientId, int $activityId, int $enrollmentId ): ?ActivityCompletion;

	/** @return ActivityCompletion[] */
	public function findByEnrollment( int $enrollmentId ): array;

	/** @return ActivityCompletion[] */
	public function findByClient( int $clientId, int $limit = 100, int $offset = 0 ): array;

	public function countByEnrollment( int $enrollmentId ): int;

	public function countByProgram( int $programId, int $clientId ): int;

	public function isCompleted( int $clientId, int $activityId, int $enrollmentId ): bool;

	public function save( ActivityCompletion $completion ): ActivityCompletion;

	public function delete( int $id ): bool;

	public function deleteByEnrollment( int $enrollmentId ): int;

	/**
	 * Delete all activity completions belonging to a client.
	 *
	 * @return int Number of completions deleted.
	 */
	public function deleteByClient( int $clientId ): int;
}
