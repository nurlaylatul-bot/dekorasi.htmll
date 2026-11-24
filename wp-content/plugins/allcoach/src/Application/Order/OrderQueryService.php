<?php

declare(strict_types=1);

namespace AllCoach\Application\Order;

defined( 'ABSPATH' ) || exit;

use AllCoach\Domain\Client\ClientRepository;
use AllCoach\Domain\Enrollment\EnrollmentRepository;
use AllCoach\Domain\Order\Order;
use AllCoach\Domain\Order\OrderFilter;
use AllCoach\Domain\Order\OrderItemRepository;
use AllCoach\Domain\Order\OrderRepository;
use AllCoach\Domain\Order\OrderRow;
use AllCoach\Domain\Program\ProgramRepository;

final class OrderQueryService {

	private int $totalCount = 0;

	public function __construct(
		private readonly OrderRepository $orderRepository,
		private readonly OrderItemRepository $orderItemRepository,
		private readonly EnrollmentRepository $enrollmentRepository,
		private readonly ClientRepository $clientRepository,
		private readonly ProgramRepository $programRepository,
	) {}

	public function findById( int $orderId ): ?OrderView {
		$order = $this->orderRepository->findById( $orderId );
		if ( ! $order ) {
			return null;
		}
		$items = $this->orderItemRepository->findByOrderId( $order->getId() );
		if ( $items === [] ) {
			return null;
		}
		$enrollment = $this->enrollmentRepository->findByOrderItemId( $items[0]->getId() );
		if ( ! $enrollment ) {
			return null;
		}

		$client  = $this->clientRepository->findById( $order->getUserId() );
		$program = $this->programRepository->findById( $enrollment->getProgramId() );

		return OrderView::fromOrder( $order, $enrollment, $client?->getFirstName(), $program?->getTitle() );
	}

	/**
	 * @return OrderView[]
	 */
	public function findWithFilter( OrderFilter $filter, int $page = 1, int $perPage = 20 ): array {
		$offset           = ( $page - 1 ) * $perPage;
		$this->totalCount = $this->orderRepository->countWithFilter( $filter );
		$rows             = $this->orderRepository->findWithFilter( $filter, $perPage, $offset );

		return array_map( static fn( OrderRow $row ) => OrderView::fromRow( $row ), $rows );
	}

	/** @return OrderView[] */
	public function getAll( int $page = 1, int $perPage = 20 ): array {
		return $this->findWithFilter( new OrderFilter(), $page, $perPage );
	}

	/** @return OrderView[] */
	public function getByClient( int $clientId, int $page = 1, int $perPage = 20 ): array {
		return $this->findWithFilter( new OrderFilter( clientId: $clientId ), $page, $perPage );
	}

	/** @return OrderView[] */
	public function getByProgram( int $programId, int $page = 1, int $perPage = 20 ): array {
		return $this->findWithFilter( new OrderFilter( programId: $programId ), $page, $perPage );
	}

	public function getTotalCount(): int {
		return $this->totalCount;
	}

	/**
	 * Orders awaiting payment verification — used for the "pending orders" admin menu badge.
	 */
	public function getPendingCount(): int {
		return $this->orderRepository->countWithFilter( new OrderFilter( status: 'pending' ) );
	}
}
