<?php

declare(strict_types=1);

namespace AllCoach\Domain\Enrollment;

defined( 'ABSPATH' ) || exit;

final class EnrollmentStatus {

	private const ACTIVE    = 'active';
	private const CANCELLED = 'cancelled';
	private const EXPIRED   = 'expired';
	private const COMPLETED = 'completed';

	private function __construct(
		private readonly string $value
	) {}

	public static function active(): self {
		return new self( self::ACTIVE );
	}

	public static function cancelled(): self {
		return new self( self::CANCELLED );
	}

	public static function expired(): self {
		return new self( self::EXPIRED );
	}

	public static function completed(): self {
		return new self( self::COMPLETED );
	}

	public static function fromString( string $value ): self {
		$valid = [ self::ACTIVE, self::CANCELLED, self::EXPIRED, self::COMPLETED ];
		if ( ! in_array( $value, $valid, true ) ) {
			throw new \InvalidArgumentException(
				esc_html( "Invalid enrollment status: '{$value}'. Expected one of: " ) . implode( ', ', array_map( 'esc_html', $valid ) ) . '.'
			);
		}
		return new self( $value );
	}

	public function getValue(): string {
		return $this->value;
	}

	public function isActive(): bool {
		return $this->value === self::ACTIVE;
	}

	public function isCancelled(): bool {
		return $this->value === self::CANCELLED;
	}

	public function isExpired(): bool {
		return $this->value === self::EXPIRED;
	}

	public function isCompleted(): bool {
		return $this->value === self::COMPLETED;
	}
}
