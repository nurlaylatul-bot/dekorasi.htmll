<?php

declare(strict_types=1);

namespace AllCoach\Core\Events;

defined( 'ABSPATH' ) || exit;

use AllCoach\Domain\Activity\Events\ActivityCompleted;
use AllCoach\Domain\Booking\Events\BookingCompleted;
use AllCoach\Domain\Booking\Events\SessionBooked;
use AllCoach\Domain\Booking\Events\SessionCancelled;
use AllCoach\Domain\Booking\Events\SessionCompleted;
use AllCoach\Domain\Client\Events\ClientCreated;
use AllCoach\Domain\Client\Events\ClientDeleted;
use AllCoach\Domain\Client\Events\ClientUpdated;
use AllCoach\Domain\Order\Events\OrderCancelled;
use AllCoach\Domain\Order\Events\OrderCreated;
use AllCoach\Domain\Order\Events\OrderPaid;
use AllCoach\Traits\Hooks;

class EventHookBridge implements EventSubscriber {

	use Hooks;

	public function getSubscribedEvents(): array {
		return [
			OrderCreated::class      => 'onOrderCreated',
			OrderPaid::class         => 'onOrderPaid',
			OrderCancelled::class    => 'onOrderCancelled',
			SessionBooked::class     => 'onSessionBooked',
			SessionCancelled::class  => 'onSessionCancelled',
			SessionCompleted::class  => 'onSessionCompleted',
			BookingCompleted::class  => 'onBookingCompleted',
			ActivityCompleted::class => 'onActivityCompleted',
			ClientCreated::class     => 'onClientCreated',
			ClientUpdated::class     => 'onClientUpdated',
			ClientDeleted::class     => 'onClientDeleted',
		];
	}

	public function onOrderCreated( OrderCreated $event ): void {
		$this->doAction( 'allcoach:order:created', $event->order, $event->enrollment );
	}

	public function onOrderPaid( OrderPaid $event ): void {
		$this->doAction( 'allcoach:order:paid', $event->orderId, $event->order );
	}

	public function onOrderCancelled( OrderCancelled $event ): void {
		$this->doAction( 'allcoach:order:cancelled', $event->enrollmentId );
	}

	public function onSessionBooked( SessionBooked $event ): void {
		$this->doAction( 'allcoach:session:booked', $event->sessionBooking );
	}

	public function onSessionCancelled( SessionCancelled $event ): void {
		$this->doAction( 'allcoach:session:cancelled', $event->sessionBooking );
	}

	public function onSessionCompleted( SessionCompleted $event ): void {
		$this->doAction( 'allcoach:session:completed', $event->sessionBooking );
	}

	public function onBookingCompleted( BookingCompleted $event ): void {
		$this->doAction( 'allcoach:booking:completed', $event->enrollment );
	}

	public function onActivityCompleted( ActivityCompleted $event ): void {
		$this->doAction( 'allcoach:activity:completed', $event->completion );
	}

	public function onClientCreated( ClientCreated $event ): void {
		$this->doAction( 'allcoach:client:created', $event->client );
	}

	public function onClientUpdated( ClientUpdated $event ): void {
		$this->doAction( 'allcoach:client:updated', $event->client );
	}

	public function onClientDeleted( ClientDeleted $event ): void {
		$this->doAction( 'allcoach:client:deleted', $event->client );
	}
}
