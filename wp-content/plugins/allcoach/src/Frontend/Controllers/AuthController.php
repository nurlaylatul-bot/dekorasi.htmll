<?php

declare(strict_types=1);

namespace AllCoach\Frontend\Controllers;

defined( 'ABSPATH' ) || exit;

use AllCoach\Application\Auth\LoginService;
use AllCoach\Application\Auth\LoginDTO;
use AllCoach\Application\Auth\LostPasswordService;
use AllCoach\Application\Auth\LostPasswordDTO;
use AllCoach\Application\Auth\ResetPasswordService;
use AllCoach\Application\Auth\ResetPasswordDTO;
use AllCoach\Frontend\Router;
use AllCoach\Support\TemplateLoader;

class AuthController extends BaseController {

	public function __construct(
		TemplateLoader $templateLoader,
		private readonly LoginService $loginService,
		private readonly LostPasswordService $lostPasswordService,
		private readonly ResetPasswordService $resetPasswordService,
	) {
		parent::__construct( $templateLoader );
	}

	public function login(): void {
		if ( is_user_logged_in() ) {
			$this->redirect( Router::url() );
		}

		$error = '';

		if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
			$error = $this->handleLogin();
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only flag, no state change.
		$passwordReset = isset( $_GET['password-reset'] ) && 'success' === $_GET['password-reset'];

		$this->render(
			'auth/login.php',
			[
				'pageTitle'     => __( 'Login', 'allcoach' ),
				'error'         => $error,
				'passwordReset' => $passwordReset,
			]
		);
	}

	private function handleLogin(): string {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_allcoach_nonce'] ?? '' ) ), 'allcoach_login' ) ) {
			return __( 'Security check failed. Please try again.', 'allcoach' );
		}

		$login    = sanitize_text_field( wp_unslash( $_POST['login'] ?? '' ) );
		$password = wp_unslash( $_POST['password'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- password must not be sanitized

		if ( empty( $login ) || empty( $password ) ) {
			return __( 'Please enter your email and password.', 'allcoach' );
		}

		$rememberme = ! empty( $_POST['rememberme'] );

		try {
			$dto = new LoginDTO(
				login: $login,
				password: $password,
				remember: $rememberme,
			);

			$this->loginService->execute( $dto );

			$redirect = sanitize_url( wp_unslash( $_GET['redirect_to'] ?? '' ) );
			$this->redirect( $redirect ?: Router::url() );
		} catch ( \AllCoach\Core\Exceptions\ValidationException $e ) {
			return $e->getErrors()['login'] ?? $e->getMessage();
		}

		return '';
	}

	public function lostPassword(): void {
		if ( is_user_logged_in() ) {
			$this->redirect( Router::url() );
		}

		$error = '';
		$sent  = false;

		if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
			$error = $this->handleLostPassword();
			$sent  = ( '' === $error );
		}

		$this->render(
			'auth/lostpassword.php',
			[
				'pageTitle' => __( 'Reset your password', 'allcoach' ),
				'error'     => $error,
				'sent'      => $sent,
			]
		);
	}

	private function handleLostPassword(): string {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_allcoach_nonce'] ?? '' ) ), 'allcoach_lostpassword' ) ) {
			return __( 'Security check failed. Please try again.', 'allcoach' );
		}

		$login = sanitize_text_field( wp_unslash( $_POST['login'] ?? '' ) );

		try {
			$dto = new LostPasswordDTO(
				login: $login,
				resetPageUrl: get_permalink() ?: home_url( '/' ),
			);

			$this->lostPasswordService->execute( $dto );
		} catch ( \AllCoach\Core\Exceptions\ValidationException $e ) {
			return $e->getErrors()['login'] ?? $e->getMessage();
		}

