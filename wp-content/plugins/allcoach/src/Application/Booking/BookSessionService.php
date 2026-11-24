<?php

declare(strict_types=1);

namespace AllCoach\Application\Booking;

defined( 'ABSPATH' ) || exit;

use AllCoach\Application\Availability\AvailabilityQueryService;
use AllCoach\Application\Booking\Pipes\BookingContext;
use AllCoach\Application\Booking\Pipes\ValidateCoachAvailability;
use AllCoach\Application\Booking\Pipes\ValidateNoClientOverlap;
use AllCoach\Application\Booking\Pipes\ValidateNoCoachOverlap;
use AllCoach\Application\Booking\Pipes\ValidateSessionDuration;
use AllCoach\Application\Booking\Pipes\ValidateTimeSlot;
use AllCoach\Core\Events\EventDispatcher;
use AllCoach\Core\Exceptions\NotFoundException;
use AllCoach\Core\Exceptions\ValidationException;
use AllCoach\Core\Pipeline\Pipeline;
use AllCoach\Domain\Booking\Events\SessionBooked;
use AllCoach\Domain\Booking\SessionBooking;
use AllCoach\Domain\Booking\SessionBookingFilter;
use AllCoach\Domain\Booking\SessionBookingRepository;
use AllCoach\Domain\Client\ClientRepository;
use AllCoach\Domain\Enrollment\EnrollmentRepository;
use AllCoach\Domain\Program\ProgramRepository;
use AllCoach\Domain\Session\SessionRepository;
use AllCoach\Support\Logger;
use Carbon\CarbonImmutable;

class BookSessionService {

	public function __construct(
		private readonly EnrollmentRepository $enrollmentRepository,
		private readonly SessionBookingRepository $sessionBookingRepository,
		private readonly SessionRepository $sessionRepository,
		private readonly ProgramRepository $programRepository,
		private readonly AvailabilityQueryService $availabilityQueryService,
		private readonly ClientRepository $clientRepository,
		private readonly EventDispatcher $events,
		private readonly Logger $logger,
	) {}

	public function execute( SessionBookingDTO $dto ): SessionBooking {
		$ctx = $this->buildContext( $dto );

		Pipeline::make()
			->send( $ctx )
			->through( $this->validationPipes() )
			->thenReturn();

		$meetingType = $ctx->program->getMeetingType();

		$sessionBooking = new SessionBooking(
			enrollmentId: $ctx->dto->enrollmentId,
			clientId: $ctx->enrollment->getUserId(),
			coachId: $ctx->coachId,
			programId: $ctx->enrollment->getProgramId(),
			scheduledDate: $ctx->convertedDto->scheduledDate,
			startTime: $ctx->convertedDto->startTime,
			endTime: $ctx->convertedDto->endTime,
			sessionNumber: $ctx->scheduledCount + 1,
			notes: $dto->notes,
			timezone: $ctx->coachTimezone,
			meetingType: $meetingType,
			location: $meetingType->isFaceToFace() ? $ctx->program->getMeetingLocation() : null,
		);

		if ( $meetingType->isCustomUrl() ) {
			$sessionBooking->setMeetingUrl( $ctx->program->getCustomMeetingUrl() );
		}

		$sessionBooking = $this->sessionBookingRepository->save( $sessionBooking );

		$this->logger->info(
			'Session booked',
			[
				'session_booking_id' => $sessionBooking->getId(),
				'enrollment_id'      => $dto->enrollmentId,
				'coach_id'           => $ctx->coachId,
				'date'               => $ctx->convertedDto->scheduledDate,
				'time'               => $ctx->convertedDto->startTime . '-' . $ctx->convertedDto->endTime,
				'timezone'           => $ctx->coachTimezone,
			]
		);

		$this->events->dispatch( new SessionBooked( $sessionBooking ) );

		return $sessionBooking;
	}

