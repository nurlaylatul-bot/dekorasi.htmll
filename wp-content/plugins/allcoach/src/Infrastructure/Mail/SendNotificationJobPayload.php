<?php

declare(strict_types=1);

namespace AllCoach\Infrastructure\Mail;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\Jobs\AbstractJobPayload;

class SendNotificationJobPayload extends AbstractJobPayload {

	public function __construct(
		public readonly string $notificationType = '',
		public readonly int $enrollmentId = 0,
		public readonly int $sessionBookingId = 0,
		public readonly int $orderId = 0,
		public readonly int $clientId = 0,
	) {}

	public static function fromArray( array $data ): self {
		return new self(
			$data['notificationType'] ?? '',
			(int) ( $data['enrollmentId'] ?? 0 ),
			(int) ( $data['sessionBookingId'] ?? 0 ),
			(int) ( $data['orderId'] ?? 0 ),
			(int) ( $data['clientId'] ?? 0 ),
		);
	}

	public function toArray(): array {
		return [
			'notificationType' => $this->notificationType,
			'enrollmentId'     => $this->enrollmentId,
			'sessionBookingId' => $this->sessionBookingId,
			'orderId'          => $this->orderId,
			'clientId'         => $this->clientId,
		];
	}
}
