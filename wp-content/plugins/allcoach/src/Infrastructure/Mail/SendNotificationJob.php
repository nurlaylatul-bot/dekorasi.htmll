<?php

declare(strict_types=1);

namespace AllCoach\Infrastructure\Mail;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\Jobs\AbstractJob;
use AllCoach\Core\Jobs\AbstractJobPayload;
use AllCoach\Domain\Booking\SessionBookingRepository;
use AllCoach\Domain\Client\ClientRepository;
use AllCoach\Domain\Enrollment\EnrollmentRepository;
use AllCoach\Domain\Order\OrderItemRepository;
use AllCoach\Domain\Order\OrderRepository;
use AllCoach\Domain\Program\ProgramRepository;
use AllCoach\Infrastructure\Mail\Notifications\CancellationNoticeNotification;
use AllCoach\Infrastructure\Mail\Notifications\NewBookingAdminNotification;
use AllCoach\Infrastructure\Mail\Notifications\NewBookingClientNotification;
use AllCoach\Infrastructure\Mail\Notifications\OrderCancelledNotification;
use AllCoach\Infrastructure\Mail\Notifications\OrderConfirmedNotification;
use AllCoach\Infrastructure\Mail\Notifications\SessionBookingConfirmationNotification;
use AllCoach\Infrastructure\Mail\Notifications\SessionCancelledAdminNotification;
use AllCoach\Infrastructure\Mail\Notifications\SessionCompletedFollowUpNotification;
use AllCoach\Infrastructure\Mail\Notifications\SessionReminder24hNotification;
use AllCoach\Infrastructure\Mail\Notifications\WelcomeClientNotification;
use AllCoach\Support\Logger;

class SendNotificationJob extends AbstractJob {

	private const VALID_TYPES = [
		'new_booking_confirmation',
		'new_booking_alert',
		'cancellation_notice',
		'24h_reminder',
		'order_confirmed',
		'order_cancelled',
		'welcome_client',
		'session_booking_confirmation',
		'session_completed_follow_up',
		'session_cancelled_alert',
	];

	public function __construct(
		private readonly EnrollmentRepository $enrollmentRepository,
		private readonly OrderRepository $orderRepository,
		private readonly OrderItemRepository $orderItemRepository,
		private readonly SessionBookingRepository $sessionBookingRepository,
		private readonly ClientRepository $clientRepository,
		private readonly ProgramRepository $programRepository,
		private readonly NewBookingClientNotification $newBookingClient,
		private readonly NewBookingAdminNotification $newBookingAdmin,
		private readonly CancellationNoticeNotification $cancellationNotice,
		private readonly SessionReminder24hNotification $sessionReminder24h,
		private readonly OrderConfirmedNotification $orderConfirmed,
		private readonly OrderCancelledNotification $orderCancelled,
		private readonly WelcomeClientNotification $welcomeClient,
		private readonly SessionBookingConfirmationNotification $sessionBookingConfirmation,
		private readonly SessionCompletedFollowUpNotification $sessionCompletedFollowUp,
		private readonly SessionCancelledAdminNotification $sessionCancelledAdmin,
		private readonly Logger $logger,
	) {}

	public function handle( AbstractJobPayload $payload ): void {
		assert( $payload instanceof SendNotificationJobPayload );

		$type = $payload->notificationType;

		if ( ! in_array( $type, self::VALID_TYPES, true ) ) {
			$this->logger->error( 'Unknown notification type', [ 'type' => $type ] );
			return;
		}

		if ( $type === 'welcome_client' ) {
			$this->handleWelcomeClient( $payload->clientId );
			return;
		}

		if ( in_array( $type, [ 'session_booking_confirmation', 'session_completed_follow_up', 'session_cancelled_alert' ], true ) ) {
			$this->handleSessionNotification( $type, $payload->sessionBookingId );
			return;
		}

		if ( $type === 'order_confirmed' && $payload->orderId > 0 ) {
			$this->handleOrderNotification( $type, $payload->orderId );
			return;
		}

		if ( $type === 'order_cancelled' && $payload->enrollmentId > 0 ) {
			$this->handleEnrollmentNotification( $type, $payload->enrollmentId );
			return;
		}

		$this->handleEnrollmentNotification( $type, $payload->enrollmentId, $payload->sessionBookingId );
	}

	public static function payloadFromArray( array $data ): SendNotificationJobPayload {
		return SendNotificationJobPayload::fromArray( $data );
	}

	private function handleWelcomeClient( int $clientId ): void {
		$client = $this->clientRepository->findById( $clientId );
		if ( ! $client ) {
			$this->logger->error( 'Client not found for welcome notification', [ 'client_id' => $clientId ] );
			return;
		}

		$this->welcomeClient->send( $client );
		$this->logger->info( 'Welcome notification sent', [ 'client_id' => $clientId ] );
	}

