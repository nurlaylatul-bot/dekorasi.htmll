<?php

declare(strict_types=1);

namespace AllCoach\Domain\Booking;

defined( 'ABSPATH' ) || exit;

class PaymentMethod {

	private const OFFLINE = 'offline';
	private const PAYPAL  = 'paypal';

	private const VALID_METHODS = [
		self::OFFLINE,
		self::PAYPAL,
	];

	private string $value;

	private function __construct( string $value ) {
		if ( ! in_array( $value, self::VALID_METHODS, true ) ) {
			throw new \InvalidArgumentException(
				sprintf( 'Invalid payment method: %s', esc_html( $value ) )
			);
		}
		$this->value = $value;
	}

	public static function offline(): self {
		return new self( self::OFFLINE );
	}

	public static function paypal(): self {
		return new self( self::PAYPAL );
	}

	public static function fromString( string $value ): self {
		return new self( $value );
	}

	public function getValue(): string {
		return $this->value;
	}

	public function isOffline(): bool {
		return $this->value === self::OFFLINE;
	}

	public function isPaypal(): bool {
		return $this->value === self::PAYPAL;
	}

	public function equals( PaymentMethod $other ): bool {
		return $this->value === $other->value;
	}

	public static function all(): array {
		return self::VALID_METHODS;
	}

	public function __toString(): string {
		return $this->value;
	}
}
