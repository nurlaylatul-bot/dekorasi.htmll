<?php

declare(strict_types=1);

namespace AllCoach\Domain\Booking;

defined( 'ABSPATH' ) || exit;

class SessionBookingStatus {

	private const SCHEDULED = 'scheduled';
	private const COMPLETED = 'completed';
	private const CANCELLED = 'cancelled';
	private const NO_SHOW   = 'no_show';

	private const VALID_STATUSES = [
		self::SCHEDULED,
		self::COMPLETED,
		self::CANCELLED,
		self::NO_SHOW,
	];

	private string $value;

	private function __construct( string $value ) {
		if ( ! in_array( $value, self::VALID_STATUSES, true ) ) {
			throw new \InvalidArgumentException(
				sprintf( 'Invalid session booking status: %s', esc_html( $value ) )
			);
		}
		$this->value = $value;
	}

	public static function scheduled(): self {
		return new self( self::SCHEDULED );
	}

	public static function completed(): self {
		return new self( self::COMPLETED );
	}

	public static function cancelled(): self {
		return new self( self::CANCELLED );
	}

	public static function noShow(): self {
		return new self( self::NO_SHOW );
	}

	public static function fromString( string $value ): self {
		return new self( $value );
	}

	public function getValue(): string {
		return $this->value;
	}

	public function isScheduled(): bool {
		return $this->value === self::SCHEDULED;
	}

	public function isCompleted(): bool {
		return $this->value === self::COMPLETED;
	}

	public function isCancelled(): bool {
		return $this->value === self::CANCELLED;
	}

	public function isNoShow(): bool {
		return $this->value === self::NO_SHOW;
	}

	public function equals( SessionBookingStatus $other ): bool {
		return $this->value === $other->value;
	}

	public static function all(): array {
		return self::VALID_STATUSES;
	}

	public function __toString(): string {
		return $this->value;
	}
}
