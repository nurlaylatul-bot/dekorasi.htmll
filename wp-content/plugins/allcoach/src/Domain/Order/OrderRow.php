<?php

declare(strict_types=1);

namespace AllCoach\Domain\Order;

defined( 'ABSPATH' ) || exit;

/**
 * Denormalised read model returned by the JOIN query in OrderRepository.
 * Holds all fields needed to construct an OrderView without extra lookups.
 */
final class OrderRow {

	public function __construct(
		public readonly int $orderId,
		public readonly string $orderStatus,
		public readonly string $paymentMethod,
		public readonly string $currency,
		public readonly float $total,
		public readonly ?string $paymentReference,
		public readonly string $createdAt,
		public readonly ?string $updatedAt,
		public readonly int $enrollmentId,
		public readonly int $clientId,
		public readonly int $programId,
		public readonly string $clientName,
		public readonly string $programTitle,
		public readonly ?string $notes = null,
	) {}
}
