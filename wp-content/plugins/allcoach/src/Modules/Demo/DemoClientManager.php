<?php

declare(strict_types=1);

namespace AllCoach\Modules\Demo;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\RoleManager;

class DemoClientManager {

	private const DEMO_USER_LOGIN = 'allcoach_demo_client';
	private const DEMO_META_KEY   = '_allcoach_is_demo';

	public function getOrCreateDemoUser(): \WP_User {
		$existing = get_user_by( 'login', self::DEMO_USER_LOGIN );
		if ( $existing instanceof \WP_User ) {
			return $existing;
		}

		$userId = wp_insert_user(
			[
				'user_login'   => self::DEMO_USER_LOGIN,
				'user_email'   => 'demo@' . wp_parse_url( home_url(), PHP_URL_HOST ),
				'user_pass'    => wp_generate_password( 32 ),
				'display_name' => __( 'Demo Client', 'allcoach' ),
				'first_name'   => __( 'Demo', 'allcoach' ),
				'last_name'    => __( 'Client', 'allcoach' ),
				'role'         => RoleManager::ROLE_CLIENT,
			]
		);

		if ( is_wp_error( $userId ) ) {
			throw new \RuntimeException( 'Failed to create demo user: ' . esc_html( $userId->get_error_message() ) );
		}

		update_user_meta( $userId, self::DEMO_META_KEY, '1' );

		return get_user_by( 'id', $userId );
	}

	public static function isDemoSession(): bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		return (bool) get_user_meta( get_current_user_id(), self::DEMO_META_KEY, true );
	}
}
