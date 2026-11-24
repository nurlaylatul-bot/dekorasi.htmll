<?php

declare(strict_types=1);

namespace AllCoach\Infrastructure\Database;

defined( 'ABSPATH' ) || exit;

use AllCoach\Domain\Order\Order;
use AllCoach\Domain\Order\OrderFilter;
use AllCoach\Domain\Order\OrderRepository as OrderRepositoryInterface;
use AllCoach\Domain\Order\OrderRow;
use AllCoach\Domain\Order\OrderStatus;
use Carbon\CarbonImmutable;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter

class OrderRepository implements OrderRepositoryInterface {

	private string $table;

	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'allcoach_orders';
	}

	public function findById( int $id ): ?Order {
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

	/** @return Order[] */
	public function findByUser( int $userId, int $limit = 100, int $offset = 0 ): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE user_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$userId,
				$limit,
				$offset
			),
			ARRAY_A
		) ?: [];

		return array_map( [ $this, 'hydrate' ], $rows );
	}

	/** @return Order[] */
	public function findAll( int $limit = 100, int $offset = 0 ): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} ORDER BY created_at DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$limit,
				$offset
			),
			ARRAY_A
		) ?: [];

		return array_map( [ $this, 'hydrate' ], $rows );
	}

	public function countByUser( int $userId ): int {
		global $wpdb;
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table} WHERE user_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$userId
			)
		);
	}

	public function count(): int {
		global $wpdb;
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/** @return OrderRow[] */
	public function findWithFilter( OrderFilter $filter, int $limit = 20, int $offset = 0 ): array {
		global $wpdb;

		[ $from, $where, $params ] = $this->buildFilterQuery( $filter );

		$whereClause = $where !== [] ? 'WHERE ' . implode( ' AND ', $where ) : '';
		$sql         = "SELECT o.id AS order_id, o.status AS order_status, o.payment_method, o.currency,
			o.total, o.payment_reference, o.notes, o.created_at, o.updated_at,
			e.id AS enrollment_id, e.user_id AS client_id, oi.program_id,
			u.display_name AS client_name, p.post_title AS program_title
			{$from} {$whereClause} ORDER BY o.created_at DESC LIMIT %d OFFSET %d"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$params[] = $limit;
		$params[] = $offset;

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ), ARRAY_A ) ?: []; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return array_map( [ $this, 'hydrateRow' ], $rows );
	}

	public function countWithFilter( OrderFilter $filter ): int {
		global $wpdb;

		[ $from, $where, $params ] = $this->buildFilterQuery( $filter );

		$whereClause = $where !== [] ? 'WHERE ' . implode( ' AND ', $where ) : '';
		$sql         = "SELECT COUNT(*) {$from} {$whereClause}"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( $params !== [] ) {
			return (int) $wpdb->get_var( $wpdb->prepare( $sql, ...$params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		return (int) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Builds the FROM/JOIN clause, WHERE conditions, and parameter list from an OrderFilter.
	 *
	 * @return array{0: string, 1: string[], 2: mixed[]}
	 */
	private function buildFilterQuery( OrderFilter $filter ): array {
		global $wpdb;

		$ordersTable      = $this->table;
		$itemsTable       = $wpdb->prefix . 'allcoach_order_items';
		$enrollmentsTable = $wpdb->prefix . 'allcoach_enrollments';
		$usersTable       = $wpdb->users;
		$postsTable       = $wpdb->posts;

		$from = "FROM {$ordersTable} o
			INNER JOIN {$itemsTable} oi ON oi.order_id = o.id
			INNER JOIN {$enrollmentsTable} e ON e.order_item_id = oi.id
			INNER JOIN {$usersTable} u ON u.ID = e.user_id
			INNER JOIN {$postsTable} p ON p.ID = oi.program_id"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$where  = [];
		$params = [];

		if ( $filter->clientId !== null ) {
			$where[]  = 'e.user_id = %d';
			$params[] = $filter->clientId;
		}

		if ( $filter->programId !== null ) {
			$where[]  = 'oi.program_id = %d';
			$params[] = $filter->programId;
		}

		if ( $filter->status !== null ) {
			$domainStatuses = $this->mapViewStatusToDomain( $filter->status );
			if ( $domainStatuses === [] ) {
				$where[] = '1 = 0';
			} else {
				$placeholders = implode( ', ', array_fill( 0, count( $domainStatuses ), '%s' ) );
				$where[]      = "o.status IN ({$placeholders})"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$params       = array_merge( $params, $domainStatuses );
			}
		}

		if ( $filter->fromDate !== null ) {
			$where[]  = 'DATE(o.created_at) >= %s';
			$params[] = $filter->fromDate;
		}

		if ( $filter->toDate !== null ) {
			$where[]  = 'DATE(o.created_at) <= %s';
			$params[] = $filter->toDate;
		}

		if ( $filter->search !== null && $filter->search !== '' ) {
			$like     = '%' . $wpdb->esc_like( $filter->search ) . '%';
			$where[]  = '(u.display_name LIKE %s OR p.post_title LIKE %s OR CAST(o.id AS CHAR) LIKE %s OR o.payment_reference LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		if ( $filter->excludeDemo ) {
			$where[] = 'o.is_demo = 0';
		}

		return [ $from, $where, $params ];
	}

	/**
	 * @return string[]
	 */
	private function mapViewStatusToDomain( string $viewStatus ): array {
		return match ( $viewStatus ) {
			'confirmed' => [ 'paid' ],
			'cancelled' => [ 'refunded', 'failed' ],
			'pending'   => [ 'pending' ],
			default     => [],
		};
	}

	private function hydrateRow( array $row ): OrderRow {
		return new OrderRow(
			orderId: (int) $row['order_id'],
			orderStatus: $row['order_status'],
			paymentMethod: $row['payment_method'],
			currency: $row['currency'],
			total: (float) $row['total'],
			paymentReference: $row['payment_reference'] ?: null,
			createdAt: $row['created_at'],
			updatedAt: $row['updated_at'] ?: null,
			enrollmentId: (int) $row['enrollment_id'],
			clientId: (int) $row['client_id'],
			programId: (int) $row['program_id'],
			clientName: $row['client_name'] ?? '',
			programTitle: $row['program_title'] ?? '',
			notes: $row['notes'] ?? null,
		);
	}

	public function save( Order $order ): Order {
		return $order->isNew() ? $this->insert( $order ) : $this->update( $order );
	}

	public function delete( int $id ): bool {
		global $wpdb;
		return $wpdb->delete( $this->table, [ 'id' => $id ], [ '%d' ] ) !== false;
	}

	public function deleteByUser( int $userId ): int {
		global $wpdb;

		$itemsTable = $wpdb->prefix . 'allcoach_order_items';

		// Remove order items belonging to this user's orders first, then the orders.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$itemsTable} WHERE order_id IN ( SELECT id FROM {$this->table} WHERE user_id = %d )", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$userId
			)
		);

		$result = $wpdb->delete( $this->table, [ 'user_id' => $userId ], [ '%d' ] );

		return $result !== false ? $result : 0;
	}

	private function insert( Order $order ): Order {
		global $wpdb;

		$result = $wpdb->insert(
			$this->table,
			[
				'user_id'           => $order->getUserId(),
				'status'            => $order->getStatus()->getValue(),
				'payment_method'    => $order->getPaymentMethod(),
				'currency'          => $order->getCurrency(),
				'subtotal'          => $order->getSubtotal(),
				'discount'          => $order->getDiscount(),
				'tax'               => $order->getTax(),
				'total'             => $order->getTotal(),
				'payment_reference' => $order->getPaymentReference(),
				'notes'             => $order->getNotes(),
				'consent'           => $order->hasConsent() ? 1 : 0,
				'created_at'        => $order->getCreatedAt()->format( 'Y-m-d H:i:s' ),
				'updated_at'        => $order->getUpdatedAt()?->format( 'Y-m-d H:i:s' ),
			],
			[ '%d', '%s', '%s', '%s', '%f', '%f', '%f', '%f', '%s', '%s', '%d', '%s', '%s' ]
		);

		if ( false === $result ) {
			throw new \RuntimeException( 'Failed to insert order: ' . esc_html( $wpdb->last_error ) );
		}

		return Order::reconstitute(
			id: (int) $wpdb->insert_id,
			userId: $order->getUserId(),
			status: $order->getStatus(),
			paymentMethod: $order->getPaymentMethod(),
			currency: $order->getCurrency(),
			subtotal: $order->getSubtotal(),
			discount: $order->getDiscount(),
			tax: $order->getTax(),
			total: $order->getTotal(),
			paymentReference: $order->getPaymentReference(),
			notes: $order->getNotes(),
			createdAt: $order->getCreatedAt(),
			updatedAt: $order->getUpdatedAt(),
			consent: $order->hasConsent(),
		);
	}

	private function update( Order $order ): Order {
		global $wpdb;

		$result = $wpdb->update(
			$this->table,
			[
				'status'            => $order->getStatus()->getValue(),
				'payment_reference' => $order->getPaymentReference(),
				'updated_at'        => current_time( 'mysql' ),
			],
			[ 'id' => $order->getId() ],
			[ '%s', '%s', '%s' ],
			[ '%d' ]
		);

		if ( false === $result ) {
			throw new \RuntimeException( 'Failed to update order: ' . esc_html( $wpdb->last_error ) );
		}

		return $order;
	}

	private function hydrate( array $row ): Order {
		return Order::reconstitute(
			id: (int) $row['id'],
			userId: (int) $row['user_id'],
			status: OrderStatus::fromString( $row['status'] ),
			paymentMethod: $row['payment_method'],
			currency: $row['currency'],
			subtotal: (float) $row['subtotal'],
			discount: (float) $row['discount'],
			tax: (float) $row['tax'],
			total: (float) $row['total'],
			paymentReference: $row['payment_reference'] ?? null,
			notes: $row['notes'] ?? null,
			createdAt: CarbonImmutable::parse( $row['created_at'] ),
			updatedAt: ! empty( $row['updated_at'] ) ? CarbonImmutable::parse( $row['updated_at'] ) : null,
			consent: ! array_key_exists( 'consent', $row ) || (bool) $row['consent'],
		);
	}
}
