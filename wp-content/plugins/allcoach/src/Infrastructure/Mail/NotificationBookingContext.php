<?php

declare(strict_types=1);

namespace AllCoach\Infrastructure\Mail;

defined( 'ABSPATH' ) || exit;

final class NotificationBookingContext {

	public function __construct(
		public readonly int $id,
		public readonly int $clientId,
		public readonly int $programId,
		public readonly string $paymentMethod,
		public readonly float $amount,
		public readonly string $currency,
		public readonly string $status,
		public readonly int $orderId = 0,
	) {}

	public function getId(): int {
		return $this->id;
	}

	public function getClientId(): int {
		return $this->clientId;
	}

	public function getProgramId(): int {
		return $this->programId;
	}

	public function getPaymentMethod(): string {
		return $this->paymentMethod;
	}

	public function getAmount(): float {
		return $this->amount;
	}

	public function getCurrency(): string {
		return $this->currency;
	}

	public function getStatus(): string {
		return $this->status;
	}

	public function getOrderId(): int {
		return $this->orderId;
	}
}
