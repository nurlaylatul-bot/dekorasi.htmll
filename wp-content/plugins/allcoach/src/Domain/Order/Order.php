<?php

declare(strict_types=1);

namespace AllCoach\Domain\Order;

defined( 'ABSPATH' ) || exit;

use AllCoach\Domain\Shared\Entity;
use Carbon\CarbonImmutable;

class Order extends Entity {

	private OrderStatus $status;
	private CarbonImmutable $createdAt;
	private ?CarbonImmutable $updatedAt = null;

	public function __construct(
		private int $userId,
		private string $paymentMethod,
		private string $currency,
		private float $subtotal,
		private float $discount,
		private float $tax,
		private float $total,
		private ?string $paymentReference = null,
		private ?string $notes = null,
		private bool $consent = true,
	) {
		$this->status    = OrderStatus::pending();
		$this->createdAt = CarbonImmutable::now();
	}

	public static function reconstitute(
		int $id,
		int $userId,
		OrderStatus $status,
		string $paymentMethod,
		string $currency,
		float $subtotal,
		float $discount,
		float $tax,
		float $total,
		?string $paymentReference,
		?string $notes,
		CarbonImmutable $createdAt,
		?CarbonImmutable $updatedAt,
		bool $consent = true,
	): self {
		$order            = new self( $userId, $paymentMethod, $currency, $subtotal, $discount, $tax, $total, $paymentReference, $notes, $consent );
		$order->id        = $id;
		$order->status    = $status;
		$order->createdAt = $createdAt;
		$order->updatedAt = $updatedAt;
		return $order;
	}

	public function getUserId(): int {
		return $this->userId;
	}

	public function getStatus(): OrderStatus {
		return $this->status;
	}

	public function getPaymentMethod(): string {
		return $this->paymentMethod;
	}

	public function getCurrency(): string {
		return $this->currency;
	}

	public function getSubtotal(): float {
		return $this->subtotal;
	}

	public function getDiscount(): float {
		return $this->discount;
	}

	public function getTax(): float {
		return $this->tax;
	}

	public function getTotal(): float {
		return $this->total;
	}

	public function getPaymentReference(): ?string {
		return $this->paymentReference;
	}

	public function getNotes(): ?string {
		return $this->notes;
	}

	public function hasConsent(): bool {
		return $this->consent;
	}

	public function setNotes( ?string $notes ): void {
		$this->notes = $notes;
		$this->touch();
	}

	public function getCreatedAt(): CarbonImmutable {
		return $this->createdAt;
	}

	public function getUpdatedAt(): ?CarbonImmutable {
		return $this->updatedAt;
	}

	public function markPaid( ?string $paymentReference = null ): void {
		$this->status           = OrderStatus::paid();
		$this->paymentReference = $paymentReference ?? $this->paymentReference;
		$this->touch();
	}

	public function markPending(): void {
		$this->status = OrderStatus::pending();
		$this->touch();
	}

	public function markFailed(): void {
		$this->status = OrderStatus::failed();
		$this->touch();
	}

	public function markRefunded(): void {
		$this->status = OrderStatus::refunded();
		$this->touch();
	}

	public function setPaymentReference( ?string $paymentReference ): void {
		$this->paymentReference = $paymentReference;
		$this->touch();
	}

	private function touch(): void {
		$this->updatedAt = CarbonImmutable::now();
	}

	public function toArray(): array {
		return [
			'id'                => $this->id,
			'user_id'           => $this->userId,
			'status'            => $this->status->getValue(),
			'payment_method'    => $this->paymentMethod,
			'currency'          => $this->currency,
			'subtotal'          => $this->subtotal,
			'discount'          => $this->discount,
			'tax'               => $this->tax,
			'total'             => $this->total,
			'payment_reference' => $this->paymentReference,
			'notes'             => $this->notes,
			'consent'           => $this->consent,
			'created_at'        => $this->createdAt->format( 'Y-m-d H:i:s' ),
			'updated_at'        => $this->updatedAt?->format( 'Y-m-d H:i:s' ),
		];
	}
}
