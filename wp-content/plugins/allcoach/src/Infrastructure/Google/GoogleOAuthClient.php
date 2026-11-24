<?php

declare(strict_types=1);

namespace AllCoach\Infrastructure\Google;

defined( 'ABSPATH' ) || exit;

use AllCoach\Support\Logger;
use Google\Client as GoogleClient;
use Google\Service\Oauth2;

class GoogleOAuthClient {

	private const SCOPES = [
		'https://www.googleapis.com/auth/calendar.events',
		'https://www.googleapis.com/auth/userinfo.email',
	];

	public function __construct(
		private readonly GoogleCredentialManager $credentialManager,
		private readonly GoogleTokenManager $tokenManager,
		private readonly Logger $logger,
	) {}

	public function getAuthUrl( string $redirectUri, string $state = '' ): string {
		$client = $this->createGoogleClient( $redirectUri, $state );
		return $client->createAuthUrl();
	}

	/**
	 * @return array{access_token: string, refresh_token: string, expires_at: int, email: string}
	 * @throws \RuntimeException On API failure.
	 */
	public function exchangeCode( string $code, string $redirectUri, int $coachId ): array {
		$client = $this->createGoogleClient( $redirectUri );

		try {
			$creds = $client->fetchAccessTokenWithAuthCode( $code );
		} catch ( \Exception $e ) {
			$this->logger->error( 'Google OAuth code exchange failed', [ 'error' => $e->getMessage() ] );
			throw new \RuntimeException( esc_html__( 'Failed to connect to Google: ', 'allcoach' ) . esc_html( $e->getMessage() ) );
		}

		if ( ! is_array( $creds ) || empty( $creds['access_token'] ) ) {
			$errorDescription = $creds['error_description'] ?? $creds['error'] ?? __( 'Invalid response from Google.', 'allcoach' );
			$this->logger->error( 'Google OAuth invalid token response', [ 'body' => $creds ] );
			throw new \RuntimeException( esc_html( (string) $errorDescription ) );
		}

		$expiresAt = ( $creds['created'] ?? time() ) + ( (int) ( $creds['expires_in'] ?? 3600 ) );
		$email     = $this->fetchUserEmail( $creds['access_token'] );

		$this->tokenManager->saveTokens(
			$coachId,
			$creds['access_token'],
			$creds['refresh_token'] ?? '',
			$expiresAt,
			$email
		);

		return [
			'access_token'  => $creds['access_token'],
			'refresh_token' => $creds['refresh_token'] ?? '',
			'expires_at'    => $expiresAt,
			'email'         => $email,
		];
	}

	/**
	 * @throws \RuntimeException
	 */
	public function refreshAccessToken( int $coachId ): string {
		$tokens = $this->tokenManager->getTokens( $coachId );
		if ( ! $tokens || empty( $tokens['refresh_token'] ) ) {
			throw new \RuntimeException( esc_html__( 'No refresh token available.', 'allcoach' ) );
		}

		$client = $this->createGoogleClient( '' );
		$client->setAccessToken( $this->tokensToGoogleFormat( $tokens ) );

		try {
			$creds = $client->fetchAccessTokenWithRefreshToken();
		} catch ( \Exception $e ) {
			$this->logger->error( 'Google token refresh failed', [ 'error' => $e->getMessage() ] );
			$this->tokenManager->deleteTokens( $coachId );
			throw new \RuntimeException( esc_html__( 'Google token refresh failed. Please reconnect.', 'allcoach' ) );
		}

		if ( ! is_array( $creds ) || empty( $creds['access_token'] ) ) {
			$this->logger->error( 'Google token refresh invalid response', [ 'body' => $creds ] );
			$this->tokenManager->deleteTokens( $coachId );
			throw new \RuntimeException( esc_html__( 'Google token refresh failed. Please reconnect.', 'allcoach' ) );
		}

		$expiresAt = ( $creds['created'] ?? time() ) + ( (int) ( $creds['expires_in'] ?? 3600 ) );

		$this->tokenManager->saveTokens(
			$coachId,
			$creds['access_token'],
			$creds['refresh_token'] ?? $tokens['refresh_token'],
			$expiresAt,
			$tokens['email']
		);

		return $creds['access_token'];
	}

	public function getValidAccessToken( int $coachId ): ?string {
		if ( ! $this->tokenManager->isConnected( $coachId ) ) {
			return null;
		}

		if ( $this->tokenManager->isExpired( $coachId ) ) {
			try {
				return $this->refreshAccessToken( $coachId );
			} catch ( \RuntimeException $e ) {
				$this->logger->error( 'Could not obtain valid Google token', [ 'coach_id' => $coachId ] );
				return null;
			}
		}

		$tokens = $this->tokenManager->getTokens( $coachId );
		return $tokens['access_token'] ?? null;
	}

	public function isConfigured(): bool {
		return $this->credentialManager->isConfigured();
	}

	private function getClientId(): string {
		return $this->credentialManager->getClientId();
	}

	private function getClientSecret(): string {
		return $this->credentialManager->getClientSecret();
	}

	private function createGoogleClient( string $redirectUri = '', string $state = '' ): GoogleClient {
		$client = new GoogleClient();
		$client->setClientId( $this->getClientId() );
		$client->setClientSecret( $this->getClientSecret() );
		$client->setScopes( self::SCOPES );
		$client->setAccessType( 'offline' );
		$client->setPrompt( 'consent' );

		if ( $redirectUri !== '' ) {
			$client->setRedirectUri( $redirectUri );
		}
		if ( $state !== '' ) {
			$client->setState( $state );
		}

		return $client;
	}

	/**
	 * @param array{access_token: string, refresh_token: string, expires_at: int} $tokens
	 * @return array{access_token: string, refresh_token: string, created: int, expires_in: int}
	 */
	private function tokensToGoogleFormat( array $tokens ): array {
		$expiresIn = 3600;
		$created   = $tokens['expires_at'] - $expiresIn;

		return [
			'access_token'  => $tokens['access_token'],
			'refresh_token' => $tokens['refresh_token'],
			'created'       => $created,
			'expires_in'    => $expiresIn,
		];
	}

	private function fetchUserEmail( string $accessToken ): string {
		$client = $this->createGoogleClient();
		$client->setAccessToken(
			[
				'access_token' => $accessToken,
				'created'      => time(),
				'expires_in'   => 3600,
			]
		);

		$service = new Oauth2( $client );

		try {
			$userInfo = $service->userinfo->get();
			return $userInfo->getEmail() ?? '';
		} catch ( \Exception $e ) {
			$this->logger->error( 'Google userinfo failed', [ 'error' => $e->getMessage() ] );
			return '';
		}
	}
}
