<?php

declare(strict_types=1);

namespace AllCoach\Infrastructure\Mail\Notifications;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\Settings\SettingsManager;
use AllCoach\Domain\Client\Client;
use AllCoach\Frontend\Router;
use AllCoach\Infrastructure\Mail\Mailer;

class WelcomeClientNotification {

	public function __construct(
		private readonly Mailer $mailer,
		private readonly SettingsManager $settings,
	) {}

	public function send( Client $client ): bool {
		if ( ! $this->settings->get( 'notify_welcome_client' ) ) {
			return false;
		}

		$set_password_url = '';
		$user             = get_user_by( 'email', $client->getEmail() );
		if ( $user instanceof \WP_User ) {
			$key = get_password_reset_key( $user );
			if ( ! is_wp_error( $key ) ) {
				$set_password_url = add_query_arg(
					[
						'action'      => 'rp',
						'key'         => $key,
						'login'       => rawurlencode( $user->user_login ),
						'redirect_to' => rawurldecode( Router::url() ),
					],
					wp_login_url()
				);
			}
		}

		$vars = [
			'client_name'      => $client->getName(),
			'client_email'     => $client->getEmail(),
			'login_url'        => Router::url(),
			'set_password_url' => $set_password_url ?: Router::url(),
			'site_name'        => get_bloginfo( 'name' ),
			'site_url'         => home_url(),
		];

		$subject = $this->mailer->interpolate(
			(string) $this->settings->get( 'email_tpl_welcome_client_subject' ),
			$vars
		);

		$body = $this->mailer->interpolate(
			(string) $this->settings->get( 'email_tpl_welcome_client_body' ),
			$vars
		);

		return $this->mailer->send( $client->getEmail(), $subject, $body );
	}
}
