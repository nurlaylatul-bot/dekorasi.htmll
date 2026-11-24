<?php

declare(strict_types=1);

namespace AllCoach\Core\Events;

defined( 'ABSPATH' ) || exit;

interface EventSubscriber {

	/**
	 * @return array<string, string|array> Event class => method name or [method name, priority]
	 */
	public function getSubscribedEvents(): array;
}