	private function buildContext( SessionBookingDTO $dto ): BookingContext {
		$enrollment = $this->enrollmentRepository->findById( $dto->enrollmentId );
		if ( ! $enrollment ) {
			throw NotFoundException::entity( esc_html__( 'Booking', 'allcoach' ), absint( $dto->enrollmentId ) );
		}

		if ( ! $enrollment->isActive() ) {
			throw ValidationException::withErrors(
				[ 'booking' => esc_html__( 'This booking is not active.', 'allcoach' ) ]
			);
		}

		if ( ! $this->enrollmentRepository->isOrderPaid( $dto->enrollmentId ) ) {
			throw ValidationException::withErrors(
				[ 'booking' => esc_html__( 'This booking is not active.', 'allcoach' ) ]
			);
		}

		$program = $this->programRepository->findById( $enrollment->getProgramId() );
		if ( ! $program ) {
			throw NotFoundException::entity( esc_html__( 'Program', 'allcoach' ), absint( $enrollment->getProgramId() ) );
		}

		$session = $this->sessionRepository->findOneByProgramId( $enrollment->getProgramId() );
		if ( ! $session ) {
			throw ValidationException::withErrors(
				[ 'session' => esc_html__( 'This program does not have session configuration.', 'allcoach' ) ]
			);
		}

		$scheduledCount = $this->sessionBookingRepository->countWithFilter(
			new SessionBookingFilter( enrollmentId: $dto->enrollmentId, excludeCancelled: true ),
		);
		if ( $scheduledCount >= $session->getRepetitionCount() ) {
			throw ValidationException::withErrors(
				[ 'session' => esc_html__( 'All sessions for this booking have already been scheduled.', 'allcoach' ) ]
			);
		}

		$coachId = $program->getCoachId();
		if ( ! $coachId ) {
			throw ValidationException::withErrors(
				[ 'coach' => esc_html__( 'This program does not have an assigned coach.', 'allcoach' ) ]
			);
		}

		$coachTimezone = $this->resolveCoachTimezone( $coachId );
		$convertedDto  = $this->convertToCoachTimezone( $dto, $coachTimezone );

		return new BookingContext(
			dto: $dto,
			enrollment: $enrollment,
			program: $program,
			session: $session,
			coachId: $coachId,
			coachTimezone: $coachTimezone,
			convertedDto: $convertedDto,
			scheduledCount: $scheduledCount,
		);
	}

	/**
	 * @return array<object>
	 */
	private function validationPipes(): array {
		return [
			new ValidateTimeSlot(),
			new ValidateSessionDuration(),
			new ValidateCoachAvailability( $this->availabilityQueryService ),
			new ValidateNoCoachOverlap( $this->sessionBookingRepository ),
			new ValidateNoClientOverlap( $this->sessionBookingRepository ),
		];
	}

	private function resolveCoachTimezone( int $coachId ): string {
		$availabilities = $this->availabilityQueryService->findByUserOrDefault( $coachId );
		$availability   = current( $availabilities );
		if ( $availability && $availability->getTimezone() ) {
			return $availability->getTimezone();
		}
		return wp_timezone_string() ?: 'UTC';
	}

	private function convertToCoachTimezone( SessionBookingDTO $dto, string $coachTimezone ): SessionBookingDTO {
		$clientTimezone = $this->resolveClientTimezone( $dto );

		if ( empty( $clientTimezone ) || $clientTimezone === $coachTimezone ) {
			return $dto;
		}

		try {
			$clientTz = new \DateTimeZone( $clientTimezone );
			$coachTz  = new \DateTimeZone( $coachTimezone );
		} catch ( \Exception ) {
			return $dto;
		}

		$startDateTime = CarbonImmutable::parse(
			$dto->scheduledDate . ' ' . $dto->startTime,
			$clientTz
		);
		$endDateTime   = CarbonImmutable::parse(
			$dto->scheduledDate . ' ' . $dto->endTime,
			$clientTz
		);

		if ( ! $startDateTime || ! $endDateTime ) {
			return $dto;
		}

		$startInCoachTz = $startDateTime->setTimezone( $coachTz );
		$endInCoachTz   = $endDateTime->setTimezone( $coachTz );

		return new SessionBookingDTO(
			enrollmentId: $dto->enrollmentId,
			scheduledDate: $startInCoachTz->format( 'Y-m-d' ),
			startTime: $startInCoachTz->format( 'H:i' ),
			endTime: $endInCoachTz->format( 'H:i' ),
			notes: $dto->notes,
			clientTimezone: $dto->clientTimezone,
		);
	}

	private function resolveClientTimezone( SessionBookingDTO $dto ): string {
		if ( ! empty( $dto->clientTimezone ) ) {
			return $dto->clientTimezone;
		}

		$userId = get_current_user_id();
		if ( $userId > 0 ) {
			$client = $this->clientRepository->findById( $userId );
			if ( $client && ! empty( $client->getTimezone() ) ) {
				return $client->getTimezone();
			}
		}

		return '';
	}
}
