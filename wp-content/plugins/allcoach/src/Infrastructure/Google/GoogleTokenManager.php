<?php

declare(strict_types=1);

namespace AllCoach\Infrastructure\Google;

defined( 'ABSPATH' ) || exit;

class GoogleTokenManager {

	private const META_ACCESS_TOKEN  = '_allcoach_google_access_token';
	private const META_REFRESH_TOKEN = '_allcoach_google_refresh_token';
	private const META_EXPIRES_AT    = '_allcoach_google_token_expires_at';
	private const META_EMAIL         = '_allcoach_google_email';

	/**
	 * @return array{access_token: string, refresh_token: string, expires_at: int, email: string}|null
	 */
	public function getTokens( int $coachId ): ?array {
		$accessToken = get_user_meta( $coachId, self::META_ACCESS_TOKEN, true );
		if ( empty( $accessToken ) ) {
			return null;
		}

		return [
			'access_token'  => (string) $accessToken,
			'refresh_token' => (string) get_user_meta( $coachId, self::META_REFRESH_TOKEN, true ),
			'expires_at'    => (int) get_user_meta( $coachId, self::META_EXPIRES_AT, true ),
			'email'         => (string) get_user_meta( $coachId, self::META_EMAIL, true ),
		];
	}

	public function saveTokens( int $coachId, string $accessToken, string $refreshToken, int $expiresAt, string $email = '' ): void {
		update_user_meta( $coachId, self::META_ACCESS_TOKEN, $accessToken );
		update_user_meta( $coachId, self::META_EXPIRES_AT, $expiresAt );

		if ( ! empty( $refreshToken ) ) {
			update_user_meta( $coachId, self::META_REFRESH_TOKEN, $refreshToken );
		}

		if ( ! empty( $email ) ) {
			update_user_meta( $coachId, self::META_EMAIL, $email );
		}
	}

	public function deleteTokens( int $coachId ): void {
		delete_user_meta( $coachId, self::META_ACCESS_TOKEN );
		delete_user_meta( $coachId, self::META_REFRESH_TOKEN );
		delete_user_meta( $coachId, self::META_EXPIRES_AT );
		delete_user_meta( $coachId, self::META_EMAIL );
	}

	public function isConnected( int $coachId ): bool {
		$tokens = $this->getTokens( $coachId );
		return $tokens !== null && ! empty( $tokens['refresh_token'] );
	}

	public function isExpired( int $coachId ): bool {
		$tokens = $this->getTokens( $coachId );
		if ( $tokens === null ) {
			return true;
		}
		return time() >= ( $tokens['expires_at'] - 60 );
	}
}
