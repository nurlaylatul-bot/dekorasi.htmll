<?php

declare(strict_types=1);

namespace AllCoach\Frontend\Controllers;

defined( 'ABSPATH' ) || exit;

use AllCoach\Application\Activity\ActivityProgressQueryService;
use AllCoach\Application\Booking\SessionBookingQueryService;
use AllCoach\Application\Order\OrderQueryService;
use AllCoach\Domain\Activity\ActivityRepository;
use AllCoach\Domain\Booking\SessionBookingFilter;
use AllCoach\Domain\Order\OrderFilter;
use AllCoach\Domain\Session\SessionRepository;
use AllCoach\Support\TemplateLoader;

class DashboardController extends BaseController {

	private const MAX_PENDING_ACTIVITIES = 5;

	public function __construct(
		TemplateLoader $templateLoader,
		private readonly OrderQueryService $orderQueryService,
		private readonly SessionBookingQueryService $bookingQueryService,
		private readonly SessionRepository $sessionRepository,
		private readonly ActivityRepository $activityRepository,
		private readonly ActivityProgressQueryService $activityProgressQueryService,
	) {
		parent::__construct( $templateLoader );
	}

	public function index(): void {
		$user  = wp_get_current_user();
		$today = wp_date( 'Y-m-d' );

		$enrollments = $this->orderQueryService->findWithFilter(
			new OrderFilter( clientId: $user->ID ),
			1,
			50
		);

		$todayBookings = $this->bookingQueryService->findWithFilter(
			new SessionBookingFilter(
				clientId: $user->ID,
				fromDate: $today,
				toDate: $today,
				excludeCancelled: true
			),
			1,
			10
		);

		$progressMap       = [];
		$pendingActivities = [];
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

			$activityProgress = $this->activityProgressQueryService->getProgress(
				$enrollment->enrollmentId,
				$enrollment->programId
			);
			$completedActivityIds = $activityProgress['completed_activity_ids'];

			foreach ( $this->activityRepository->findByProgram( $enrollment->programId ) as $activity ) {
				if ( in_array( $activity->getId(), $completedActivityIds, true ) ) {
					continue;
				}

				$pendingActivities[] = [
					'activity'     => $activity,
					'programId'    => $enrollment->programId,
					'programTitle' => $enrollment->programTitle ?? __( 'Program', 'allcoach' ),
				];
			}
		}

		usort(
			$pendingActivities,
			fn( array $a, array $b ) => [ $a['activity']->getWeek(), $a['activity']->getDay(), $a['activity']->getPosition() ]
				<=> [ $b['activity']->getWeek(), $b['activity']->getDay(), $b['activity']->getPosition() ]
		);
		$pendingActivities = array_slice( $pendingActivities, 0, self::MAX_PENDING_ACTIVITIES );

		$this->render(
			'dashboard/index.php',
			[
				'pageTitle'         => __( 'Dashboard', 'allcoach' ),
				'enrollments'       => $enrollments,
				'todayBookings'     => $todayBookings,
				'progressMap'       => $progressMap,
				'pendingActivities' => $pendingActivities,
				'today'             => $today,
			]
		);
	}
}
