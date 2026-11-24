<?php

declare(strict_types=1);

namespace AllCoach\Domain\Booking;

defined( 'ABSPATH' ) || exit;

use AllCoach\Domain\Order\Order;
use AllCoach\Domain\Order\OrderItem;

interface PaymentGateway {

	public function getId(): string;

	public function getName(): string;

	public function isEnabled(): bool;

	/**
	 * @return array{success: bool, transaction_id: string|null, message: string}
	 */
	public function processPaymentForOrder( Order $order, OrderItem $orderItem ): array;
}
