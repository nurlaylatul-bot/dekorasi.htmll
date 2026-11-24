<?php

declare(strict_types=1);

namespace AllCoach\Domain\Client;

defined( 'ABSPATH' ) || exit;

class ClientStatus {

	private const ACTIVE    = 'active';
	private const INACTIVE  = 'inactive';
	private const SUSPENDED = 'suspended';

	private const VALID_STATUSES = [
		self::ACTIVE,
		self::INACTIVE,
		self::SUSPENDED,
	];

	private string $value;

	private function __construct( string $value ) {
		if ( ! in_array( $value, self::VALID_STATUSES, true ) ) {
			throw new \InvalidArgumentException(
				sprintf(
					/* translators: %s: Client status */
					esc_html__( 'Invalid client status: %s', 'allcoach' ),
					esc_html( $value )
				)
			);
		}

		$this->value = $value;
	}

	public static function active(): self {
		return new self( self::ACTIVE );
	}

	public static function inactive(): self {
		return new self( self::INACTIVE );
	}

	public static function suspended(): self {
		return new self( self::SUSPENDED );
	}

	public static function fromString( string $value ): self {
		return new self( $value );
	}

	public function getValue(): string {
		return $this->value;
	}

	public function isActive(): bool {
		return $this->value === self::ACTIVE;
	}

	public function isInactive(): bool {
		return $this->value === self::INACTIVE;
	}

	public function isSuspended(): bool {
		return $this->value === self::SUSPENDED;
	}

	public function equals( ClientStatus $other ): bool {
		return $this->value === $other->getValue();
	}

	public static function all(): array {
		return self::VALID_STATUSES;
	}

	public function __toString(): string {
		return $this->value;
	}
}
