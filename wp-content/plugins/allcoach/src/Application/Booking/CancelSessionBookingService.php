<?php

declare(strict_types=1);

namespace AllCoach\Application\Booking;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\Events\EventDispatcher;
use AllCoach\Core\Exceptions\NotFoundException;
use AllCoach\Core\Exceptions\ValidationException;
use AllCoach\Domain\Booking\Events\SessionCancelled;
use AllCoach\Domain\Booking\SessionBooking;
use AllCoach\Domain\Booking\SessionBookingRepository;
use AllCoach\Support\Logger;

class CancelSessionBookingService {

	public function __construct(
		private readonly SessionBookingRepository $sessionBookingRepository,
		private readonly EventDispatcher $events,
		private readonly Logger $logger,
	) {}

	public function execute( int $sessionBookingId ): SessionBooking {
		$sb = $this->sessionBookingRepository->findById( $sessionBookingId );
		if ( ! $sb ) {
			throw NotFoundException::entity( esc_html__( 'Session booking', 'allcoach' ), absint( $sessionBookingId ) );
		}

		if ( $sb->getStatus()->isCompleted() ) {
			throw ValidationException::withErrors(
				[
					'status' => esc_html__( 'Cannot cancel a completed session.', 'allcoach' ),
				]
			);
		}

		if ( $sb->getStatus()->isCancelled() ) {
			throw ValidationException::withErrors(
				[
					'status' => esc_html__( 'This session is already cancelled.', 'allcoach' ),
				]
			);
		}

		$sb->cancel();
		$sb = $this->sessionBookingRepository->save( $sb );

		$this->logger->info(
			'Session booking cancelled',
			[
				'session_booking_id' => $sessionBookingId,
				'enrollment_id'      => $sb->getEnrollmentId(),
			]
		);

		$this->events->dispatch( new SessionCancelled( $sb ) );

		return $sb;
	}
}
