<?php

declare(strict_types=1);

namespace AllCoach\Domain\Cart;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\Exceptions\ValidationException;
use AllCoach\Support\Arrayable;

final class CartItem implements Arrayable {

	private int $programId;
	private string $name;
	private int $quantity;
	private float $price;
	private float $tax;

	public function __construct( array $data ) {
		$this->programId = (int) ( $data['program_id'] ?? 0 );
		$this->name      = (string) ( $data['name'] ?? '' );
		$this->quantity  = (int) ( $data['quantity'] ?? 1 );
		$this->price     = (float) ( $data['price'] ?? 0.00 );
		$this->tax       = (float) ( $data['tax'] ?? 0.00 );

		$this->validate();
	}

	public function getId(): string {
		return hash( 'sha256', (string) $this->programId );
	}

	public function getProgramId(): int {
		return $this->programId;
	}

	public function getName(): string {
		return $this->name;
	}

	public function getQuantity(): int {
		return $this->quantity;
	}

	public function setQuantity( int $quantity ): void {
		if ( $quantity < 0 ) {
			throw new ValidationException(
				esc_html__( 'Quantity must be non-negative', 'allcoach' ),
			);
		}
		$this->quantity = $quantity;
	}

	public function getPrice(): float {
		return $this->price;
	}

	public function getTax(): float {
		return $this->tax;
	}

	public function getTotalPrice(): float {
		return ( $this->price + $this->tax ) * $this->quantity;
	}

	public function getTotalPriceExcludingTax(): float {
		return $this->price * $this->quantity;
	}

	public function getTotalTax(): float {
		return $this->tax * $this->quantity;
	}

	public function toArray(): array {
		return [
			'id'   => $this->getId(),
			'data' => [
				'program_id' => $this->programId,
				'name'       => $this->name,
				'quantity'   => $this->quantity,
				'price'      => $this->price,
				'tax'        => $this->tax,
			],
		];
	}

	private function validate(): void {
		if ( $this->programId <= 0 ) {
			throw new ValidationException( 'Cart item must reference a program' );
		}

		if ( $this->quantity < 0 ) {
			throw new ValidationException( 'Quantity must be non-negative' );
		}

		if ( $this->price < 0 ) {
			throw new ValidationException( 'Price must be non-negative' );
		}

		if ( $this->tax < 0 ) {
			throw new ValidationException( 'Tax must be non-negative' );
		}
	}
}
