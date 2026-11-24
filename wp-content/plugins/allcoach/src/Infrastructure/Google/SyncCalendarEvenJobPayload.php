<?php

declare(strict_types=1);

namespace AllCoach\Infrastructure\Google;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\Jobs\AbstractJobPayload;

class SyncCalendarEvenJobPayload extends AbstractJobPayload {

	public function __construct(
		public string $action = '',
		public int $sessionBookingId = 0,
	) {}

	public static function fromArray( array $data ): self {
		return new self(
			$data['action'] ?? '',
			$data['sessionBookingId'] ?? 0,
		);
	}

	public function toArray(): array {
		return [
			'action'           => $this->action,
			'sessionBookingId' => $this->sessionBookingId,
		];
	}
}
