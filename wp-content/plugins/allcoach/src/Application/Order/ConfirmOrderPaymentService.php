<?php

declare(strict_types=1);

namespace AllCoach\Application\Order;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\Events\EventDispatcher;
use AllCoach\Core\Exceptions\NotFoundException;
use AllCoach\Core\Exceptions\ValidationException;
use AllCoach\Domain\Enrollment\EnrollmentRepository;
use AllCoach\Domain\Order\Events\OrderPaid;
use AllCoach\Domain\Order\OrderItemRepository;
use AllCoach\Domain\Order\OrderRepository;
use AllCoach\Support\Logger;

class ConfirmOrderPaymentService {

	public function __construct(
		private readonly OrderRepository $orderRepository,
		private readonly OrderItemRepository $orderItemRepository,
		private readonly EnrollmentRepository $enrollmentRepository,
		private readonly OrderQueryService $queryService,
		private readonly EventDispatcher $events,
		private readonly Logger $logger,
	) {}

	public function execute( int $orderId, ?string $transactionId = null ): OrderView {
		$order = $this->orderRepository->findById( $orderId );
		if ( ! $order ) {
			throw NotFoundException::entity( esc_html__( 'Order', 'allcoach' ), absint( $orderId ) );
		}

		if ( $order->getStatus()->isPaid() ) {
			throw ValidationException::withErrors(
				[ 'payment' => esc_html__( 'This order has already been paid.', 'allcoach' ) ]
			);
		}

		$items = $this->orderItemRepository->findByOrderId( $order->getId() );
		if ( $items === [] ) {
			throw NotFoundException::entity( esc_html__( 'Order item', 'allcoach' ), 0 );
		}
		$enrollment = $this->enrollmentRepository->findByOrderItemId( $items[0]->getId() );
		if ( $enrollment && $enrollment->getStatus()->isCancelled() ) {
			$enrollment->reactivate();
			$this->enrollmentRepository->save( $enrollment );
		}

		$order->markPaid( $transactionId );
		$this->orderRepository->save( $order );

		$this->logger->info(
			'Order payment confirmed',
			[
				'order_id'       => $orderId,
				'transaction_id' => $transactionId,
			]
		);

		$this->events->dispatch( new OrderPaid( $orderId, $order ) );

		$view = $this->queryService->findById( $orderId );
		if ( ! $view ) {
			throw new \RuntimeException( 'Could not load order view after confirm.' );
		}
		return $view;
	}
}
