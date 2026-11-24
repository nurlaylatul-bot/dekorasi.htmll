<?php

declare(strict_types=1);

namespace AllCoach\Domain\Activity\Events;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\Events\Event;
use AllCoach\Domain\Activity\ActivityCompletion;

class ActivityCompleted extends Event {

	public function __construct(
		public readonly ActivityCompletion $completion,
	) {}
}
