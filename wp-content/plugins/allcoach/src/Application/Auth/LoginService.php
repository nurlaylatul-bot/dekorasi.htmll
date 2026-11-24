<?php

declare(strict_types=1);

namespace AllCoach\Application\Auth;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\Exceptions\ValidationException;
use AllCoach\Core\RoleManager;

class LoginService {

	public function execute( LoginDTO $dto ): \WP_User {
		if ( $dto->login === '' || $dto->password === '' ) {
			throw ValidationException::withErrors(
				[ 'login' => esc_html__( 'Login and password are required.', 'allcoach' ) ]
			);
		}

		// Allow login by email or username.
		$user      = get_user_by( 'email', $dto->login ) ?: get_user_by( 'login', $dto->login );
		$userLogin = $user ? $user->user_login : $dto->login;

		$result = wp_signon(
			[
				'user_login'    => $userLogin,
				'user_password' => $dto->password,
				'remember'      => $dto->remember,
			],
			true
		);

		if ( is_wp_error( $result ) ) {
			throw ValidationException::withErrors(
				[ 'login' => wp_kses_post( $result->get_error_message() ) ]
			);
		}

		if ( $dto->role !== null ) {
			$requiredRole = $dto->role === 'coach'
				? RoleManager::ROLE_COACH
				: ( $dto->role === 'client' ? RoleManager::ROLE_CLIENT : null );

			if ( $requiredRole !== null && ! in_array( $requiredRole, (array) $result->roles, true ) ) {
				wp_logout();
				throw ValidationException::withErrors(
					[ 'role' => esc_html__( 'You do not have access to this area.', 'allcoach' ) ]
				);
			}
		}

		return $result;
	}
}
