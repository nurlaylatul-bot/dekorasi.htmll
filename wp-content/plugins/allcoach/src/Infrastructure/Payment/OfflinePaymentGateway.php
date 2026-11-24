<?php

declare(strict_types=1);

namespace AllCoach\Infrastructure\Payment;

defined( 'ABSPATH' ) || exit;

use AllCoach\Domain\Booking\PaymentGateway;
use AllCoach\Domain\Order\Order;
use AllCoach\Domain\Order\OrderItem;

class OfflinePaymentGateway implements PaymentGateway {

	public function getId(): string {
		return 'offline';
	}

	public function getName(): string {
		return __( 'Offline Payment', 'allcoach' );
	}

	public function isEnabled(): bool {
		return true;
	}

	public function processPaymentForOrder( Order $order, OrderItem $orderItem ): array {
		$transactionId = sprintf( 'OFF-%d-%s', $order->getId(), wp_generate_password( 8, false ) );

		return [
			'success'        => true,
			'transaction_id' => $transactionId,
			'message'        => __( 'Offline payment recorded. Awaiting manual confirmation.', 'allcoach' ),
		];
	}
}
