<?php

declare(strict_types=1);

namespace AllCoach\Infrastructure\Mail;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\Jobs\Contracts\JobDispatcher;
use AllCoach\Domain\Booking\SessionBooking;
use AllCoach\Domain\Client\Client;
use AllCoach\Domain\Enrollment\Enrollment;
use AllCoach\Domain\Order\Order;
use AllCoach\Core\Contracts\ServiceProviderInterface;
use AllCoach\Traits\Hooks;
use Carbon\CarbonImmutable;

class NotificationServiceProvider implements ServiceProviderInterface {

	use Hooks;

	public function __construct(
		private readonly JobDispatcher $dispatcher,
	) {}

	public function boot(): void {
		$this->addAction( 'allcoach:order:created', [ $this, 'onOrderCreated' ], 10, 2 );
		$this->addAction( 'allcoach:order:paid', [ $this, 'onOrderPaid' ], 10, 2 );
		$this->addAction( 'allcoach:order:cancelled', [ $this, 'onOrderCancelled' ] );

		$this->addAction( 'allcoach:session:booked', [ $this, 'onSessionBooked' ] );
		$this->addAction( 'allcoach:session:cancelled', [ $this, 'onSessionCancelled' ] );
		$this->addAction( 'allcoach:session:completed', [ $this, 'onSessionCompleted' ] );

		$this->addAction( 'allcoach:client:created', [ $this, 'onClientCreated' ] );
	}


	public function onOrderCreated( Order $order, Enrollment $enrollment ): void {
		$enrollmentId = $enrollment->getId();

		$this->dispatcher->dispatch(
			SendNotificationJob::class,
			new SendNotificationJobPayload(
				notificationType: 'new_booking_confirmation',
				enrollmentId: $enrollmentId,
			)
		);

		$this->dispatcher->dispatch(
			SendNotificationJob::class,
			new SendNotificationJobPayload(
				notificationType: 'new_booking_alert',
				enrollmentId: $enrollmentId,
			)
		);
	}

	public function onOrderPaid( int $orderId, Order $order ): void {
		$this->dispatcher->dispatch(
			SendNotificationJob::class,
			new SendNotificationJobPayload(
				notificationType: 'order_confirmed',
				orderId: $orderId,
			)
		);
	}

	public function onOrderCancelled( int $enrollmentId ): void {
		$this->dispatcher->dispatch(
			SendNotificationJob::class,
			new SendNotificationJobPayload(
				notificationType: 'order_cancelled',
				enrollmentId: $enrollmentId,
			)
		);
	}

	public function onSessionCancelled( SessionBooking $session ): void {
		$enrollmentId     = $session->getEnrollmentId();
		$sessionBookingId = (int) $session->getId();

		$this->dispatcher->cancel(
			SendNotificationJob::class,
			new SendNotificationJobPayload(
				notificationType: '24h_reminder',
				sessionBookingId: $sessionBookingId,
			)
		);

		$this->dispatcher->dispatch(
			SendNotificationJob::class,
			new SendNotificationJobPayload(
				notificationType: 'cancellation_notice',
				enrollmentId: $enrollmentId,
				sessionBookingId: $sessionBookingId,
			)
		);

		$this->dispatcher->dispatch(
			SendNotificationJob::class,
			new SendNotificationJobPayload(
				notificationType: 'session_cancelled_alert',
				sessionBookingId: $sessionBookingId,
			)
		);
	}

	public function onSessionBooked( SessionBooking $session ): void {
		$sessionBookingId = (int) $session->getId();

		$this->dispatcher->dispatch(
			SendNotificationJob::class,
			new SendNotificationJobPayload(
				notificationType: 'session_booking_confirmation',
				sessionBookingId: $sessionBookingId,
			)
		);

		$tz           = wp_timezone();
		$sessionStart = CarbonImmutable::parse(
			$session->getScheduledDate() . ' ' . $session->getStartTime(),
			$tz
		);
		$reminderAt   = $sessionStart->getTimestamp() - DAY_IN_SECONDS;

		$payload = new SendNotificationJobPayload(
			notificationType: '24h_reminder',
			sessionBookingId: $sessionBookingId,
		);

		if ( $reminderAt <= time() ) {
			$this->dispatcher->dispatch( SendNotificationJob::class, $payload );
		} else {
			$this->dispatcher->scheduleAt( SendNotificationJob::class, $payload, $reminderAt );
		}
	}

	public function onSessionCompleted( SessionBooking $session ): void {
		$this->dispatcher->dispatch(
			SendNotificationJob::class,
			new SendNotificationJobPayload(
				notificationType: 'session_completed_follow_up',
				sessionBookingId: (int) $session->getId(),
			)
		);
	}

	public function onClientCreated( Client $client ): void {
		$clientId = (int) $client->getId();
		if ( $clientId <= 0 ) {
			return;
		}

		$this->dispatcher->dispatch(
			SendNotificationJob::class,
			new SendNotificationJobPayload(
				notificationType: 'welcome_client',
				clientId: $clientId,
			)
		);
	}
}
