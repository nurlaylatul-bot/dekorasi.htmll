<?php

declare(strict_types=1);

namespace AllCoach\Traits;

defined( 'ABSPATH' ) || exit;

trait Singleton {

	private static ?self $instance = null;

	public static function getInstance(): self {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __clone() {}

	public function __wakeup(): void {
		throw new \Exception( 'Cannot unserialize singleton' );
	}
}
