<?php

declare(strict_types=1);

namespace AllCoach\Application\Auth;

defined( 'ABSPATH' ) || exit;

class LostPasswordDTO {

	public function __construct(
		public readonly string $login,
		public readonly string $resetPageUrl,
	) {}
}
