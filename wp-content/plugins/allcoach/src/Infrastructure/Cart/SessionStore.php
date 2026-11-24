<?php

declare(strict_types=1);

namespace AllCoach\Infrastructure\Cart;

defined( 'ABSPATH' ) || exit;

use AllCoach\Domain\Cart\Store;

final class SessionStore implements Store {

	private const PREFIX = 'allcoach_cart_';

	public function get( string $cartId ): string {
		$this->ensureSession();
		$key = self::PREFIX . $cartId;

		return isset( $_SESSION[ $key ] ) && is_string( $_SESSION[ $key ] )
			? $_SESSION[ $key ] // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- internally managed JSON
			: '';
	}

	public function put( string $cartId, string $data ): void {
		$this->ensureSession();
		$_SESSION[ self::PREFIX . $cartId ] = $data;
	}

	public function flush( string $cartId ): void {
		$this->ensureSession();
		unset( $_SESSION[ self::PREFIX . $cartId ] );
	}

	public function exists( string $cartId ): bool {
		return $this->get( $cartId ) !== '';
	}

	private function ensureSession(): void {
		if ( session_status() === PHP_SESSION_NONE && ! headers_sent() ) {
			session_start();
		}
	}
}
