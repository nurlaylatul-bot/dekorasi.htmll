<?php

declare(strict_types=1);

namespace AllCoach\Application\Auth;

defined( 'ABSPATH' ) || exit;

class LoginDTO {

	public function __construct(
		public readonly string $login,
		public readonly string $password,
		public readonly ?string $role = null,
		public readonly bool $remember = false,
	) {}

	public static function fromArray( array $data ): self {
		return new self(
			login: (string) ( $data['login'] ?? ( $data['email'] ?? '' ) ),
			password: (string) ( $data['password'] ?? '' ),
			role: isset( $data['role'] ) ? (string) $data['role'] : null,
			remember: ! empty( $data['rememberme'] ),
		);
	}
}
