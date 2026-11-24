<?php

declare(strict_types=1);

namespace AllCoach\Frontend\Controllers;

defined( 'ABSPATH' ) || exit;

use AllCoach\Application\Availability\AvailabilityQueryService;
use AllCoach\Application\Booking\SessionBookingQueryService;
use AllCoach\Application\Order\OrderQueryService;
use AllCoach\Domain\Booking\SessionBookingFilter;
use AllCoach\Domain\Order\OrderFilter;
use AllCoach\Domain\Program\ProgramRepository;
use AllCoach\Domain\Session\SessionRepository;
use AllCoach\Frontend\Router;
use AllCoach\Support\TemplateLoader;

class BookingController extends BaseController {

	public function __construct(
		TemplateLoader $templateLoader,
		private readonly SessionBookingQueryService $bookingQueryService,
		private readonly OrderQueryService $orderQueryService,
		private readonly ProgramRepository $programRepository,
		private readonly SessionRepository $sessionRepository,
		private readonly AvailabilityQueryService $availabilityQueryService,
	) {
		parent::__construct( $templateLoader );
	}

	public function index(): void {
		$user = wp_get_current_user();

		$bookProgramId = isset( $_GET['book'] ) ? absint( $_GET['book'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		if ( $bookProgramId > 0 ) {
			$this->renderBookingFlow( $user, $bookProgramId, Router::url( 'appointments' ) );
			return;
		}

		$perPage     = 5;
		$currentPage = $this->getCurrentPage();

		$bookings = $this->bookingQueryService->findWithFilter(
			new SessionBookingFilter( clientId: $user->ID ),
			1,
			1000
		);

		$enrollments = $this->orderQueryService->findWithFilter(
			new OrderFilter( clientId: $user->ID ),
			1,
			50
		);

		$today = wp_date( 'Y-m-d' );

		$progressMap = [];
		$durationMap = [];
		foreach ( $enrollments as $enrollment ) {
			if ( $enrollment->status !== 'confirmed' ) {
				continue;
			}
			$session                                  = $this->sessionRepository->findOneByProgramId( $enrollment->programId );
			$totalSessions                            = $session ? $session->getRepetitionCount() : 0;
			$progressMap[ $enrollment->enrollmentId ] = $this->bookingQueryService->getProgress( $enrollment->enrollmentId, $totalSessions );
			$durationMap[ $enrollment->enrollmentId ] = $session ? $session->getDurationMinutes() : 60;
		}

		$this->render(
			'sessions/index.php',
			[
				'pageTitle'   => __( 'My Appointments', 'allcoach' ),
				'bookings'    => $bookings,
				'enrollments' => $enrollments,
				'today'       => $today,
				'currentPage' => $currentPage,
				'perPage'     => $perPage,
				'progressMap' => $progressMap,
				'durationMap' => $durationMap,
			]
		);
	}

	private function renderBookingFlow( \WP_User $user, int $programId, string $backUrl ): void {
		$enrollments = $this->orderQueryService->findWithFilter(
			new OrderFilter( clientId: $user->ID, programId: $programId, status: 'confirmed' ),
			1,
			1
		);
		$enrollment  = $enrollments[0] ?? null;

		if ( ! $enrollment ) {
			$this->redirect( $backUrl );
		}

		$program         = $this->programRepository->findById( $enrollment->programId );
		$session         = $program ? $this->sessionRepository->findOneByProgramId( $program->getId() ) : null;
		$durationMinutes = $session ? $session->getDurationMinutes() : 60;
		$coachId         = $program ? $program->getCoachId() : 0;

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
				'programTitle'      => $enrollment->programTitle ?? __( 'Program', 'allcoach' ),
				'backUrl'           => $backUrl,
				'availableDays'     => $availableDays,
				'durationMinutes'   => $durationMinutes,
				'remainingSessions' => $progress['remaining'],
			]
		);
	}
}
