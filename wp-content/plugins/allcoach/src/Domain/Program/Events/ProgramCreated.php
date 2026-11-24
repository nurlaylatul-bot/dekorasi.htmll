<?php

declare(strict_types=1);

namespace AllCoach\Domain\Program\Events;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\Events\Event;
use AllCoach\Domain\Program\Program;

class ProgramCreated extends Event {

	public function __construct(
		public readonly Program $program
	) {}
}
