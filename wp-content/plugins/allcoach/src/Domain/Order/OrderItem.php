<?php

declare(strict_types=1);

namespace AllCoach\Domain\Order;

defined( 'ABSPATH' ) || exit;

use AllCoach\Domain\Shared\Entity;
use Carbon\CarbonImmutable;

class OrderItem extends Entity {

	public function __construct(
		private int $orderId,
		private int $programId,
		private float $price,
		private int $quantity,
		private float $total,
		private CarbonImmutable $createdAt
	) {}

	public static function reconstitute(
		int $id,
		int $orderId,
		int $programId,
		float $price,
		int $quantity,
		float $total,
		CarbonImmutable $createdAt,
	): self {
		$item     = new self( $orderId, $programId, $price, $quantity, $total, $createdAt );
		$item->id = $id;
		return $item;
	}

	public function getOrderId(): int {
		return $this->orderId;
	}

	public function getProgramId(): int {
		return $this->programId;
	}

	public function getPrice(): float {
		return $this->price;
	}

	public function getQuantity(): int {
		return $this->quantity;
	}

	public function getTotal(): float {
		return $this->total;
	}

	public function getCreatedAt(): CarbonImmutable {
		return $this->createdAt;
	}

	public function toArray(): array {
		return [
			'id'         => $this->id,
			'order_id'   => $this->orderId,
			'program_id' => $this->programId,
			'price'      => $this->price,
			'quantity'   => $this->quantity,
			'total'      => $this->total,
			'created_at' => $this->createdAt->format( 'Y-m-d H:i:s' ),
		];
	}
}
