<?php

declare(strict_types=1);

namespace AllCoach\Domain\Order;

defined( 'ABSPATH' ) || exit;

final class OrderStatus {

	private const PENDING  = 'pending';
	private const PAID     = 'paid';
	private const FAILED   = 'failed';
	private const REFUNDED = 'refunded';

	private function __construct(
		private readonly string $value
	) {}

	public static function pending(): self {
		return new self( self::PENDING );
	}

	public static function paid(): self {
		return new self( self::PAID );
	}

	public static function failed(): self {
		return new self( self::FAILED );
	}

	public static function refunded(): self {
		return new self( self::REFUNDED );
	}

	public static function fromString( string $value ): self {
		$valid = [ self::PENDING, self::PAID, self::FAILED, self::REFUNDED ];
		if ( ! in_array( $value, $valid, true ) ) {
			throw new \InvalidArgumentException( esc_html( "Invalid order status: '{$value}'. Expected one of: " ) . implode( ', ', array_map( 'esc_html', $valid ) ) . '.' );
		}
		return new self( $value );
	}

	public function getValue(): string {
		return $this->value;
	}

	public function isPending(): bool {
		return $this->value === self::PENDING;
	}

	public function isPaid(): bool {
		return $this->value === self::PAID;
	}

	public function isFailed(): bool {
		return $this->value === self::FAILED;
	}

	public function isRefunded(): bool {
		return $this->value === self::REFUNDED;
	}
}
