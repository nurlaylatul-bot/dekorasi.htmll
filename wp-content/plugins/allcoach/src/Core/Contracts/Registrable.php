<?php

declare(strict_types=1);

namespace AllCoach\Core\Contracts;

defined( 'ABSPATH' ) || exit;

interface Registrable {
	public function register(): void;
}
