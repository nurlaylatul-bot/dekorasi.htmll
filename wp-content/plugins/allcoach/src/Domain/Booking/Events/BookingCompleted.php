<?php

declare(strict_types=1);

namespace AllCoach\Domain\Booking\Events;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\Events\Event;
use AllCoach\Domain\Enrollment\Enrollment;

class BookingCompleted extends Event {

	public function __construct(
		public readonly Enrollment $enrollment,
	) {}
}
