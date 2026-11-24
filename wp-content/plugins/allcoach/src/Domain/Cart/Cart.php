<?php

declare(strict_types=1);

namespace AllCoach\Domain\Cart;

defined( 'ABSPATH' ) || exit;

use AllCoach\Support\Arrayable;

final class Cart implements Arrayable {

	/** @var array<string, CartItem> */
	private array $items = [];

	private bool $isDirty = false;

	public function __construct(
		private readonly string $id,
		private readonly Store $store
	) {
		$this->restore();
	}

	public function getId(): string {
		return $this->id;
	}

	/**
	 * @return CartItem[]
	 */
	public function all(): array {
		return array_values( $this->items );
	}

	public function add( CartItem $item ): void {
		$itemId = $item->getId();

		if ( isset( $this->items[ $itemId ] ) ) {
			$existing = $this->items[ $itemId ];
			$existing->setQuantity( $existing->getQuantity() + $item->getQuantity() );
		} else {
			$this->items[ $itemId ] = $item;
		}

		$this->isDirty = true;
	}

	public function remove( string $itemId ): void {
		if ( isset( $this->items[ $itemId ] ) ) {
			unset( $this->items[ $itemId ] );
			$this->isDirty = true;
		}
	}

	public function find( string $itemId ): ?CartItem {
		return $this->items[ $itemId ] ?? null;
	}

	public function has( string $itemId ): bool {
		return isset( $this->items[ $itemId ] );
	}

	public function hasProgramId( int $programId ): bool {
		foreach ( $this->items as $item ) {
			if ( $item->getProgramId() === $programId ) {
				return true;
			}
		}
		return false;
	}

	public function isEmpty(): bool {
		return empty( $this->items );
	}

	public function count(): int {
		return array_sum(
			array_map( fn( CartItem $item ) => $item->getQuantity(), $this->items )
		);
	}

	public function total(): float {
		return array_sum(
			array_map( fn( CartItem $item ) => $item->getTotalPrice(), $this->items )
		);
	}

	public function subtotal(): float {
		return array_sum(
			array_map( fn( CartItem $item ) => $item->getTotalPriceExcludingTax(), $this->items )
		);
	}

	public function tax(): float {
		return array_sum(
			array_map( fn( CartItem $item ) => $item->getTotalTax(), $this->items )
		);
	}

	public function save(): void {
		if ( ! $this->isDirty ) {
			return;
		}

		$this->store->put( $this->id, maybe_serialize( $this->toArray() ) );
		$this->isDirty = false;
	}

	public function clear(): void {
		$this->items = [];
		$this->store->flush( $this->id );
		$this->isDirty = false;
	}

	public function isDirty(): bool {
		return $this->isDirty;
	}

	public function toArray(): array {
		return [
			'id'    => $this->id,
			'items' => array_map( fn( CartItem $item ) => $item->toArray(), $this->items ),
		];
	}

	private function restore(): void {
		try {
			$state = $this->store->get( $this->id );

			if ( $state === '' ) {
				return;
			}

			$data = maybe_unserialize( $state );

			if ( ! is_array( $data ) || ( $data['id'] ?? null ) !== $this->id ) {
				return;
			}

			if ( ! isset( $data['items'] ) || ! is_array( $data['items'] ) ) {
				return;
			}

			foreach ( $data['items'] as $itemData ) {
				if ( ! is_array( $itemData ) || ! isset( $itemData['data'] ) ) {
					continue;
				}

				try {
					$item                          = new CartItem( $itemData['data'] );
					$this->items[ $item->getId() ] = $item;
				} catch ( \Throwable ) {
					continue;
				}
			}
		} catch ( \Throwable ) {
			$this->items = [];
		}

		$this->isDirty = false;
	}
}
