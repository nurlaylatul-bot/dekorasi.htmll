<?php

declare(strict_types=1);

namespace AllCoach\Domain\Order\Events;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\Events\Event;

class OrderCancelled extends Event {

	public function __construct(
		public readonly int $enrollmentId,
	) {}
}
