<?php

declare(strict_types=1);

namespace AllCoach\Application\Order;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\Database\Transaction;
use AllCoach\Core\Events\EventDispatcher;
use AllCoach\Core\Exceptions\NotFoundException;
use AllCoach\Core\Exceptions\ValidationException;
use AllCoach\Domain\Enrollment\Enrollment;
use AllCoach\Domain\Enrollment\EnrollmentRepository;
use AllCoach\Domain\Order\Events\OrderCreated;
use AllCoach\Domain\Order\Order;
use AllCoach\Domain\Order\OrderItem;
use AllCoach\Domain\Order\OrderItemRepository;
use AllCoach\Domain\Order\OrderRepository;
use AllCoach\Domain\Program\ProgramRepository;
use AllCoach\Domain\Client\ClientRepository;
use AllCoach\Core\Settings\SettingsManager;
use AllCoach\Domain\Booking\PaymentGateway;
use AllCoach\Support\Logger;
use Carbon\CarbonImmutable;

class CreateOrderService {

	public function __construct(
		private readonly ClientRepository $clientRepository,
		private readonly ProgramRepository $programRepository,
		private readonly OrderRepository $orderRepository,
		private readonly OrderItemRepository $orderItemRepository,
		private readonly EnrollmentRepository $enrollmentRepository,
		private readonly PaymentGateway $paymentGateway,
		private readonly SettingsManager $settingsManager,
		private readonly EventDispatcher $events,
		private readonly Logger $logger,
	) {}

	public function execute( CreateOrderDTO $dto ): OrderView {
		$client = $this->clientRepository->findById( $dto->clientId );
		if ( ! $client ) {
			throw NotFoundException::entity( esc_html__( 'Client', 'allcoach' ), absint( $dto->clientId ) );
		}

		$program = $this->programRepository->findById( $dto->programId );
		if ( ! $program ) {
			throw NotFoundException::entity( esc_html__( 'Program', 'allcoach' ), absint( $dto->programId ) );
		}

		if ( ! $program->isActive() ) {
			throw ValidationException::withErrors(
				[ 'program' => esc_html__( 'This program is not currently active.', 'allcoach' ) ]
			);
		}

		$enabledMethods = $this->settingsManager->getEnabledPaymentMethods();
		if ( $enabledMethods !== [] && ! in_array( $dto->paymentMethod, $enabledMethods, true ) ) {
			throw ValidationException::withErrors(
				[ 'payment_method' => esc_html__( 'This payment method is not enabled.', 'allcoach' ) ]
			);
		}

		if ( $this->enrollmentRepository->hasActiveEnrollment( $dto->clientId, $dto->programId ) ) {
			throw ValidationException::withErrors(
				[ 'enrollment' => esc_html__( 'Client already has an active enrollment for this program.', 'allcoach' ) ]
			);
		}

		if ( $program->hasEnrollmentLimit() ) {
			$count = $this->enrollmentRepository->countActiveByProgram( $dto->programId );
			if ( $count >= $program->getMaxEnrollments() ) {
				throw ValidationException::withErrors(
					[ 'enrollment' => esc_html__( 'This program has reached its maximum capacity.', 'allcoach' ) ]
				);
			}
		}

		$amount   = (float) $dto->amount;
		$currency = $dto->currency ?: 'USD';

		/**
		 * Wrap all database writes in a transaction so a failure mid-way
		 * (e.g. payment processing error) rolls back order + items + enrollment.
		 */
		[ $order, $enrollment ] = Transaction::run(
			function () use ( $dto, $amount, $currency ) {
				$order = new Order(
					userId: $dto->clientId,
					paymentMethod: $dto->paymentMethod,
					currency: $currency,
					subtotal: $amount,
					discount: 0.0,
					tax: 0.0,
					total: $amount,
					paymentReference: null,
					notes: $dto->notes,
					consent: $dto->consent,
				);
				if ( $dto->status === 'confirmed' ) {
					$order->markPaid();
				}
				$order = $this->orderRepository->save( $order );

				$item = new OrderItem(
					orderId: $order->getId(),
					programId: $dto->programId,
					price: $amount,
					quantity: 1,
					total: $amount,
					createdAt: $order->getCreatedAt()
				);
				$item = $this->orderItemRepository->save( $item );

				$paymentResult = $this->paymentGateway->processPaymentForOrder( $order, $item );
				if ( ! empty( $paymentResult['transaction_id'] ) ) {
					$order->setPaymentReference( $paymentResult['transaction_id'] );
					$this->orderRepository->save( $order );
				}

				$enrollment = new Enrollment(
					userId: $dto->clientId,
					programId: $dto->programId,
					orderItemId: $item->getId(),
					enrolledAt: CarbonImmutable::now(),
					expiresAt: null
				);
				$enrollment = $this->enrollmentRepository->save( $enrollment );

				return [ $order, $enrollment ];
			}
		);

		$this->logger->info(
			'Order created',
			[
				'order_id'      => $order->getId(),
				'enrollment_id' => $enrollment->getId(),
				'client_id'     => $dto->clientId,
				'program_id'    => $dto->programId,
			]
		);

		$this->events->dispatch( new OrderCreated( $order, $enrollment ) );

		return OrderView::fromOrder( $order, $enrollment, $client->getFirstName(), $program->getTitle() );
	}
}
