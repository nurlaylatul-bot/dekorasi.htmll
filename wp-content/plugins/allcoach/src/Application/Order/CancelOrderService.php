<?php

declare(strict_types=1);

namespace AllCoach\Application\Order;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\Events\EventDispatcher;
use AllCoach\Core\Exceptions\NotFoundException;
use AllCoach\Core\Exceptions\ValidationException;
use AllCoach\Domain\Enrollment\EnrollmentRepository;
use AllCoach\Domain\Order\Events\OrderCancelled;
use AllCoach\Domain\Order\OrderItemRepository;
use AllCoach\Domain\Order\OrderRepository;
use AllCoach\Support\Logger;

class CancelOrderService {

	public function __construct(
		private readonly OrderRepository $orderRepository,
		private readonly OrderItemRepository $orderItemRepository,
		private readonly EnrollmentRepository $enrollmentRepository,
		private readonly OrderQueryService $queryService,
		private readonly EventDispatcher $events,
		private readonly Logger $logger,
	) {}

	public function execute( int $orderId ): OrderView {
		$order = $this->orderRepository->findById( $orderId );
		if ( ! $order ) {
			throw NotFoundException::entity( esc_html__( 'Order', 'allcoach' ), absint( $orderId ) );
		}

		$items = $this->orderItemRepository->findByOrderId( $order->getId() );
		if ( $items === [] ) {
			throw NotFoundException::entity( esc_html__( 'Order item', 'allcoach' ), 0 );
		}
		$enrollment = $this->enrollmentRepository->findByOrderItemId( $items[0]->getId() );
		if ( ! $enrollment ) {
			throw NotFoundException::entity( esc_html__( 'Enrollment', 'allcoach' ), 0 );
		}

		if ( $enrollment->getStatus()->isExpired() ) {
			throw ValidationException::withErrors(
				[ 'order' => esc_html__( 'Cannot cancel an expired enrollment.', 'allcoach' ) ]
			);
		}

		if ( $enrollment->getStatus()->isCancelled() && $order->getStatus()->isRefunded() ) {
			throw ValidationException::withErrors(
				[ 'order' => esc_html__( 'This order is already cancelled.', 'allcoach' ) ]
			);
		}

		if ( ! $enrollment->getStatus()->isCancelled() ) {
			$enrollment->cancel();
			$this->enrollmentRepository->save( $enrollment );
		}

		if ( ! $order->getStatus()->isRefunded() ) {
			$order->markRefunded();
			$this->orderRepository->save( $order );
		}

		$this->logger->info(
			'Order cancelled',
			[
				'order_id'      => $orderId,
				'enrollment_id' => $enrollment->getId(),
				'client_id'     => $enrollment->getUserId(),
				'program_id'    => $enrollment->getProgramId(),
			]
		);

		$this->events->dispatch( new OrderCancelled( $enrollment->getId() ) );

		$view = $this->queryService->findById( $orderId );
		if ( ! $view ) {
			throw new \RuntimeException( 'Could not load order view after cancel.' );
		}
		return $view;
	}
}