		return '';
	}

	public function resetPassword(): void {
		if ( is_user_logged_in() ) {
			$this->redirect( Router::url() );
		}

		$accountPageUrl = get_permalink() ?: home_url( '/' );

		// From the emailed link: move the key into a cookie and redirect to a clean
		// URL, so it never lingers in the address bar, history, logs or Referer.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- values are cryptographically validated below.
		if ( isset( $_GET['key'], $_GET['login'] ) ) {
			$login = sanitize_text_field( wp_unslash( $_GET['login'] ) );
			$key   = sanitize_text_field( wp_unslash( $_GET['key'] ) );

			$this->setResetCookie( $login, $key );
			$this->redirect( add_query_arg( 'allcoach_action', 'resetpass', $accountPageUrl ) );
		}

		[ $login, $key ] = $this->readResetCookie();

		$user = $this->resetPasswordService->validateKey( $login, $key );

		if ( is_wp_error( $user ) ) {
			$this->clearResetCookie();
			$this->render(
				'auth/resetpass.php',
				[
					'pageTitle' => __( 'Reset your password', 'allcoach' ),
					'error'     => $user->get_error_message(),
					'validKey'  => false,
				]
			);
			return;
		}

		$error = '';

		if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
			$error = $this->handleResetPassword( $user );

			if ( '' === $error ) {
				$this->clearResetCookie();
				$this->redirect( add_query_arg( 'password-reset', 'success', $accountPageUrl ) );
			}
		}

		$this->render(
			'auth/resetpass.php',
			[
				'pageTitle' => __( 'Reset your password', 'allcoach' ),
				'error'     => $error,
				'validKey'  => true,
			]
		);
	}

	private function handleResetPassword( \WP_User $user ): string {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_allcoach_nonce'] ?? '' ) ), 'allcoach_resetpass' ) ) {
			return __( 'Security check failed. Please try again.', 'allcoach' );
		}

		$password        = wp_unslash( $_POST['password'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- password must not be sanitized
		$confirmPassword = wp_unslash( $_POST['confirm_password'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- password must not be sanitized

		try {
			$dto = new ResetPasswordDTO(
				password: $password,
				confirmPassword: $confirmPassword,
			);

			$this->resetPasswordService->execute( $user, $dto );
		} catch ( \AllCoach\Core\Exceptions\ValidationException $e ) {
			return $e->getErrors()['password'] ?? $e->getMessage();
		}

		return '';
	}

	private function resetCookieName(): string {
		return 'allcoach_resetpass_' . COOKIEHASH;
	}

	private function resetCookiePath(): string {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
		$path = (string) current( explode( '?', (string) $uri ) );

		return sanitize_text_field( $path ) ?: '/';
	}

	private function setResetCookie( string $login, string $key ): void {
		$value = rawurlencode( $login ) . ':' . rawurlencode( $key );
		setcookie( $this->resetCookieName(), $value, 0, $this->resetCookiePath(), COOKIE_DOMAIN, is_ssl(), true );
		$_COOKIE[ $this->resetCookieName() ] = $value;
	}

	/** @return array{0: string, 1: string} [ login, key ] */
	private function readResetCookie(): array {
		$name = $this->resetCookieName();

		if ( ! isset( $_COOKIE[ $name ] ) ) {
			return [ '', '' ];
		}

		$cookie = wp_unslash( $_COOKIE[ $name ] );

		if ( ! is_string( $cookie ) || ! str_contains( $cookie, ':' ) ) {
			return [ '', '' ];
		}

		[ $login, $key ] = explode( ':', $cookie, 2 );

		return [
			sanitize_text_field( rawurldecode( $login ) ),
			sanitize_text_field( rawurldecode( $key ) ),
		];
	}

	private function clearResetCookie(): void {
		setcookie( $this->resetCookieName(), ' ', time() - YEAR_IN_SECONDS, $this->resetCookiePath(), COOKIE_DOMAIN, is_ssl(), true );
		unset( $_COOKIE[ $this->resetCookieName() ] );
	}
}
