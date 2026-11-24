<?php

declare(strict_types=1);

namespace AllCoach\Application\Auth;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\Exceptions\ValidationException;

class ResetPasswordService {

	/**
	 * @return \WP_User|\WP_Error The user the key belongs to, or a WP_Error if the key is invalid/expired.
	 */
	public function validateKey( string $login, string $key ): \WP_User|\WP_Error {
		return check_password_reset_key( $key, $login );
	}

	public function execute( \WP_User $user, ResetPasswordDTO $dto ): void {
		if ( $dto->password === '' || $dto->confirmPassword === '' ) {
			throw ValidationException::withErrors(
				[ 'password' => esc_html__( 'Please enter and confirm your new password.', 'allcoach' ) ]
			);
		}

		if ( $dto->password !== $dto->confirmPassword ) {
			throw ValidationException::withErrors(
				[ 'password' => esc_html__( 'Passwords do not match.', 'allcoach' ) ]
			);
		}

		if ( strlen( $dto->password ) < 8 ) {
			throw ValidationException::withErrors(
				[ 'password' => esc_html__( 'Password must be at least 8 characters.', 'allcoach' ) ]
			);
		}

		reset_password( $user, $dto->password );
	}
}
