<?php

declare(strict_types=1);

namespace AllCoach\Application\Order;

defined( 'ABSPATH' ) || exit;

class CreateOrderDTO {

	public function __construct(
		public readonly int $clientId,
		public readonly int $programId,
		public readonly string $paymentMethod,
		public readonly float $amount,
		public readonly string $currency = 'USD',
		public readonly ?string $notes = null,
		public readonly string $status = 'pending',
		public readonly bool $consent = true,
	) {}

	public static function fromArray( array $data ): self {
		return new self(
			clientId: (int) ( $data['client_id'] ?? 0 ),
			programId: (int) ( $data['program_id'] ?? 0 ),
			paymentMethod: $data['payment_method'] ?? 'manual',
			amount: (float) ( $data['amount'] ?? 0 ),
			currency: $data['currency'] ?? 'USD',
			notes: $data['notes'] ?? null,
			status: $data['status'] ?? 'pending',
			consent: array_key_exists( 'consent', $data ) ? (bool) $data['consent'] : true,
		);
	}
}
