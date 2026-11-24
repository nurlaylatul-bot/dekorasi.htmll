<?php

declare(strict_types=1);

namespace AllCoach\Application\Booking\Pipes;

defined( 'ABSPATH' ) || exit;

use AllCoach\Application\Booking\SessionBookingDTO;
use AllCoach\Domain\Enrollment\Enrollment;
use AllCoach\Domain\Program\Program;
use AllCoach\Domain\Session\Session;

class BookingContext {

	public function __construct(
		public readonly SessionBookingDTO $dto,
		public readonly Enrollment $enrollment,
		public readonly Program $program,
		public readonly Session $session,
		public readonly int $coachId,
		public readonly string $coachTimezone,
		public readonly SessionBookingDTO $convertedDto,
		public readonly int $scheduledCount,
	) {}
}
