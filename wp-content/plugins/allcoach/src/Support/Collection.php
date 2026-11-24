<?php

declare(strict_types=1);

namespace AllCoach\Support;

defined( 'ABSPATH' ) || exit;

/**
 * @template T
 */
class Collection implements \ArrayAccess, \Countable, \IteratorAggregate {

	/** @var array<T> */
	private array $items = [];

	/**
	 * @param array<T> $items
	 */
	public function __construct( array $items = [] ) {
		$this->items = $items;
	}

	public function map( callable $callback ): self {
		return new self( array_map( $callback, $this->items ) );
	}

	public function filter( callable $callback ): self {
		return new self( array_filter( $this->items, $callback, ARRAY_FILTER_USE_BOTH ) );
	}

	public function reduce( callable $callback, mixed $initial = null ): mixed {
		return array_reduce( $this->items, $callback, $initial );
	}

	public function first( ?callable $callback = null ): mixed {
		if ( $callback === null ) {
			return $this->items[0] ?? null;
		}

		foreach ( $this->items as $item ) {
			if ( $callback( $item ) ) {
				return $item;
			}
		}

		return null;
	}

	public function last( ?callable $callback = null ): mixed {
		if ( $callback === null ) {
			return end( $this->items ) ?: null;
		}

		return $this->filter( $callback )->last();
	}

	public function isEmpty(): bool {
		return empty( $this->items );
	}

	public function isNotEmpty(): bool {
		return ! $this->isEmpty();
	}

	public function count(): int {
		return count( $this->items );
	}

	public function all(): array {
		return $this->items;
	}

	public function toArray(): array {
		return array_map(
			function ( $item ) {
				return method_exists( $item, 'toArray' ) ? $item->toArray() : $item;
			},
			$this->items
		);
	}

	public function pluck( string $key ): array {
		return array_map( fn( $item ) => is_object( $item ) ? $item->$key : $item[ $key ], $this->items );
	}

	public function groupBy( string $key ): array {
		$result = [];

		foreach ( $this->items as $item ) {
			$groupKey              = is_object( $item ) ? $item->$key : $item[ $key ];
			$result[ $groupKey ][] = $item;
		}

		return $result;
	}

	public function sort( ?callable $callback = null ): self {
		$items = $this->items;

		if ( $callback ) {
			usort( $items, $callback );
		} else {
			sort( $items );
		}

		return new self( $items );
	}

	public function chunk( int $size ): array {
		return array_chunk( $this->items, $size );
	}

	public function getIterator(): \ArrayIterator {
		return new \ArrayIterator( $this->items );
	}

	public function offsetExists( mixed $offset ): bool {
		return isset( $this->items[ $offset ] );
	}

	public function offsetGet( mixed $offset ): mixed {
		return $this->items[ $offset ] ?? null;
	}

	public function offsetSet( mixed $offset, mixed $value ): void {
		if ( $offset === null ) {
			$this->items[] = $value;
		} else {
			$this->items[ $offset ] = $value;
		}
	}

	public function offsetUnset( mixed $offset ): void {
		unset( $this->items[ $offset ] );
	}

	public static function make( array $items = [] ): self {
		return new self( $items );
	}
}
