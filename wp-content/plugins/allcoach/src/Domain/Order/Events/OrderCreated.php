<?php

declare(strict_types=1);

namespace AllCoach\Domain\Order\Events;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\Events\Event;
use AllCoach\Domain\Enrollment\Enrollment;
use AllCoach\Domain\Order\Order;

class OrderCreated extends Event {

	public function __construct(
		public readonly Order $order,
		public readonly Enrollment $enrollment,
	) {}
}
