<?php

declare(strict_types=1);

namespace AllCoach\Application\Order;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\Database\Transaction;
use AllCoach\Core\Exceptions\NotFoundException;
use AllCoach\Core\Exceptions\ValidationException;
use AllCoach\Domain\Enrollment\EnrollmentRepository;
use AllCoach\Domain\Order\OrderItemRepository;
use AllCoach\Domain\Order\OrderRepository;
use AllCoach\Support\Logger;

class AbandonOrderService {

	public function __construct(
		private readonly OrderRepository $orderRepository,
		private readonly OrderItemRepository $orderItemRepository,
		private readonly EnrollmentRepository $enrollmentRepository,
		private readonly Logger $logger,
	) {}

	public function execute( int $orderId ): void {
		$order = $this->orderRepository->findById( $orderId );
		if ( ! $order ) {
			throw NotFoundException::entity( esc_html__( 'Order', 'allcoach' ), absint( $orderId ) );
		}

		if ( $order->getStatus()->isFailed() ) {
			return;
		}

		if ( ! $order->getStatus()->isPending() ) {
			throw ValidationException::withErrors(
				[ 'order' => esc_html__( 'This order can no longer be abandoned.', 'allcoach' ) ]
			);
		}

		$userId = $order->getUserId();

		Transaction::run( function () use ( $orderId, $userId ) {
			$order = $this->orderRepository->findById( $orderId );
			if ( ! $order || ! $order->getStatus()->isPending() ) {
				return;
			}

			foreach ( $this->orderItemRepository->findByOrderId( $order->getId() ) as $item ) {
				$enrollment = $this->enrollmentRepository->findByOrderItemId( $item->getId() );
				if ( $enrollment && ! $enrollment->getStatus()->isCancelled() ) {
					$enrollment->cancel();
					$this->enrollmentRepository->save( $enrollment );
				}
			}

			$order->markFailed();
			$this->orderRepository->save( $order );

			$this->logger->info(
				'Order abandoned',
				[
					'order_id' => $orderId,
					'user_id'  => $userId,
				]
			);
		} );
	}
}
