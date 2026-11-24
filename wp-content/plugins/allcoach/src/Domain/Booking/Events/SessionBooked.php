<?php

declare(strict_types=1);

namespace AllCoach\Domain\Booking\Events;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\Events\Event;
use AllCoach\Domain\Booking\SessionBooking;

class SessionBooked extends Event {

	public function __construct(
		public readonly SessionBooking $sessionBooking,
	) {}
}
