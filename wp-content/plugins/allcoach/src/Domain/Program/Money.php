<?php

declare(strict_types=1);

namespace AllCoach\Domain\Program;

defined( 'ABSPATH' ) || exit;

class Money {

	private float $amount;
	private string $currency;

	public function __construct( float $amount, string $currency = 'USD' ) {
		if ( $amount < 0 ) {
			throw new \InvalidArgumentException( 'Amount cannot be negative' );
		}

		$currency = strtoupper( $currency );
		if ( ! preg_match( '/^[A-Z]{3}$/', $currency ) ) {
			throw new \InvalidArgumentException( esc_html( "Invalid currency code: {$currency}" ) );
		}

		$this->amount   = round( $amount, 2 );
		$this->currency = $currency;
	}

	public function getAmount(): float {
		return $this->amount;
	}

	public function getCurrency(): string {
		return $this->currency;
	}

	public function add( Money $other ): self {
		$this->assertSameCurrency( $other );
		return new self( $this->amount + $other->getAmount(), $this->currency );
	}

	public function subtract( Money $other ): self {
		$this->assertSameCurrency( $other );
		return new self( $this->amount - $other->getAmount(), $this->currency );
	}

	public function multiply( float $factor ): self {
		return new self( $this->amount * $factor, $this->currency );
	}

	public function isZero(): bool {
		return $this->amount === 0.0;
	}

	public function isPositive(): bool {
		return $this->amount > 0;
	}

	public function equals( Money $other ): bool {
		return $this->amount === $other->getAmount() && $this->currency === $other->getCurrency();
	}

	public function greaterThan( Money $other ): bool {
		$this->assertSameCurrency( $other );
		return $this->amount > $other->getAmount();
	}

	public function lessThan( Money $other ): bool {
		$this->assertSameCurrency( $other );
		return $this->amount < $other->getAmount();
	}

	private function assertSameCurrency( Money $other ): void {
		if ( $this->currency !== $other->getCurrency() ) {
			throw new \InvalidArgumentException( 'Cannot operate on different currencies' );
		}
	}

	public static function zero( string $currency = 'USD' ): self {
		return new self( 0, $currency );
	}

	public function toArray(): array {
		return [
			'amount'   => $this->amount,
			'currency' => $this->currency,
		];
	}

	public function __toString(): string {
		return sprintf( '%s %.2f', $this->currency, $this->amount );
	}
}
