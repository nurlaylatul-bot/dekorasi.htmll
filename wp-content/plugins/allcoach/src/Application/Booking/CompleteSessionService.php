<?php

declare(strict_types=1);

namespace AllCoach\Application\Booking;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\Events\EventDispatcher;
use AllCoach\Core\Exceptions\NotFoundException;
use AllCoach\Core\Exceptions\ValidationException;
use AllCoach\Domain\Booking\Events\BookingCompleted;
use AllCoach\Domain\Booking\Events\SessionCompleted;
use AllCoach\Domain\Booking\SessionBooking;
use AllCoach\Domain\Booking\SessionBookingFilter;
use AllCoach\Domain\Booking\SessionBookingRepository;
use AllCoach\Domain\Enrollment\EnrollmentRepository;
use AllCoach\Domain\Order\OrderItemRepository;
use AllCoach\Domain\Order\OrderRepository;
use AllCoach\Domain\Session\SessionRepository;
use AllCoach\Support\Logger;

class CompleteSessionService {

	public function __construct(
		private readonly SessionBookingRepository $sessionBookingRepository,
		private readonly EnrollmentRepository $enrollmentRepository,
		private readonly OrderRepository $orderRepository,
		private readonly OrderItemRepository $orderItemRepository,
		private readonly SessionRepository $sessionRepository,
		private readonly EventDispatcher $events,
		private readonly Logger $logger,
	) {}

	public function execute( int $sessionBookingId ): SessionBooking {
		$sb = $this->sessionBookingRepository->findById( $sessionBookingId );
		if ( ! $sb ) {
			throw NotFoundException::entity( esc_html__( 'Session booking', 'allcoach' ), absint( $sessionBookingId ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		if ( ! $sb->getStatus()->isScheduled() ) {
			throw ValidationException::withErrors(
				[
					'status' => esc_html__( 'Only scheduled sessions can be marked as completed.', 'allcoach' ),
				]
			);
		}

		$sb->complete();
		$sb = $this->sessionBookingRepository->save( $sb );

		$this->logger->info(
			'Session completed',
			[
				'session_booking_id' => $sessionBookingId,
				'enrollment_id'      => $sb->getEnrollmentId(),
			]
		);

		$this->events->dispatch( new SessionCompleted( $sb ) );

		$this->completeBookingIfAllDone( $sb );

		return $sb;
	}

	private function completeBookingIfAllDone( SessionBooking $sb ): void {
		$session = $this->sessionRepository->findOneByProgramId( $sb->getProgramId() );
		if ( ! $session ) {
			return;
		}

		$totalRequired  = $session->getRepetitionCount();
		$enrollmentId   = $sb->getEnrollmentId();
		$completedCount = $this->sessionBookingRepository->countWithFilter(
			new SessionBookingFilter( enrollmentId: $enrollmentId, status: 'completed' )
		);

		if ( $completedCount < $totalRequired ) {
			return;
		}

		$enrollment = $this->enrollmentRepository->findById( $enrollmentId );
		if ( ! $enrollment || ! $enrollment->isActive() ) {
			return;
		}

		$orderItem = $this->orderItemRepository->findById( $enrollment->getOrderItemId() );
		if ( ! $orderItem ) {
			return;
		}
		$order = $this->orderRepository->findById( $orderItem->getOrderId() );
		if ( ! $order || ! $order->getStatus()->isPaid() ) {
			return;
		}

		$enrollment->complete();
		$this->enrollmentRepository->save( $enrollment );

		$this->logger->info(
			'Enrollment auto-completed (all sessions done)',
			[
				'enrollment_id'  => $enrollmentId,
				'sessions_total' => $totalRequired,
			]
		);

		$this->events->dispatch( new BookingCompleted( $enrollment ) );
	}
}
