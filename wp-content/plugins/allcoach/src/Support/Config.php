<?php

declare(strict_types=1);

namespace AllCoach\Support;

defined( 'ABSPATH' ) || exit;

class Config {

	private array $config = [];

	public function __construct( array $config = [] ) {
		$this->config = $config;
	}

	public function get( string $key, mixed $defaultValue = null ): mixed {
		$keys  = explode( '.', $key );
		$value = $this->config;

		foreach ( $keys as $segment ) {
			if ( ! is_array( $value ) || ! isset( $value[ $segment ] ) ) {
				return $defaultValue;
			}
			$value = $value[ $segment ];
		}

		return $value;
	}

	public function set( string $key, mixed $value ): void {
		$keys   = explode( '.', $key );
		$config = &$this->config;

		foreach ( $keys as $segment ) {
			if ( ! isset( $config[ $segment ] ) || ! is_array( $config[ $segment ] ) ) {
				$config[ $segment ] = [];
			}
			$config = &$config[ $segment ];
		}

		$config = $value;
	}

	public function has( string $key ): bool {
		$keys  = explode( '.', $key );
		$value = $this->config;

		foreach ( $keys as $segment ) {
			if ( ! is_array( $value ) || ! isset( $value[ $segment ] ) ) {
				return false;
			}
			$value = $value[ $segment ];
		}

		return true;
	}

	public function all(): array {
		return $this->config;
	}
}