	private function handleOrderNotification( string $type, int $orderId ): void {
		$order = $this->orderRepository->findById( $orderId );
		if ( ! $order ) {
			$this->logger->error( 'Order not found for notification', [ 'order_id' => $orderId ] );
			return;
		}

		$client = $this->clientRepository->findById( $order->getUserId() );
		if ( ! $client ) {
			$this->logger->error( 'Client not found for notification', [ 'user_id' => $order->getUserId() ] );
			return;
		}

		$items      = $this->orderItemRepository->findByOrderId( $orderId );
		$firstItem  = $items[0] ?? null;
		$enrollment = $firstItem ? $this->enrollmentRepository->findByOrderItemId( $firstItem->getId() ) : null;

		$programId = $firstItem ? $firstItem->getProgramId() : 0;
		$program   = $programId > 0 ? $this->programRepository->findById( $programId ) : null;
		if ( ! $program ) {
			$this->logger->error( 'Program not found for notification', [ 'order_id' => $orderId ] );
			return;
		}

		$context = new NotificationBookingContext(
			id: $enrollment ? $enrollment->getId() : 0,
			clientId: $order->getUserId(),
			programId: $programId,
			paymentMethod: $order->getPaymentMethod(),
			amount: $order->getTotal(),
			currency: $order->getCurrency(),
			status: $order->getStatus()->getValue(),
			orderId: $orderId,
		);

		$this->orderConfirmed->send( $context, $client, $program );
		$this->logger->info( 'Order confirmed notification sent', [ 'order_id' => $orderId ] );
	}

	private function handleEnrollmentNotification( string $type, int $enrollmentId, int $sessionBookingId = 0 ): void {
		$session = null;

		if ( $type === '24h_reminder' ) {
			$session = $this->sessionBookingRepository->findById( $sessionBookingId );
			if ( ! $session ) {
				$this->logger->error( 'Session booking not found for notification', [ 'session_booking_id' => $sessionBookingId ] );
				return;
			}
			if ( $session->getStatus()->isCancelled() ) {
				$this->logger->error( 'Skipping 24h reminder for cancelled session', [ 'session_booking_id' => $sessionBookingId ] );
				return;
			}
			$enrollmentId = $session->getEnrollmentId();
		}

		$enrollment = $this->enrollmentRepository->findById( $enrollmentId );
		if ( ! $enrollment ) {
			$this->logger->error( 'Enrollment not found for notification', [ 'enrollment_id' => $enrollmentId ] );
			return;
		}

		$orderItem = $this->orderItemRepository->findById( $enrollment->getOrderItemId() );
		$order     = $orderItem ? $this->orderRepository->findById( $orderItem->getOrderId() ) : null;
		if ( ! $order ) {
			$this->logger->error( 'Order not found for notification', [ 'enrollment_id' => $enrollmentId ] );
			return;
		}

		$bookingContext = new NotificationBookingContext(
			id: $enrollment->getId(),
			clientId: $enrollment->getUserId(),
			programId: $enrollment->getProgramId(),
			paymentMethod: $order->getPaymentMethod(),
			amount: $order->getTotal(),
			currency: $order->getCurrency(),
			status: $order->getStatus()->getValue(),
			orderId: $orderItem ? $orderItem->getOrderId() : 0,
		);

		$client = $this->clientRepository->findById( $bookingContext->getClientId() );
		if ( ! $client ) {
			$this->logger->error( 'Client not found for notification', [ 'client_id' => $bookingContext->getClientId() ] );
			return;
		}

		$program = $this->programRepository->findById( $bookingContext->getProgramId() );
		if ( ! $program ) {
			$this->logger->error( 'Program not found for notification', [ 'program_id' => $bookingContext->getProgramId() ] );
			return;
		}

		if ( $sessionBookingId > 0 && ! $session ) {
			$session = $this->sessionBookingRepository->findById( $sessionBookingId );
		}

		match ( $type ) {
			'new_booking_confirmation' => $this->newBookingClient->send( $bookingContext, $client, $program ),
			'new_booking_alert'        => $this->newBookingAdmin->send( $bookingContext, $client, $program ),
			'cancellation_notice'      => $this->cancellationNotice->send( $bookingContext, $client, $program, $session ),
			'24h_reminder'             => $session ? $this->sessionReminder24h->send( $session, $client, $program ) : null,
			'order_cancelled'          => $this->orderCancelled->send( $bookingContext, $client, $program ),
			default                    => null,
		};

		$this->logger->info(
			'Notification sent',
			[
				'type'          => $type,
				'enrollment_id' => $enrollmentId,
			]
		);
	}

	private function handleSessionNotification( string $type, int $sessionBookingId ): void {
		$session = $this->sessionBookingRepository->findById( $sessionBookingId );
		if ( ! $session ) {
			$this->logger->error( 'Session booking not found for notification', [ 'session_booking_id' => $sessionBookingId ] );
			return;
		}

		$client = $this->clientRepository->findById( $session->getClientId() );
		if ( ! $client ) {
			$this->logger->error( 'Client not found for notification', [ 'client_id' => $session->getClientId() ] );
			return;
		}

		$program = $this->programRepository->findById( $session->getProgramId() );
		if ( ! $program ) {
			$this->logger->error( 'Program not found for notification', [ 'program_id' => $session->getProgramId() ] );
			return;
		}

		match ( $type ) {
			'session_booking_confirmation' => $this->sessionBookingConfirmation->send( $session, $client, $program ),
			'session_completed_follow_up'  => $this->sessionCompletedFollowUp->send( $session, $client, $program ),
			'session_cancelled_alert'      => $this->sessionCancelledAdmin->send( $session, $client, $program ),
			default                        => null,
		};

		$this->logger->info(
			'Notification sent',
			[
				'type'               => $type,
				'session_booking_id' => $sessionBookingId,
			]
		);
	}
}
