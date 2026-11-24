<?php

declare(strict_types=1);

namespace AllCoach\API\Controllers\V1;

defined( 'ABSPATH' ) || exit;

use AllCoach\API\RestController;
use AllCoach\Application\Availability\AvailabilityQueryService;
use AllCoach\Application\Booking\BookSessionService;
use AllCoach\Application\Booking\CancelSessionBookingService;
use AllCoach\Application\Booking\SessionBookingDTO;
use AllCoach\Domain\Booking\SessionBooking;
use AllCoach\Domain\Booking\SessionBookingFilter;
use AllCoach\Domain\Booking\SessionBookingRepository;
use AllCoach\Domain\Enrollment\EnrollmentRepository;
use AllCoach\Domain\Program\ProgramRepository;
use AllCoach\Domain\Session\SessionRepository;
use Carbon\CarbonImmutable;

class ClientBookingController extends RestController {

	protected string $restBase = 'client';

	public function __construct(
		private readonly EnrollmentRepository $enrollmentRepository,
		private readonly ProgramRepository $programRepository,
		private readonly SessionRepository $sessionRepository,
		private readonly SessionBookingRepository $sessionBookingRepository,
		private readonly AvailabilityQueryService $availabilityQueryService,
		private readonly BookSessionService $bookService,
		private readonly CancelSessionBookingService $cancelService,
	) {}

