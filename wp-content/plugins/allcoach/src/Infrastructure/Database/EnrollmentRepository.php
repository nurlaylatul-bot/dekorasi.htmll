<?php

declare(strict_types=1);

namespace AllCoach\Infrastructure\Database;

defined( 'ABSPATH' ) || exit;

use AllCoach\Domain\Enrollment\Enrollment;
use AllCoach\Domain\Enrollment\EnrollmentRepository as EnrollmentRepositoryInterface;
use AllCoach\Domain\Enrollment\EnrollmentStatus;
use Carbon\CarbonImmutable;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter

class EnrollmentRepository implements EnrollmentRepositoryInterface {

	private string $table;

	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'allcoach_enrollments';
	}

	public function findById( int $id ): ?Enrollment {
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

	/** @return Enrollment[] */
	public function findAll( int $limit = 100, int $offset = 0 ): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} ORDER BY enrolled_at DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$limit,
				$offset
			),
			ARRAY_A
		) ?: [];

		return array_map( [ $this, 'hydrate' ], $rows );
	}

	public function count(): int {
		global $wpdb;
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/** @return Enrollment[] */
	public function findByUser( int $userId, int $limit = 100, int $offset = 0 ): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE user_id = %d ORDER BY enrolled_at DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$userId,
				$limit,
				$offset
			),
			ARRAY_A
		) ?: [];

		return array_map( [ $this, 'hydrate' ], $rows );
	}

	/** @return Enrollment[] */
	public function findByProgram( int $programId, int $limit = 100, int $offset = 0 ): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE program_id = %d ORDER BY enrolled_at DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$programId,
				$limit,
				$offset
			),
			ARRAY_A
		) ?: [];

		return array_map( [ $this, 'hydrate' ], $rows );
	}

	public function findByUserAndProgram( int $userId, int $programId ): ?Enrollment {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE user_id = %d AND program_id = %d ORDER BY enrolled_at DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$userId,
				$programId
			),
			ARRAY_A
		);

		return $row ? $this->hydrate( $row ) : null;
	}

	public function findByOrderItemId( int $orderItemId ): ?Enrollment {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE order_item_id = %d LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$orderItemId
			),
			ARRAY_A
		);

		return $row ? $this->hydrate( $row ) : null;
	}

	public function save( Enrollment $enrollment ): Enrollment {
		global $wpdb;

		if ( $enrollment->isNew() ) {
			$result = $wpdb->insert(
				$this->table,
				[
					'user_id'       => $enrollment->getUserId(),
					'program_id'    => $enrollment->getProgramId(),
					'order_item_id' => $enrollment->getOrderItemId(),
					'status'        => $enrollment->getStatus()->getValue(),
					'enrolled_at'   => $enrollment->getEnrolledAt()->format( 'Y-m-d H:i:s' ),
					'expires_at'    => $enrollment->getExpiresAt()?->format( 'Y-m-d H:i:s' ),
					'created_at'    => $enrollment->getCreatedAt()->format( 'Y-m-d H:i:s' ),
				],
				[ '%d', '%d', '%d', '%s', '%s', '%s', '%s' ]
			);

			if ( false === $result ) {
				throw new \RuntimeException( 'Failed to insert enrollment: ' . esc_html( $wpdb->last_error ) );
			}

			return Enrollment::reconstitute(
				id: (int) $wpdb->insert_id,
				userId: $enrollment->getUserId(),
				programId: $enrollment->getProgramId(),
				orderItemId: $enrollment->getOrderItemId(),
				status: $enrollment->getStatus(),
				enrolledAt: $enrollment->getEnrolledAt(),
				expiresAt: $enrollment->getExpiresAt(),
				createdAt: $enrollment->getCreatedAt(),
			);
		}

		$result = $wpdb->update(
			$this->table,
			[ 'status' => $enrollment->getStatus()->getValue() ],
			[ 'id' => $enrollment->getId() ],
			[ '%s' ],
			[ '%d' ]
		);

		if ( false === $result ) {
			throw new \RuntimeException( 'Failed to update enrollment: ' . esc_html( $wpdb->last_error ) );
		}

		return $enrollment;
	}

	public function countActiveByProgram( int $programId ): int {
		global $wpdb;

		$itemsTable  = $wpdb->prefix . 'allcoach_order_items';
		$ordersTable = $wpdb->prefix . 'allcoach_orders';

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table} e
				INNER JOIN {$itemsTable} oi ON oi.id = e.order_item_id
				INNER JOIN {$ordersTable} o ON o.id = oi.order_id
				WHERE e.program_id = %d AND e.status IN ('active', 'complete') AND e.is_demo = '0' AND o.status = 'paid'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$programId
			)
		);
	}

	public function countByProgram( int $programId ): int {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table} WHERE program_id = %d AND is_demo = '0'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$programId
			)
		);
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

	public function hasActiveEnrollment( int $userId, int $programId ): bool {
		global $wpdb;

		$itemsTable  = $wpdb->prefix . 'allcoach_order_items';
		$ordersTable = $wpdb->prefix . 'allcoach_orders';

		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table} e
				INNER JOIN {$itemsTable} oi ON oi.id = e.order_item_id
				INNER JOIN {$ordersTable} o ON o.id = oi.order_id
				WHERE e.user_id = %d AND e.program_id = %d AND e.status = 'active' AND o.status = 'paid'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$userId,
				$programId
			)
		);

		return $count > 0;
	}

	public function isOrderPaid( int $enrollmentId ): bool {
		global $wpdb;

		$itemsTable  = $wpdb->prefix . 'allcoach_order_items';
		$ordersTable = $wpdb->prefix . 'allcoach_orders';

		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table} e
				INNER JOIN {$itemsTable} oi ON oi.id = e.order_item_id
				INNER JOIN {$ordersTable} o ON o.id = oi.order_id
				WHERE e.id = %d AND o.status = 'paid'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$enrollmentId
			)
		);

		return $count > 0;
	}

	public function delete( int $id ): bool {
		global $wpdb;
		return $wpdb->delete( $this->table, [ 'id' => $id ], [ '%d' ] ) !== false;
	}

	public function deleteByUser( int $userId ): int {
		global $wpdb;
		$result = $wpdb->delete( $this->table, [ 'user_id' => $userId ], [ '%d' ] );
		return $result !== false ? $result : 0;
	}

	private function hydrate( array $row ): Enrollment {
		return Enrollment::reconstitute(
			id: (int) $row['id'],
			userId: (int) $row['user_id'],
			programId: (int) $row['program_id'],
			orderItemId: (int) $row['order_item_id'],
			status: EnrollmentStatus::fromString( $row['status'] ),
			enrolledAt: CarbonImmutable::parse( $row['enrolled_at'] ),
			expiresAt: ! empty( $row['expires_at'] ) ? CarbonImmutable::parse( $row['expires_at'] ) : null,
			createdAt: CarbonImmutable::parse( $row['created_at'] ),
		);
	}
}
