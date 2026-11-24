<?php

declare(strict_types=1);

namespace AllCoach;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\Constants;

class SDK {

	public function __construct() {
		add_filter( 'themegrill_sdk_products', [ $this, 'addProduct' ] );
	}

	public function addProduct( array $products ): array {
		$products[] = Constants::pluginFile();
		return $products;
	}
}