	public function registerRoutes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->restBase . '/booking-slots',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'getBookingSlots' ],
					'permission_callback' => fn() => $this->permClient(),
					'args'                => [
						'enrollment_id' => $this->argInteger(
							description: __( 'Enrollment ID to fetch slots for.', 'allcoach' ),
							required: true,
							min: 1,
						),
					],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->restBase . '/date-slots',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'getDateSlots' ],
					'permission_callback' => fn() => $this->permClient(),
					'args'                => [
						'enrollment_id' => $this->argInteger(
							description: __( 'Enrollment ID.', 'allcoach' ),
							required: true,
							min: 1,
						),
						'date'          => $this->argString(
							description: __( 'Date in Y-m-d format.', 'allcoach' ),
							required: true,
						),
					],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->restBase . '/book-session',
			[
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'bookSession' ],
					'permission_callback' => fn() => $this->permClient(),
					'args'                => [
						'enrollment_id'  => $this->argInteger(
							description: __( 'Enrollment to schedule a session for.', 'allcoach' ),
							required: true,
							min: 1,
						),
						'scheduled_date' => $this->argString(
							description: __( 'Session date (YYYY-MM-DD).', 'allcoach' ),
							required: true,
						),
						'start_time'     => $this->argString(
							description: __( 'Session start time (HH:MM, 24-hour).', 'allcoach' ),
							required: true,
						),
						'end_time'       => $this->argString(
							description: __( 'Session end time (HH:MM, 24-hour).', 'allcoach' ),
							required: true,
						),
						'timezone'       => $this->argString(
							description: __( 'Client timezone (IANA identifier, e.g. America/New_York).', 'allcoach' ),
						),
						'notes'          => $this->argString(
							description: __( 'Optional note from the client about this session.', 'allcoach' ),
							maxLength: 1000,
						),
					],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->restBase . '/cancel-session',
			[
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'cancelSession' ],
					'permission_callback' => fn() => $this->permClient(),
					'args'                => [
						'booking_id' => $this->argInteger(
							description: __( 'Session booking ID to cancel.', 'allcoach' ),
							required: true,
							min: 1,
						),
					],
				],
			]
		);
	}

	public function getBookingSlots( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$enrollmentId = (int) $request->get_param( 'enrollment_id' );
		$enrollment   = $this->enrollmentRepository->findById( $enrollmentId );

		if ( ! $enrollment || $enrollment->getUserId() !== get_current_user_id() ) {
			return $this->notFoundResponse( __( 'Enrollment', 'allcoach' ) );
		}

		if ( ! $enrollment->isActive() || ! $this->enrollmentRepository->isOrderPaid( $enrollmentId ) ) {
			return $this->errorResponse( __( 'This enrollment is not active.', 'allcoach' ) );
		}

		$program = $this->programRepository->findById( $enrollment->getProgramId() );
		if ( ! $program ) {
			return $this->notFoundResponse( __( 'Program', 'allcoach' ) );
		}

		$session = $this->sessionRepository->findOneByProgramId( $program->getId() );
		if ( ! $session ) {
			return $this->errorResponse( __( 'This program does not have session configuration.', 'allcoach' ) );
		}

		$coachId = $program->getCoachId();
		if ( ! $coachId ) {
			return $this->errorResponse( __( 'This program does not have an assigned coach.', 'allcoach' ) );
		}

		$availabilities    = $this->availabilityQueryService->findByUserOrDefault( $coachId, 1, 50 );
		$scheduledCount    = $this->sessionBookingRepository->countWithFilter( new SessionBookingFilter( enrollmentId: $enrollmentId ) );
		$remainingSessions = max( 0, $session->getRepetitionCount() - $scheduledCount );

		return $this->successResponse(
			[
				'enrollment_id'      => $enrollmentId,
				'program_id'         => $program->getId(),
				'program_title'      => $program->getTitle(),
				'coach_id'           => $coachId,
				'duration_minutes'   => $session->getDurationMinutes(),
				'remaining_sessions' => $remainingSessions,
				'total_sessions'     => $session->getRepetitionCount(),
				'scheduled_count'    => $scheduledCount,
				'availability'       => array_map( fn( $a ) => $a->toArray(), $availabilities ),
			]
		);
	}

	public function getDateSlots( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$enrollmentId = (int) $request->get_param( 'enrollment_id' );
		$date         = sanitize_text_field( $request->get_param( 'date' ) );

		$enrollment = $this->enrollmentRepository->findById( $enrollmentId );
		if ( ! $enrollment || $enrollment->getUserId() !== get_current_user_id() ) {
			return $this->notFoundResponse( __( 'Enrollment', 'allcoach' ) );
		}

		if ( ! $enrollment->isActive() || ! $this->enrollmentRepository->isOrderPaid( $enrollmentId ) ) {
			return $this->errorResponse( __( 'This enrollment is not active.', 'allcoach' ) );
		}

		$program = $this->programRepository->findById( $enrollment->getProgramId() );
		if ( ! $program ) {
			return $this->successResponse( [ 'slots' => [] ] );
		}

		$session  = $this->sessionRepository->findOneByProgramId( $program->getId() );
		$duration = $session ? max( 15, $session->getDurationMinutes() ) : 60;

		$availabilities = $this->availabilityQueryService->findByUserOrDefault( $program->getCoachId(), 1, 50 );
		$dateObj        = CarbonImmutable::createFromFormat( 'Y-m-d', $date );
		if ( ! $dateObj ) {
			return $this->successResponse( [ 'slots' => [] ] );
		}

		$dayOfWeek = (int) $dateObj->format( 'w' );

		$windows = [];
		foreach ( $availabilities as $availability ) {
			$schedule = $availability->getSchedule();
			if ( $schedule->isDayAvailable( $dayOfWeek ) ) {
				$day       = $schedule->getDay( $dayOfWeek );
				$windows[] = [
					'start' => $day['start_time'],
					'end'   => $day['end_time'],
				];
			}
		}

		if ( empty( $windows ) ) {
			return $this->successResponse(
				[
					'slots'            => [],
					'duration_minutes' => $duration,
				]
			);
		}

		$existingBookings = $this->sessionBookingRepository->findWithFilter(
			new SessionBookingFilter(
				coachId:          $program->getCoachId(),
				fromDate:         $date,
				toDate:           $date,
				excludeCancelled: true,
			),
			200,
			0
		);

		$slots = [];
		foreach ( $windows as $window ) {
			$current = strtotime( $window['start'] );
			$end     = strtotime( $window['end'] );

			while ( $current + ( $duration * 60 ) <= $end ) {
				$slotStart = gmdate( 'H:i', $current );
				$slotEnd   = gmdate( 'H:i', $current + ( $duration * 60 ) );

				$booked = false;
				foreach ( $existingBookings as $booking ) {
					if ( $booking->getStartTime() < $slotEnd && $booking->getEndTime() > $slotStart ) {
						$booked = true;
						break;
					}
				}

				$slots[] = [
					'start_time' => $slotStart,
					'end_time'   => $slotEnd,
					'available'  => ! $booked,
				];

				$current += $duration * 60;
			}
		}

		return $this->successResponse(
			[
				'slots'            => $slots,
				'duration_minutes' => $duration,
			]
		);
	}

	public function bookSession( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$enrollmentId = (int) $request->get_param( 'enrollment_id' );
		$enrollment   = $this->enrollmentRepository->findById( $enrollmentId );

		if ( ! $enrollment || $enrollment->getUserId() !== get_current_user_id() ) {
			return $this->notFoundResponse( __( 'Enrollment', 'allcoach' ) );
		}

		try {
			$dto = SessionBookingDTO::fromArray( $request->get_params() );
			$sb  = $this->bookService->execute( $dto );

			return $this->successResponse( $this->prepareBooking( $sb ), 201 );
		} catch ( \Throwable $e ) {
			return $this->exceptionToResponse( $e );
		}
	}

	public function cancelSession( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$bookingId = (int) $request->get_param( 'booking_id' );
		$booking   = $this->sessionBookingRepository->findById( $bookingId );

		if ( ! $booking || $booking->getClientId() !== get_current_user_id() ) {
			return $this->notFoundResponse( __( 'Session booking', 'allcoach' ) );
		}

		try {
			$sb = $this->cancelService->execute( $bookingId );

			return $this->successResponse( $this->prepareBooking( $sb ) );
		} catch ( \Throwable $e ) {
			return $this->exceptionToResponse( $e );
		}
	}

	private function permClient(): bool {
		return is_user_logged_in() && ( $this->isClient() || $this->isCoach() || $this->isAdmin() );
	}

	private function prepareBooking( SessionBooking $sb ): array {
		return [
			'id'             => $sb->getId(),
			'enrollment_id'  => $sb->getEnrollmentId(),
			'scheduled_date' => $sb->getScheduledDate(),
			'start_time'     => $sb->getStartTime(),
			'end_time'       => $sb->getEndTime(),
			'session_number' => $sb->getSessionNumber(),
			'status'         => $sb->getStatus()->getValue(),
			'timezone'       => $sb->getTimezone(),
			'meeting_url'    => $sb->getMeetingUrl(),
		];
	}
}
