<?php

declare(strict_types=1);

namespace AllCoach\Domain\Order\Events;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\Events\Event;
use AllCoach\Domain\Order\Order;

class OrderPaid extends Event {

	public function __construct(
		public readonly int $orderId,
		public readonly Order $order,
	) {}
}
