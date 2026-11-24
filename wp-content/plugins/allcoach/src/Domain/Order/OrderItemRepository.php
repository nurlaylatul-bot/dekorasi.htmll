<?php

declare(strict_types=1);

namespace AllCoach\Domain\Order;

defined( 'ABSPATH' ) || exit;

interface OrderItemRepository {

	public function findById( int $id ): ?OrderItem;

	/** @return OrderItem[] */
	public function findByOrderId( int $orderId ): array;

	public function save( OrderItem $item ): OrderItem;

	public function deleteByOrderId( int $orderId ): bool;
}
