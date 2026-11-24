<?php

declare(strict_types=1);

namespace AllCoach\Domain\Client\Events;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\Events\Event;
use AllCoach\Domain\Client\Client;

class ClientDeleted extends Event {

	public function __construct(
		public readonly Client $client,
	) {}
}
