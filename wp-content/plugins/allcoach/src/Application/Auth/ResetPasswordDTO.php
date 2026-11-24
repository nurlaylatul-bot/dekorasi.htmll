<?php

declare(strict_types=1);

namespace AllCoach\Application\Auth;

defined( 'ABSPATH' ) || exit;

class ResetPasswordDTO {

	public function __construct(
		public readonly string $password,
		public readonly string $confirmPassword,
	) {}
}
