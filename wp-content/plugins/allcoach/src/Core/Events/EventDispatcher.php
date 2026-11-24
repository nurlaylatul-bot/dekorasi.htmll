<?php

declare(strict_types=1);

namespace AllCoach\Core\Events;

defined( 'ABSPATH' ) || exit;

use AllCoach\Traits\Hooks;

class EventDispatcher {

	use Hooks;

	/** @var array<string, array<int, callable[]>> */
	private array $listeners = [];

	public function dispatch( Event $event ): Event {
		$eventName = $event::class;

		if ( isset( $this->listeners[ $eventName ] ) ) {
			foreach ( $this->listeners[ $eventName ] as $listenersAtPriority ) {
				foreach ( $listenersAtPriority as $listener ) {
					$listener( $event );

					if ( $event->isPropagationStopped() ) {
						break 2;
					}
				}
			}
		}

		$this->doAction( "allcoach:event:{$eventName}", $event );

		return $event;
	}

	public function listen( string $eventClass, callable $listener, int $priority = 10 ): void {
		if ( ! isset( $this->listeners[ $eventClass ] ) ) {
			$this->listeners[ $eventClass ] = [];
		}

		$this->listeners[ $eventClass ][ $priority ][] = $listener;
		ksort( $this->listeners[ $eventClass ] );
	}

	public function subscribe( EventSubscriber $subscriber ): void {
		foreach ( $subscriber->getSubscribedEvents() as $eventClass => $params ) {
			if ( is_string( $params ) ) {
				$this->listen( $eventClass, [ $subscriber, $params ] );
			} elseif ( is_array( $params ) ) {
				$this->listen(
					$eventClass,
					[ $subscriber, $params[0] ],
					$params[1] ?? 10
				);
			}
		}
	}
}
