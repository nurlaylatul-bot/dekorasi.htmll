<?php

declare(strict_types=1);

namespace AllCoach\Frontend\Controllers;

defined( 'ABSPATH' ) || exit;

use AllCoach\Application\Activity\ActivityProgressQueryService;
use AllCoach\Application\Availability\AvailabilityQueryService;
use AllCoach\Application\Booking\SessionBookingQueryService;
use AllCoach\Application\Order\OrderQueryService;
use AllCoach\Domain\Activity\ActivityCompletionRepository;
use AllCoach\Domain\Activity\ActivityRepository;
use AllCoach\Domain\Order\OrderFilter;
use AllCoach\Domain\Program\ProgramRepository;
use AllCoach\Domain\Session\SessionRepository;
use AllCoach\Frontend\Router;
use AllCoach\Support\TemplateLoader;

class ProgramController extends BaseController {

	public function __construct(
		TemplateLoader $templateLoader,
		private readonly OrderQueryService $orderQueryService,
		private readonly ProgramRepository $programRepository,
		private readonly SessionBookingQueryService $bookingQueryService,
		private readonly SessionRepository $sessionRepository,
		private readonly AvailabilityQueryService $availabilityQueryService,
		private readonly ActivityRepository $activityRepository,
		private readonly ActivityProgressQueryService $activityProgressQueryService,
		private readonly ActivityCompletionRepository $activityCompletionRepository,
	) {
		parent::__construct( $templateLoader );
	}

	public function index(): void {
		$user = wp_get_current_user();

		$perPage     = 5;
		$currentPage = $this->getCurrentPage();

		$enrollments = $this->orderQueryService->findWithFilter(
			new OrderFilter( clientId: $user->ID ),
			$currentPage,
			$perPage
		);
		$totalItems  = $this->orderQueryService->getTotalCount();

		if ( $totalItems === 1 && ! empty( $enrollments ) ) {
			$this->redirect( Router::url( 'programs', $enrollments[0]->programId ) );
		}

		$progressMap = [];
		foreach ( $enrollments as $enrollment ) {
			if ( $enrollment->status !== 'confirmed' ) {
				continue;
			}

			$session       = $this->sessionRepository->findOneByProgramId( $enrollment->programId );
			$totalSessions = $session ? $session->getRepetitionCount() : 0;

			$progressMap[ $enrollment->programId ] = $this->bookingQueryService->getProgress(
				$enrollment->enrollmentId,
				$totalSessions
			);
		}

		$this->render(
			'programs/index.php',
			[
				'pageTitle'   => __( 'My Programs', 'allcoach' ),
				'enrollments' => $enrollments,
				'progressMap' => $progressMap,
				'currentPage' => $currentPage,
				'totalItems'  => $totalItems,
				'perPage'     => $perPage,
			]
		);
	}

