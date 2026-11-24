<?php

declare(strict_types=1);

namespace AllCoach\Domain\Order;

defined( 'ABSPATH' ) || exit;

interface OrderRepository {

	public function findById( int $id ): ?Order;

	/** @return Order[] */
	public function findByUser( int $userId, int $limit = 100, int $offset = 0 ): array;

	public function countByUser( int $userId ): int;

	/** @return Order[] */
	public function findAll( int $limit = 100, int $offset = 0 ): array;

	public function count(): int;

	public function save( Order $order ): Order;

	public function delete( int $id ): bool;

	/**
	 * Delete all orders (and their order items) belonging to a user.
	 *
	 * @return int Number of orders deleted.
	 */
	public function deleteByUser( int $userId ): int;

	/**
	 * @return OrderRow[]
	 */
	public function findWithFilter( OrderFilter $filter, int $limit = 20, int $offset = 0 ): array;

	public function countWithFilter( OrderFilter $filter ): int;
}
