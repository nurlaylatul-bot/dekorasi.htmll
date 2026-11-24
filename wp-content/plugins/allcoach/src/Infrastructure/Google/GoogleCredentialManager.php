<?php

declare(strict_types=1);

namespace AllCoach\Infrastructure\Google;

defined( 'ABSPATH' ) || exit;

class GoogleCredentialManager {

	private const OPTION_KEY = '_allcoach_google_credentials';

	private const REQUIRED_KEYS = [ 'client_id', 'client_secret' ];

	/**
	 * @param array{client_id: string, client_secret: string, project_id?: string, auth_uri?: string, token_uri?: string, redirect_uris?: string[]} $credentials
	 */
	public function save( array $credentials ): void {
		$clean = [
			'client_id'     => sanitize_text_field( $credentials['client_id'] ),
			'client_secret' => sanitize_text_field( $credentials['client_secret'] ),
			'project_id'    => sanitize_text_field( $credentials['project_id'] ?? '' ),
			'auth_uri'      => esc_url_raw( $credentials['auth_uri'] ?? '' ),
			'token_uri'     => esc_url_raw( $credentials['token_uri'] ?? '' ),
		];

		update_option( self::OPTION_KEY, $clean, false );
	}

	/**
	 * @return array{client_id: string, client_secret: string, project_id: string, auth_uri: string, token_uri: string}|null
	 */
	public function get(): ?array {
		$stored = get_option( self::OPTION_KEY, null );

		if ( ! is_array( $stored ) || empty( $stored['client_id'] ) ) {
			return null;
		}

		return $stored;
	}

	public function getClientId(): string {
		return $this->get()['client_id'] ?? '';
	}

	public function getClientSecret(): string {
		return $this->get()['client_secret'] ?? '';
	}

	public function isConfigured(): bool {
		$creds = $this->get();
		return $creds !== null
			&& ! empty( $creds['client_id'] )
			&& ! empty( $creds['client_secret'] );
	}

	public function delete(): void {
		delete_option( self::OPTION_KEY );
	}

	/**
	 * @return array{client_id: string, client_secret: string, project_id?: string, auth_uri?: string, token_uri?: string}
	 * @throws \InvalidArgumentException When the JSON is malformed or missing required fields.
	 */
	public static function parseJson( string $json ): array {
		$data = json_decode( $json, true );

		if ( ! is_array( $data ) ) {
			throw new \InvalidArgumentException(
				esc_html__( 'Invalid JSON file.', 'allcoach' )
			);
		}

		$credentials = $data['web'] ?? $data['installed'] ?? $data;

		foreach ( self::REQUIRED_KEYS as $key ) {
			if ( empty( $credentials[ $key ] ) ) {
				throw new \InvalidArgumentException(
					sprintf(
						/* translators: %s: Missing key name */
						esc_html__( 'Missing required field: %s', 'allcoach' ),
						esc_html( $key )
					)
				);
			}
		}

		return $credentials;
	}
}
