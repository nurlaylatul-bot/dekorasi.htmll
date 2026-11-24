<?php

declare(strict_types=1);

namespace AllCoach\API\Controllers\V1;

defined( 'ABSPATH' ) || exit;

use AllCoach\API\RestController;
use AllCoach\Infrastructure\Google\GoogleCredentialManager;
use AllCoach\Infrastructure\Google\GoogleOAuthClient;
use AllCoach\Infrastructure\Google\GoogleTokenManager;

use function AllCoach\Support\maskSecret;

class GoogleAuthController extends RestController {

	protected string $restBase = 'google';

	private const STATE_TRANSIENT_PREFIX = '_allcoach_google_oauth_state_';
	private const STATE_TTL              = 600;

	public function __construct(
		private readonly GoogleOAuthClient $oauthClient,
		private readonly GoogleTokenManager $tokenManager,
		private readonly GoogleCredentialManager $credentialManager,
	) {}

	public function registerRoutes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->restBase . '/auth-url',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'getAuthUrl' ],
					'permission_callback' => fn() => $this->permCoachOrAdmin(),
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->restBase . '/callback',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'handleRedirectCallback' ],
					'permission_callback' => [ $this, 'permOAuthCallback' ],
					'args'                => [
						'code'  => $this->argString(
							description: __( 'Authorization code from Google.', 'allcoach' ),
							required: true,
						),
						'state' => $this->argString(
							description: __( 'State parameter from Google.', 'allcoach' ),
							required: true,
						),
					],
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'handleCallback' ],
					'permission_callback' => fn() => $this->permCoachOrAdmin(),
					'args'                => [
						'code'         => $this->argString(
							description: __( 'Authorization code from Google.', 'allcoach' ),
							required: true,
						),
						'redirect_uri' => $this->argString(
							description: __( 'Redirect URI used in the authorization request.', 'allcoach' ),
							required: true,
						),
					],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->restBase . '/status',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'getStatus' ],
					'permission_callback' => fn() => $this->permCoachOrAdmin(),
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->restBase . '/disconnect',
			[
				[
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'disconnect' ],
					'permission_callback' => fn() => $this->permCoachOrAdmin(),
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->restBase . '/credentials',
			[
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'uploadCredentials' ],
					'permission_callback' => fn() => $this->checkPermission( null ),
				],
				[
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'deleteCredentials' ],
					'permission_callback' => fn() => $this->checkPermission( null ),
				],
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'getCredentials' ],
					'permission_callback' => fn() => $this->checkPermission( null ),
				],
			]
		);
	}

	public function getAuthUrl( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		if ( ! $this->oauthClient->isConfigured() ) {
			return $this->errorResponse(
				__( 'Google integration is not configured. Please set client ID and secret in settings.', 'allcoach' ),
				400
			);
		}

		$coachId = get_current_user_id();
		$state   = $this->generateOAuthState( $coachId );

		return $this->successResponse(
			[ 'url' => $this->oauthClient->getAuthUrl( $this->getOAuthRedirectUri(), $state ) ]
		);
	}

	public function permOAuthCallback( \WP_REST_Request $request ): bool|\WP_Error {
		$state = (string) $request->get_param( 'state' );

		$result = $this->validateAndConsumeState( $state );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$userId = $result['user_id'];
		$user   = get_user_by( 'id', $userId );
		if ( ! $user ) {
			return new \WP_Error( 'invalid_user', 'User not found.', [ 'status' => 401 ] );
		}

		$request->set_param( '_oauth_user_id', $userId );

		return true;
	}

	public function handleRedirectCallback( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		if ( ! $this->oauthClient->isConfigured() ) {
			return $this->redirectToSettings( __( 'Google integration is not configured.', 'allcoach' ) );
		}

		$code   = (string) $request->get_param( 'code' );
		$userId = (int) $request->get_param( '_oauth_user_id' );

		try {
			$this->oauthClient->exchangeCode( $code, $this->getOAuthRedirectUri(), $userId );
		} catch ( \RuntimeException $e ) {
			return $this->redirectToSettings( $e->getMessage() );
		}

		return $this->redirectToSettings( null, false );
	}

	public function handleCallback( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		if ( ! $this->oauthClient->isConfigured() ) {
			return $this->errorResponse(
				__( 'Google integration is not configured.', 'allcoach' ),
				400
			);
		}

		$code        = (string) $request->get_param( 'code' );
		$redirectUri = (string) $request->get_param( 'redirect_uri' );
		$userId      = get_current_user_id();

		try {
			$tokens = $this->oauthClient->exchangeCode( $code, $redirectUri, $userId );

			return $this->successResponse(
				[
					'connected' => true,
					'email'     => $tokens['email'],
				]
			);
		} catch ( \RuntimeException $e ) {
			return $this->errorResponse( $e->getMessage(), 400 );
		}
	}

	public function getStatus( \WP_REST_Request $request ): \WP_REST_Response {
		$coachId   = get_current_user_id();
		$connected = $this->tokenManager->isConnected( $coachId );
		$tokens    = $connected ? $this->tokenManager->getTokens( $coachId ) : null;

		return $this->successResponse(
			[
				'connected'    => $connected,
				'configured'   => $this->oauthClient->isConfigured(),
				'email'        => $tokens['email'] ?? null,
				'redirect_uri' => $this->getOAuthRedirectUri(),
			]
		);
	}

	public function disconnect( \WP_REST_Request $request ): \WP_REST_Response {
		$this->tokenManager->deleteTokens( get_current_user_id() );
		return $this->successResponse( [ 'connected' => false ] );
	}

	public function uploadCredentials( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$json = '';

		$bodyJson = $request->get_param( 'credentials_json' );
		if ( is_string( $bodyJson ) && $bodyJson !== '' ) {
			$json = $bodyJson;
		}

		if ( $json === '' ) {
			$files = $request->get_file_params();
			if ( ! empty( $files['credentials_file']['tmp_name'] ) ) {
				$json = file_get_contents( $files['credentials_file']['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			}
		}

		if ( empty( $json ) ) {
			return $this->errorResponse(
				__( 'No credentials provided. Upload a JSON file or send a credentials_json field.', 'allcoach' ),
				400
			);
		}

		try {
			$credentials = GoogleCredentialManager::parseJson( $json );
		} catch ( \InvalidArgumentException $e ) {
			return $this->errorResponse( $e->getMessage(), 422 );
		}

		$this->credentialManager->save( $credentials );

		return $this->successResponse(
			[
				'configured' => true,
				'project_id' => $credentials['project_id'] ?? '',
				'client_id'  => maskSecret( $credentials['client_id'] ),
			]
		);
	}

	public function deleteCredentials( \WP_REST_Request $request ): \WP_REST_Response {
		$this->credentialManager->delete();
		return $this->successResponse( [ 'configured' => false ] );
	}

	public function getCredentials( \WP_REST_Request $request ): \WP_REST_Response {
		$creds = $this->credentialManager->get();

		if ( ! $creds ) {
			return $this->successResponse( [ 'configured' => false ] );
		}

		return $this->successResponse(
			[
				'configured' => true,
				'project_id' => $creds['project_id'] ?? '',
				'client_id'  => maskSecret( $creds['client_id'] ),
			]
		);
	}

	public function getOAuthRedirectUri(): string {
		return rest_url( $this->namespace . '/' . $this->restBase . '/callback' );
	}

	/**
	 * @return string Base64-encoded "userId|expiry|hmac"
	 */
	private function generateOAuthState( int $userId ): string {
		$expiry    = time() + self::STATE_TTL;
		$payload   = $userId . '|' . $expiry;
		$signature = hash_hmac( 'sha256', $payload, wp_salt( 'auth' ) );
		$state     = base64_encode( $payload . '|' . $signature ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode

		set_transient( self::STATE_TRANSIENT_PREFIX . $userId, $state, self::STATE_TTL );

		return $state;
	}

	/**
	 * Validate a state token and consume it (one-time use).
	 *
	 * @return array{user_id: int}|\WP_Error
	 */
	private function validateAndConsumeState( string $state ): array|\WP_Error {
		if ( $state === '' ) {
			return new \WP_Error( 'missing_state', 'Missing state parameter.', [ 'status' => 401 ] );
		}

		$decoded = base64_decode( $state, strict: true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( $decoded === false ) {
			return new \WP_Error( 'invalid_state', 'Malformed state parameter.', [ 'status' => 401 ] );
		}

		$parts = explode( '|', $decoded );
		if ( count( $parts ) !== 3 ) {
			return new \WP_Error( 'invalid_state', 'Invalid state parameter.', [ 'status' => 401 ] );
		}

		[ $userId, $expiry, $signature ] = $parts;

		if ( time() > (int) $expiry ) {
			return new \WP_Error( 'expired_state', 'State token has expired.', [ 'status' => 401 ] );
		}

		$expectedSig = hash_hmac( 'sha256', $userId . '|' . $expiry, wp_salt( 'auth' ) );
		if ( ! hash_equals( $expectedSig, $signature ) ) {
			return new \WP_Error( 'invalid_state', 'State signature mismatch.', [ 'status' => 401 ] );
		}

		$transientKey = self::STATE_TRANSIENT_PREFIX . $userId;
		$stored       = get_transient( $transientKey );
		if ( $stored === false || ! hash_equals( $stored, $state ) ) {
			return new \WP_Error( 'state_mismatch', 'State token already used or not found.', [ 'status' => 401 ] );
		}

		delete_transient( $transientKey );

		return [ 'user_id' => (int) $userId ];
	}

	private function redirectToSettings( ?string $errorMessage, bool $isError = true ): \WP_REST_Response {
		$settingsUrl = admin_url( 'admin.php?page=allcoach' );

		if ( $errorMessage !== null && $isError ) {
			$settingsUrl = add_query_arg( 'allcoach_google_error', rawurlencode( $errorMessage ), $settingsUrl );
		} elseif ( ! $isError ) {
			$settingsUrl = add_query_arg( 'allcoach_google_connected', '1', $settingsUrl );
		}

		$settingsUrl .= '#/settings/google-meet';

		$response = new \WP_REST_Response( null, 302 );
		$response->header( 'Location', $settingsUrl );

		return $response;
	}

	protected function permCoachOrAdmin(): bool {
		return is_user_logged_in() && ( $this->isCoach() || $this->isAdmin() );
	}
}
