<?php

declare(strict_types=1);

namespace AllCoach\Application\Auth;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\Exceptions\ValidationException;

class LostPasswordService {

	public function execute( LostPasswordDTO $dto ): void {
		if ( $dto->login === '' ) {
			throw ValidationException::withErrors(
				[ 'login' => esc_html__( 'Please enter your email or username.', 'allcoach' ) ]
			);
		}

		// Replace core's message with our own, composed in the recipient's locale.
		$filter = function ( string $message, string $key, string $userLogin, $userData = null ) use ( $dto ): string {
			$switched = false;
			if ( $userData instanceof \WP_User ) {
				$switched = switch_to_locale( get_user_locale( $userData ) );
			}

			$message = $this->buildResetMessage( $key, $userLogin, $dto->resetPageUrl );

			if ( $switched ) {
				restore_previous_locale();
			}

			return $message;
		};

		add_filter( 'retrieve_password_message', $filter, 10, 4 );
		$result = retrieve_password( $dto->login );
		remove_filter( 'retrieve_password_message', $filter, 10 );

		if ( is_wp_error( $result ) ) {
			throw ValidationException::withErrors(
				[ 'login' => wp_kses_post( $result->get_error_message() ) ]
			);
		}
	}

	private function buildResetMessage( string $key, string $userLogin, string $resetPageUrl ): string {
		// add_query_arg() encodes the values, so pass $userLogin raw.
		$resetUrl = add_query_arg(
			[
				'allcoach_action' => 'resetpass',
				'key'             => $key,
				'login'           => $userLogin,
			],
			$resetPageUrl
		);

		$siteName = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );

		$message  = sprintf(
			/* translators: %s: username */
			__( 'Hi %s,', 'allcoach' ),
			$userLogin
		) . "\r\n\r\n";
		$message .= sprintf(
			/* translators: %s: site name */
			__( 'We received a request to reset the password for your %s account.', 'allcoach' ),
			$siteName
		) . "\r\n\r\n";
		$message .= __( 'Click the link below to choose a new password:', 'allcoach' ) . "\r\n\r\n";
		$message .= $resetUrl . "\r\n\r\n";
		$message .= __( 'For your security, this link will expire in 24 hours.', 'allcoach' ) . "\r\n\r\n";
		$message .= __( "If you didn't request this, you can safely ignore this email — your password will remain unchanged.", 'allcoach' ) . "\r\n\r\n";
		$message .= sprintf(
			/* translators: %s: site name */
			__( '— The %s Team', 'allcoach' ),
			$siteName
		) . "\r\n";

		return $message;
	}
}
