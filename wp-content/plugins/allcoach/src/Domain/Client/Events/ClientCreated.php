<?php

declare(strict_types=1);

namespace AllCoach\Domain\Client\Events;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\Events\Event;
use AllCoach\Domain\Client\Client;

class ClientCreated extends Event {

	public function __construct(
		public readonly Client $client
	) {}
}
