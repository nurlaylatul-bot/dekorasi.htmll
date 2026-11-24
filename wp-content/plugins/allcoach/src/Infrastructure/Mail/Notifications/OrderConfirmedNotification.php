<?php

declare(strict_types=1);

namespace AllCoach\Infrastructure\Mail\Notifications;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\Settings\SettingsManager;
use AllCoach\Domain\Client\Client;
use AllCoach\Domain\Program\Program;
use AllCoach\Infrastructure\Mail\Mailer;
use AllCoach\Infrastructure\Mail\NotificationBookingContext;

class OrderConfirmedNotification {

	public function __construct(
		private readonly Mailer $mailer,
		private readonly SettingsManager $settings,
	) {}

	public function send( NotificationBookingContext $booking, Client $client, Program $program ): bool {
		if ( ! $this->settings->get( 'notify_order_confirmed' ) ) {
			return false;
		}

		$vars = [
			'order_id'             => (string) $booking->getOrderId(),
			'client_name'          => $client->getName(),
			'client_email'         => $client->getEmail(),
			'program_name'         => $program->getTitle(),
			'amount'               => (string) $booking->getAmount(),
			'currency'             => $booking->getCurrency(),
			'payment_method'       => $booking->getPaymentMethod(),
			'payment_method_title' => $this->settings->getPaymentMethodTitle( $booking->getPaymentMethod() ),
			'site_name'            => get_bloginfo( 'name' ),
			'site_url'             => home_url(),
		];

		$subject = $this->mailer->interpolate(
			(string) $this->settings->get( 'email_tpl_order_confirmed_subject' ),
			$vars
		);

		$body = $this->mailer->interpolate(
			(string) $this->settings->get( 'email_tpl_order_confirmed_body' ),
			$vars
		);

		return $this->mailer->send( $client->getEmail(), $subject, $body );
	}
}
