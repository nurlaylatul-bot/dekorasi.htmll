<?php

declare(strict_types=1);

namespace AllCoach\Application\Activity;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\Events\EventDispatcher;
use AllCoach\Core\Exceptions\NotFoundException;
use AllCoach\Core\Exceptions\ValidationException;
use AllCoach\Domain\Activity\ActivityCompletion;
use AllCoach\Domain\Activity\ActivityCompletionRepository;
use AllCoach\Domain\Activity\ActivityRepository;
use AllCoach\Domain\Activity\Events\ActivityCompleted;
use AllCoach\Domain\Enrollment\EnrollmentRepository;
use AllCoach\Support\Logger;
use Carbon\CarbonImmutable;

class CompleteActivityService {

	public function __construct(
		private readonly ActivityCompletionRepository $completionRepository,
		private readonly ActivityRepository $activityRepository,
		private readonly EnrollmentRepository $enrollmentRepository,
		private readonly EventDispatcher $events,
		private readonly Logger $logger,
	) {}

	public function execute( ActivityCompletionDTO $dto ): ActivityCompletion {
		$enrollmentId = $dto->enrollmentId;
		$activityId   = $dto->activityId;

		$enrollment = $this->enrollmentRepository->findById( $enrollmentId );
		if ( ! $enrollment ) {
			throw NotFoundException::entity( esc_html__( 'Booking', 'allcoach' ), absint( $enrollmentId ) );
		}

		if ( ! $enrollment->isActive() ) {
			throw ValidationException::withErrors(
				[
					'booking' => esc_html__( 'This booking is not active.', 'allcoach' ),
				]
			);
		}

		$activity = $this->activityRepository->findById( $activityId );
		if ( ! $activity ) {
			throw NotFoundException::entity( esc_html__( 'Activity', 'allcoach' ), absint( $activityId ) );
		}

		if ( $activity->getProgramId() !== $enrollment->getProgramId() ) {
			throw ValidationException::withErrors(
				[
					'activity' => esc_html__( 'This activity does not belong to the booked program.', 'allcoach' ),
				]
			);
		}

		$existing = $this->completionRepository->findByClientAndActivity(
			$enrollment->getUserId(),
			$dto->activityId,
			$dto->enrollmentId
		);

		if ( $existing ) {
			$existing->setResponseData( $dto->responseData );
			$existing->setCompletedAt( CarbonImmutable::now() );

			$existing = $this->completionRepository->save( $existing );

			$this->logger->info(
				'Activity completion updated',
				[
					'completion_id' => $existing->getId(),
					'activity_id'   => $dto->activityId,
					'enrollment_id' => $dto->enrollmentId,
				]
			);

			$this->events->dispatch( new ActivityCompleted( $existing ) );

			return $existing;
		}

		$completion = new ActivityCompletion(
			enrollmentId: $dto->enrollmentId,
			clientId: $enrollment->getUserId(),
			activityId: $dto->activityId,
			programId: $enrollment->getProgramId(),
			responseData: $dto->responseData,
		);

		$completion = $this->completionRepository->save( $completion );

		$this->logger->info(
			'Activity completed',
			[
				'completion_id' => $completion->getId(),
				'activity_id'   => $dto->activityId,
				'enrollment_id' => $dto->enrollmentId,
				'client_id'     => $enrollment->getUserId(),
			]
		);

		$this->events->dispatch( new ActivityCompleted( $completion ) );

		return $completion;
	}
}
