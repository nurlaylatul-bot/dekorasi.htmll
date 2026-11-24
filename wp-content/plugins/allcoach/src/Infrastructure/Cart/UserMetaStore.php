<?php

declare(strict_types=1);

namespace AllCoach\Infrastructure\Cart;

defined( 'ABSPATH' ) || exit;

use AllCoach\Domain\Cart\Store;

final class UserMetaStore implements Store {

	private const META_PREFIX = '_allcoach_cart_';

	public function __construct(
		private readonly int $userId
	) {}

	public function get( string $cartId ): string {
		$value = get_user_meta( $this->userId, self::META_PREFIX . $cartId, true );

		return is_string( $value ) && $value !== '' ? $value : '';
	}

	public function put( string $cartId, string $data ): void {
		update_user_meta( $this->userId, self::META_PREFIX . $cartId, $data );
	}

	public function flush( string $cartId ): void {
		delete_user_meta( $this->userId, self::META_PREFIX . $cartId );
	}

	public function exists( string $cartId ): bool {
		return $this->get( $cartId ) !== '';
	}
}
