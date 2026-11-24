<?php

declare(strict_types=1);

namespace AllCoach\Application\Order;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\Exceptions\NotFoundException;
use AllCoach\Core\Exceptions\ValidationException;
use AllCoach\Domain\Enrollment\EnrollmentRepository;
use AllCoach\Domain\Order\OrderItemRepository;
use AllCoach\Domain\Order\OrderRepository;

class ReactivateOrderService {

	public function __construct(
		private readonly OrderRepository $orderRepository,
		private readonly OrderItemRepository $orderItemRepository,
		private readonly EnrollmentRepository $enrollmentRepository,
		private readonly OrderQueryService $queryService,
	) {}

	public function execute( int $orderId ): OrderView {
		$order = $this->orderRepository->findById( $orderId );
		if ( ! $order ) {
			throw NotFoundException::entity( esc_html__( 'Order', 'allcoach' ), absint( $orderId ) );
		}

		if ( ! $order->getStatus()->isRefunded() && ! $order->getStatus()->isFailed() ) {
			throw ValidationException::withErrors(
				[ 'order' => esc_html__( 'Only cancelled orders can be reactivated to pending.', 'allcoach' ) ]
			);
		}

		$items = $this->orderItemRepository->findByOrderId( $order->getId() );
		if ( $items === [] ) {
			throw NotFoundException::entity( esc_html__( 'Order item', 'allcoach' ), 0 );
		}

		$enrollment = $this->enrollmentRepository->findByOrderItemId( $items[0]->getId() );
		if ( ! $enrollment ) {
			throw NotFoundException::entity( esc_html__( 'Enrollment', 'allcoach' ), 0 );
		}

		$enrollment->reactivate();
		$this->enrollmentRepository->save( $enrollment );

		$order->markPending();
		$this->orderRepository->save( $order );

		$view = $this->queryService->findById( $orderId );
		if ( ! $view ) {
			throw new \RuntimeException( 'Could not load order view after reactivation.' );
		}
		return $view;
	}
}
