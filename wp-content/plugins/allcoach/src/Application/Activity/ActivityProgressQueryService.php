<?php

declare(strict_types=1);

namespace AllCoach\Application\Activity;

defined( 'ABSPATH' ) || exit;

use AllCoach\Domain\Activity\ActivityCompletion;
use AllCoach\Domain\Activity\ActivityCompletionRepository;
use AllCoach\Domain\Activity\ActivityRepository;

class ActivityProgressQueryService {

	public function __construct(
		private readonly ActivityCompletionRepository $completionRepository,
		private readonly ActivityRepository $activityRepository,
	) {}

	/**
	 * @return array{completed: int, total: int, percentage: float, completed_activity_ids: int[]}
	 */
	public function getProgress( int $enrollmentId, int $programId ): array {
		$totalActivities = count( $this->activityRepository->findByProgram( $programId ) );
		$completedCount  = $this->completionRepository->countByEnrollment( $enrollmentId );
		$completions     = $this->completionRepository->findByEnrollment( $enrollmentId );
		$completedIds    = array_map(
			fn( ActivityCompletion $c ) => $c->getActivityId(),
			$completions
		);

		$percentage = $totalActivities > 0
			? round( ( $completedCount / $totalActivities ) * 100, 1 )
			: 0.0;

		return [
			'completed'              => $completedCount,
			'total'                  => $totalActivities,
			'percentage'             => $percentage,
			'completed_activity_ids' => $completedIds,
		];
	}

	/** @return ActivityCompletion[] */
	public function findByEnrollment( int $enrollmentId ): array {
		return $this->completionRepository->findByEnrollment( $enrollmentId );
	}

	/** @return ActivityCompletion[] */
	public function findByClient( int $clientId, int $page = 1, int $perPage = 50 ): array {
		$offset = ( $page - 1 ) * $perPage;
		return $this->completionRepository->findByClient( $clientId, $perPage, $offset );
	}

	public function isCompleted( int $clientId, int $activityId, int $enrollmentId ): bool {
		return $this->completionRepository->isCompleted( $clientId, $activityId, $enrollmentId );
	}
}
