<?php

declare(strict_types=1);

namespace AllCoach\Domain\Program;

defined( 'ABSPATH' ) || exit;

class ProgramDuration {

	private const UNIT_DAYS   = 'days';
	private const UNIT_WEEKS  = 'weeks';
	private const UNIT_MONTHS = 'months';
	private const UNIT_YEARS  = 'years';

	private const VALID_UNITS = [
		self::UNIT_DAYS,
		self::UNIT_WEEKS,
		self::UNIT_MONTHS,
		self::UNIT_YEARS,
	];

	private int $value;
	private string $unit;

	public function __construct( int $value, string $unit ) {
		if ( $value <= 0 ) {
			throw new \InvalidArgumentException( 'Duration value must be positive' );
		}

		if ( ! in_array( $unit, self::VALID_UNITS, true ) ) {
			throw new \InvalidArgumentException( esc_html( "Invalid duration unit: {$unit}" ) );
		}

		$this->value = $value;
		$this->unit  = $unit;
	}

	public function getValue(): int {
		return $this->value;
	}

	public function getUnit(): string {
		return $this->unit;
	}

	public function toDays(): int {
		return match ( $this->unit ) {
			self::UNIT_DAYS => $this->value,
			self::UNIT_WEEKS => $this->value * 7,
			self::UNIT_MONTHS => $this->value * 30,
			self::UNIT_YEARS => $this->value * 365,
		};
	}

	public function toWeeks(): int {
		return (int) ceil( $this->toDays() / 7 );
	}

	public function toMonths(): int {
		return (int) ceil( $this->toDays() / 30 );
	}

	public function equals( ProgramDuration $other ): bool {
		return $this->toDays() === $other->toDays();
	}

	public function longerThan( ProgramDuration $other ): bool {
		return $this->toDays() > $other->toDays();
	}

	public function shorterThan( ProgramDuration $other ): bool {
		return $this->toDays() < $other->toDays();
	}

	public static function days( int $value ): self {
		return new self( $value, self::UNIT_DAYS );
	}

	public static function weeks( int $value ): self {
		return new self( $value, self::UNIT_WEEKS );
	}

	public static function months( int $value ): self {
		return new self( $value, self::UNIT_MONTHS );
	}

	public static function years( int $value ): self {
		return new self( $value, self::UNIT_YEARS );
	}

	public static function validUnits(): array {
		return self::VALID_UNITS;
	}

	public function toArray(): array {
		return [
			'value' => $this->value,
			'unit'  => $this->unit,
		];
	}

	public function __toString(): string {
		return "{$this->value} {$this->unit}";
	}
}
