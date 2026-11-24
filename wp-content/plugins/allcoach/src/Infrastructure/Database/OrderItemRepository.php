<?php

declare(strict_types=1);

namespace AllCoach\Infrastructure\Database;

defined( 'ABSPATH' ) || exit;

use AllCoach\Domain\Order\OrderItem;
use AllCoach\Domain\Order\OrderItemRepository as OrderItemRepositoryInterface;
use Carbon\CarbonImmutable;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter

class OrderItemRepository implements OrderItemRepositoryInterface {

	private string $table;

	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'allcoach_order_items';
	}

	public function findById( int $id ): ?OrderItem {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE id = %d LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$id
			),
			ARRAY_A
		);

		return $row ? $this->hydrate( $row ) : null;
	}

	/** @return OrderItem[] */
	public function findByOrderId( int $orderId ): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE order_id = %d ORDER BY id ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$orderId
			),
			ARRAY_A
		) ?: [];

		return array_map( [ $this, 'hydrate' ], $rows );
	}

	public function save( OrderItem $item ): OrderItem {
		global $wpdb;

		if ( ! $item->isNew() ) {
			return $item;
		}

		$result = $wpdb->insert(
			$this->table,
			[
				'order_id'   => $item->getOrderId(),
				'program_id' => $item->getProgramId(),
				'price'      => $item->getPrice(),
				'quantity'   => $item->getQuantity(),
				'total'      => $item->getTotal(),
				'created_at' => $item->getCreatedAt()->format( 'Y-m-d H:i:s' ),
			],
			[ '%d', '%d', '%f', '%d', '%f', '%s' ]
		);

		if ( false === $result ) {
			throw new \RuntimeException( 'Failed to insert order item: ' . esc_html( $wpdb->last_error ) );
		}

		return OrderItem::reconstitute(
			id: (int) $wpdb->insert_id,
			orderId: $item->getOrderId(),
			programId: $item->getProgramId(),
			price: $item->getPrice(),
			quantity: $item->getQuantity(),
			total: $item->getTotal(),
			createdAt: $item->getCreatedAt(),
		);
	}

	public function deleteByOrderId( int $orderId ): bool {
		global $wpdb;
		return $wpdb->delete( $this->table, [ 'order_id' => $orderId ], [ '%d' ] ) !== false;
	}

	private function hydrate( array $row ): OrderItem {
		return OrderItem::reconstitute(
			id: (int) $row['id'],
			orderId: (int) $row['order_id'],
			programId: (int) $row['program_id'],
			price: (float) $row['price'],
			quantity: (int) $row['quantity'],
			total: (float) $row['total'],
			createdAt: CarbonImmutable::parse( $row['created_at'] ),
		);
	}
}
