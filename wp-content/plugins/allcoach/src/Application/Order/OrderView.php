<?php

declare(strict_types=1);

namespace AllCoach\Application\Order;

defined( 'ABSPATH' ) || exit;

use AllCoach\Domain\Enrollment\Enrollment;
use AllCoach\Domain\Order\Order;
use AllCoach\Domain\Order\OrderRow;
use AllCoach\Domain\Order\OrderStatus;

final class OrderView {

	public function __construct(
		public readonly int $id,
		public readonly int $enrollmentId,
		public readonly int $clientId,
		public readonly int $programId,
		public readonly string $status,
		public readonly string $paymentMethod,
		public readonly string $paymentStatus,
		public readonly float $amount,
		public readonly string $currency,
		public readonly ?string $transactionId,
		public readonly ?string $paidAt,
		public readonly string $createdAt,
		public readonly ?string $updatedAt,
		public readonly ?string $notes = null,
		public readonly ?string $clientName = null,
		public readonly ?string $programTitle = null,
	) {}

	public static function fromRow( OrderRow $row ): self {
		$orderStatus   = OrderStatus::fromString( $row->orderStatus );
		$paymentStatus = self::resolvePaymentStatus(
			$orderStatus,
			$row->paymentMethod,
			$row->paymentReference
		);

		if ( $orderStatus->isPaid() ) {
			$status = 'confirmed';
		} elseif ( $orderStatus->isRefunded() || $orderStatus->isFailed() ) {
			$status = 'cancelled';
		} else {
			$status = 'pending';
		}

		return new self(
			id: $row->orderId,
			enrollmentId: $row->enrollmentId,
			clientId: $row->clientId,
			programId: $row->programId,
			status: $status,
			paymentMethod: $row->paymentMethod,
			paymentStatus: $paymentStatus,
			amount: $row->total,
			currency: $row->currency,
			transactionId: self::displayPaymentReference( $row->paymentReference ),
			paidAt: $orderStatus->isPaid() ? $row->updatedAt : null,
			createdAt: $row->createdAt,
			updatedAt: $row->updatedAt,
			notes: $row->notes,
			clientName: $row->clientName,
			programTitle: $row->programTitle,
		);
	}

	public static function fromOrder( Order $order, Enrollment $enrollment, ?string $clientName = null, ?string $programTitle = null ): self {
		$orderStatus   = $order->getStatus();
		$paymentStatus = self::resolvePaymentStatus(
			$orderStatus,
			$order->getPaymentMethod(),
			$order->getPaymentReference()
		);

		if ( $orderStatus->isPaid() ) {
			$status = 'confirmed';
		} elseif ( $orderStatus->isRefunded() || $orderStatus->isFailed() ) {
			$status = 'cancelled';
		} else {
			$status = 'pending';
		}

		return new self(
			id: $order->getId(),
			enrollmentId: $enrollment->getId(),
			clientId: $enrollment->getUserId(),
			programId: $enrollment->getProgramId(),
			status: $status,
			paymentMethod: $order->getPaymentMethod(),
			paymentStatus: $paymentStatus,
			amount: $order->getTotal(),
			currency: $order->getCurrency(),
			transactionId: self::displayPaymentReference( $order->getPaymentReference() ),
			paidAt: $orderStatus->isPaid() ? $order->getUpdatedAt()?->format( 'Y-m-d H:i:s' ) : null,
			createdAt: $order->getCreatedAt()->format( 'Y-m-d H:i:s' ),
			updatedAt: $order->getUpdatedAt()?->format( 'Y-m-d H:i:s' ),
			notes: $order->getNotes(),
			clientName: $clientName,
			programTitle: $programTitle,
		);
	}

	public function toArray(): array {
		return [
			'id'             => $this->id,
			'enrollment_id'  => $this->enrollmentId,
			'client_id'      => $this->clientId,
			'program_id'     => $this->programId,
			'status'         => $this->status,
			'payment_method' => $this->paymentMethod,
			'payment_status' => $this->paymentStatus,
			'amount'         => $this->amount,
			'currency'       => $this->currency,
			'transaction_id' => $this->transactionId,
			'notes'          => $this->notes,
			'paid_at'        => $this->paidAt,
			'created_at'     => $this->createdAt,
			'updated_at'     => $this->updatedAt,
		];
	}

	private static function resolvePaymentStatus( OrderStatus $orderStatus, string $paymentMethod, ?string $paymentReference ): string {
		if ( $orderStatus->isPaid() ) {
			return 'paid';
		}

		if ( $orderStatus->isFailed() ) {
			return 'failed';
		}

		if ( $paymentMethod === 'paypal' && str_starts_with( (string) $paymentReference, 'AUTH:' ) ) {
			return 'authorized';
		}

		return 'pending';
	}

	private static function displayPaymentReference( ?string $paymentReference ): ?string {
		if ( $paymentReference === null ) {
			return null;
		}

		return str_starts_with( $paymentReference, 'AUTH:' )
			? substr( $paymentReference, 5 )
			: $paymentReference;
	}
}
