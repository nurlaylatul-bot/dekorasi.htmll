<?php

declare(strict_types=1);

namespace AllCoach\Modules\Demo;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\Settings\SettingsManager;
use AllCoach\Domain\Program\ProgramRepository;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

class DemoOrderService {

	private const TTL_SECONDS = 8 * HOUR_IN_SECONDS;

	private string $ordersTable;
	private string $orderItemsTable;
	private string $enrollmentsTable;

	public function __construct(
		private readonly ProgramRepository $programRepository,
		private readonly SettingsManager $settingsManager,
	) {
		global $wpdb;
		$this->ordersTable      = $wpdb->prefix . 'allcoach_orders';
		$this->orderItemsTable  = $wpdb->prefix . 'allcoach_order_items';
		$this->enrollmentsTable = $wpdb->prefix . 'allcoach_enrollments';
	}

	/**
	 * Create a paid demo order + order_item + enrollment for the given user/program.
	 * All rows are flagged with is_demo=1, demo_token, and expires_at.
	 *
	 * @throws \RuntimeException on DB failure.
	 */
	public function createForProgram( int $demoUserId, int $programId, string $token ): void {
		global $wpdb;

		$program   = $this->programRepository->findById( $programId );
		$amount    = $program ? $program->getPrice() : 0.0;
		$currency  = (string) $this->settingsManager->get( 'currency' );
		$expiresAt = gmdate( 'Y-m-d H:i:s', time() + self::TTL_SECONDS );
		$now       = current_time( 'mysql', true );

		$wpdb->insert(
			$this->ordersTable,
			[
				'user_id'        => $demoUserId,
				'status'         => 'paid',
				'payment_method' => 'offline',
				'currency'       => $currency,
				'subtotal'       => $amount,
				'discount'       => 0.0,
				'tax'            => 0.0,
				'total'          => $amount,
				'is_demo'        => 1,
				'demo_token'     => $token,
				'expires_at'     => $expiresAt,
				'created_at'     => $now,
			],
			[ '%d', '%s', '%s', '%s', '%f', '%f', '%f', '%f', '%d', '%s', '%s', '%s' ]
		);

		if ( ! $wpdb->insert_id ) {
			throw new \RuntimeException( 'Failed to create demo order: ' . esc_html( $wpdb->last_error ) );
		}

		$orderId = (int) $wpdb->insert_id;

		$wpdb->insert(
			$this->orderItemsTable,
			[
				'order_id'   => $orderId,
				'program_id' => $programId,
				'price'      => $amount,
				'quantity'   => 1,
				'total'      => $amount,
				'is_demo'    => 1,
				'demo_token' => $token,
				'expires_at' => $expiresAt,
				'created_at' => $now,
			],
			[ '%d', '%d', '%f', '%d', '%f', '%d', '%s', '%s', '%s' ]
		);

		if ( ! $wpdb->insert_id ) {
			throw new \RuntimeException( 'Failed to create demo order item: ' . esc_html( $wpdb->last_error ) );
		}

		$itemId = (int) $wpdb->insert_id;

		$wpdb->insert(
			$this->enrollmentsTable,
			[
				'user_id'       => $demoUserId,
				'program_id'    => $programId,
				'order_item_id' => $itemId,
				'status'        => 'active',
				'enrolled_at'   => $now,
				'is_demo'       => 1,
				'demo_token'    => $token,
				'expires_at'    => $expiresAt,
				'created_at'    => $now,
			],
			[ '%d', '%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s' ]
		);

		if ( ! $wpdb->insert_id ) {
			throw new \RuntimeException( 'Failed to create demo enrollment: ' . esc_html( $wpdb->last_error ) );
		}
	}

	public function findEnrollmentId( int $demoUserId, int $programId ): ?int {
		global $wpdb;

		$id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$this->enrollmentsTable}
				WHERE user_id = %d AND program_id = %d AND is_demo = 1 AND status = 'active'
				LIMIT 1",
				$demoUserId,
				$programId
			)
		);

		return $id ? (int) $id : null;
	}

	public function hasDemoEnrollment( int $userId, int $programId ): bool {
		return $this->findEnrollmentId( $userId, $programId ) !== null;
	}

	public function hasAnyDemoEnrollment( int $userId ): bool {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->enrollmentsTable}
				WHERE user_id = %d AND is_demo = 1 AND status = 'active'",
				$userId
			)
		) > 0;
	}

	/**
	 * Stamp existing order + enrollment rows with is_demo = 1.
	 * Called via the allcoach:order:checkout_created action after a real order is created.
	 */
	public function stampCreatedOrder( int $orderId, int $enrollmentId ): void {
		global $wpdb;

		if ( $orderId > 0 ) {
			$wpdb->update(
				$this->ordersTable,
				[ 'is_demo' => 1 ],
				[ 'id' => $orderId ],
				[ '%d' ],
				[ '%d' ]
			);
		}

		if ( $enrollmentId > 0 ) {
			$wpdb->update(
				$this->enrollmentsTable,
				[ 'is_demo' => 1 ],
				[ 'id' => $enrollmentId ],
				[ '%d' ],
				[ '%d' ]
			);
		}
	}
}
