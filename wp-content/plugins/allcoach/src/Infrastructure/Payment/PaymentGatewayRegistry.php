<?php

declare(strict_types=1);

namespace AllCoach\Infrastructure\Payment;

defined( 'ABSPATH' ) || exit;

use AllCoach\Domain\Booking\PaymentGateway;
use AllCoach\Domain\Order\Order;
use AllCoach\Domain\Order\OrderItem;

class PaymentGatewayRegistry implements PaymentGateway {

	/** @param PaymentGateway[] $gateways */
	public function __construct(
		private readonly array $gateways,
	) {}

	public function getId(): string {
		return 'registry';
	}

	public function getName(): string {
		return 'Payment Gateway Registry';
	}

	public function isEnabled(): bool {
		return true;
	}

	/**
	 * @return array{success: bool, transaction_id: string|null, message: string}
	 */
	public function processPaymentForOrder( Order $order, OrderItem $orderItem ): array {
		$gateway = $this->resolve( $order->getPaymentMethod() );

		if ( $gateway === null ) {
			return [
				'success'        => false,
				'transaction_id' => null,
				'message'        => sprintf(
					/* translators: %s: payment method ID */
					__( 'No payment gateway found for method: %s', 'allcoach' ),
					esc_html( $order->getPaymentMethod() )
				),
			];
		}

		return $gateway->processPaymentForOrder( $order, $orderItem );
	}

	public function resolve( string $methodId ): ?PaymentGateway {
		foreach ( $this->gateways as $gateway ) {
			if ( $gateway->getId() === $methodId ) {
				return $gateway;
			}
		}
		return null;
	}

	/** @return PaymentGateway[] */
	public function all(): array {
		return $this->gateways;
	}
}