	public function show(): void {
		$programId = $this->getEndpointValue();

		if ( ! $programId ) {
			$this->redirect( Router::url( 'programs' ) );
		}

		$user    = wp_get_current_user();
		$program = $this->programRepository->findById( $programId );

		if ( ! $program ) {
			$this->render(
				'errors/404.php',
				[
					'pageTitle' => __( 'Not Found', 'allcoach' ),
					'message'   => __( 'Program not found.', 'allcoach' ),
				]
			);
			return;
		}

		$bookProgramId = isset( $_GET['book'] ) ? absint( $_GET['book'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		if ( $bookProgramId > 0 && $bookProgramId === $programId ) {
			$enrollments = $this->orderQueryService->findWithFilter(
				new OrderFilter( clientId: $user->ID, programId: $programId, status: 'confirmed' ),
				1,
				1
			);
			$enrollment  = $enrollments[0] ?? null;

			if ( $enrollment ) {
				$session         = $this->sessionRepository->findOneByProgramId( $program->getId() );
				$durationMinutes = $session ? $session->getDurationMinutes() : 60;
				$coachId         = $program->getCoachId();

				$availableDays = [];
				if ( $coachId ) {
					$availabilities = $this->availabilityQueryService->findByUserOrDefault( $coachId, 1, 50 );
					$dayMap         = [];
					foreach ( $availabilities as $availability ) {
						for ( $day = 0; $day <= 6; $day++ ) {
							if ( $availability->getSchedule()->isDayAvailable( $day ) ) {
								$dayMap[ $day ] = true;
							}
						}
					}
					$availableDays = array_values( array_keys( $dayMap ) );
				}

				$totalSessions = $session ? $session->getRepetitionCount() : 0;
				$progress      = $this->bookingQueryService->getProgress( $enrollment->enrollmentId, $totalSessions );

				$this->render(
					'booking/index.php',
					[
						'pageTitle'         => __( 'Book an Appointment', 'allcoach' ),
						'enrollment'        => $enrollment,
						'programTitle'      => $program->getTitle(),
						'backUrl'           => Router::url( 'programs', $programId ),
						'availableDays'     => $availableDays,
						'durationMinutes'   => $durationMinutes,
						'remainingSessions' => $progress['remaining'],
					]
				);
				return;
			}
		}

		$activityId = isset( $_GET['activity'] ) ? absint( $_GET['activity'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		if ( $activityId > 0 ) {
			$allActivities = $this->activityRepository->findByProgram( $programId );

			$activity     = null;
			$prevActivity = null;
			$nextActivity = null;
			foreach ( $allActivities as $idx => $act ) {
				if ( $act->getId() === $activityId ) {
					$activity     = $act;
					$prevActivity = $allActivities[ $idx - 1 ] ?? null;
					$nextActivity = $allActivities[ $idx + 1 ] ?? null;
					break;
				}
			}

			if ( ! $activity ) {
				$this->render(
					'errors/404.php',
					[
						'pageTitle' => __( 'Not Found', 'allcoach' ),
						'message'   => __( 'Activity not found.', 'allcoach' ),
					]
				);
				return;
			}

			$enrollments      = $this->orderQueryService->findWithFilter(
				new OrderFilter( clientId: $user->ID, programId: $programId ),
				1,
				1
			);
			$enrollment       = $enrollments[0] ?? null;
			$isActive         = $enrollment && $enrollment->status === 'confirmed';
			$isCompleted      = false;
			$activityProgress = null;
			$responseData     = [];

			if ( $enrollment ) {
				$activityProgress = $this->activityProgressQueryService->getProgress(
					$enrollment->enrollmentId,
					$programId
				);
				$isCompleted      = in_array( $activityId, $activityProgress['completed_activity_ids'], true );

				if ( $isCompleted ) {
					$completion   = $this->activityCompletionRepository->findByClientAndActivity(
						$user->ID,
						$activityId,
						$enrollment->enrollmentId
					);
					$responseData = $completion ? $completion->getResponseData() : [];

					/**
					 * Filters whether the activity is considered completed for this enrollment.
					 *
					 * @param bool                                  $isCompleted Whether the activity is completed.
					 * @param \AllCoach\Domain\Activity\Activity    $activity    Activity domain object.
					 * @param \AllCoach\Application\Order\OrderView $enrollment Enrollment/order view.
					 */
					$isCompleted = apply_filters( 'allcoach:activity:is_completed', $isCompleted, $activity, $enrollment );
				}
			}

			$this->render(
				'activities/single.php',
				[
					'pageTitle'        => $program->getTitle(),
					'program'          => $program,
					'activity'         => $activity,
					'enrollment'       => $enrollment,
					'isActive'         => $isActive,
					'isCompleted'      => $isCompleted,
					'activityProgress' => $activityProgress,
					'responseData'     => $responseData,
					'prevActivity'     => $prevActivity,
					'nextActivity'     => $nextActivity,
					'allActivities'    => $allActivities,
				]
			);
			return;
		}

		$enrollments = $this->orderQueryService->findWithFilter(
			new OrderFilter(
				clientId: $user->ID,
				programId: $programId,
			),
			1,
			1
		);
		$enrollment  = $enrollments[0] ?? null;
		$isActive    = $enrollment && $enrollment->status === 'confirmed';

		$bookings         = [];
		$progress         = null;
		$activities       = [];
		$activityProgress = null;
		if ( $isActive ) {
			$bookings = $this->bookingQueryService->findByEnrollment( $enrollment->enrollmentId );

			$session       = $this->sessionRepository->findOneByProgramId( $programId );
			$totalSessions = $session ? $session->getRepetitionCount() : 0;

			$progress = $this->bookingQueryService->getProgress(
				$enrollment->enrollmentId,
				$totalSessions
			);

			$activities = $this->activityRepository->findByProgram( $programId );
			if ( ! empty( $activities ) ) {
				$activityProgress = $this->activityProgressQueryService->getProgress(
					$enrollment->enrollmentId,
					$programId
				);
			}
		}

		$today = wp_date( 'Y-m-d' );

		$this->render(
			'programs/single.php',
			[
				'pageTitle'        => $program->getTitle(),
				'program'          => $program,
				'enrollment'       => $enrollment,
				'isActive'         => $isActive,
				'bookings'         => $bookings,
				'progress'         => $progress,
				'activities'       => $activities,
				'activityProgress' => $activityProgress,
				'today'            => $today,
			]
		);
	}
}
