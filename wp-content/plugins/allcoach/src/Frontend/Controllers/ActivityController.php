<?php

declare(strict_types=1);

namespace AllCoach\Frontend\Controllers;

defined( 'ABSPATH' ) || exit;

use AllCoach\Application\Activity\ActivityProgressQueryService;
use AllCoach\Application\Order\OrderQueryService;
use AllCoach\Domain\Activity\ActivityRepository;
use AllCoach\Domain\Order\OrderFilter;
use AllCoach\Domain\Program\ProgramRepository;
use AllCoach\Frontend\Router;
use AllCoach\Support\TemplateLoader;

class ActivityController extends BaseController {

	public function __construct(
		TemplateLoader $templateLoader,
		private readonly ActivityRepository $activityRepository,
		private readonly ProgramRepository $programRepository,
		private readonly OrderQueryService $orderQueryService,
		private readonly ActivityProgressQueryService $progressQueryService,
	) {
		parent::__construct( $templateLoader );
	}

	public function show(): void {
		$activityId = $this->getEndpointValue();

		if ( ! $activityId ) {
			$this->redirect( Router::url( 'programs' ) );
		}

		$user     = wp_get_current_user();
		$activity = $this->activityRepository->findById( $activityId );

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

		$program = $this->programRepository->findById( $activity->getProgramId() );

		$enrollments = $this->orderQueryService->findWithFilter(
			new OrderFilter(
				clientId: $user->ID,
				programId: $activity->getProgramId(),
			),
			1,
			1
		);
		$enrollment  = $enrollments[0] ?? null;

		$isCompleted = false;
		if ( $enrollment ) {
			$isCompleted = $this->progressQueryService->isCompleted(
				$user->ID,
				$activityId,
				$enrollment->enrollmentId,
			);
		}

		$pageTitle = $program ? $program->getTitle() : __( 'Activity', 'allcoach' );

		$this->render(
			'activities/single.php',
			[
				'pageTitle'   => $pageTitle,
				'activity'    => $activity,
				'program'     => $program,
				'enrollment'  => $enrollment,
				'isCompleted' => $isCompleted,
			]
		);
	}
}
