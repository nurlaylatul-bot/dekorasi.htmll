<?php

declare(strict_types=1);

namespace AllCoach\Modules\Demo;

defined( 'ABSPATH' ) || exit;

class DemoSessionService {

	private const TOKEN_TRANSIENT_PREFIX = 'allcoach_demo_token_';
	private const TOKEN_TTL_SECONDS      = 300;

	public function generateMagicLink( int $requestingUserId, string $redirectUrl = '' ): string {
		$token = wp_generate_password( 48, false );

		set_transient(
			self::TOKEN_TRANSIENT_PREFIX . $token,
			$requestingUserId,
			self::TOKEN_TTL_SECONDS
		);

		$params = [ 'allcoach-demo' => $token ];

		if ( $redirectUrl !== '' ) {
			$params['allcoach-demo-redirect'] = rawurlencode( $redirectUrl );
		}

		return add_query_arg( $params, \AllCoach\Frontend\Router::url() );
	}

	public function consumeToken( string $token ): ?int {
		$key    = self::TOKEN_TRANSIENT_PREFIX . $token;
		$userId = get_transient( $key );

		if ( $userId === false ) {
			return null;
		}

		delete_transient( $key );

		return (int) $userId;
	}
}
