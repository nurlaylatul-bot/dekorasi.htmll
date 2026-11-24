<?php

declare(strict_types=1);

namespace AllCoach\Domain\Cart;

defined( 'ABSPATH' ) || exit;

interface Store {

	public function get( string $cartId ): string;

	public function put( string $cartId, string $data ): void;

	public function flush( string $cartId ): void;

	public function exists( string $cartId ): bool;
}
