<?php

declare(strict_types=1);

namespace AllCoach\Infrastructure\Mail\Notifications;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\Settings\SettingsManager;
use AllCoach\Domain\Booking\SessionBooking;
use AllCoach\Domain\Client\Client;
use AllCoach\Domain\Program\Program;
use AllCoach\Infrastructure\Mail\Mailer;
use AllCoach\Infrastructure\Mail\NotificationBookingContext;

class CancellationNoticeNotification {

	public function __construct(
		private readonly Mailer $mailer,
		private readonly SettingsManager $settings,
	) {}

	public function send( NotificationBookingContext $booking, Client $client, Program $program, ?SessionBooking $session = null ): bool {
		if ( ! $this->settings->get( 'notify_cancellation_notice' ) ) {
			return false;
		}

		$vars = $this->buildVars( $client, $program, $session );

		$subject = $this->mailer->interpolate(
			(string) $this->settings->get( 'email_tpl_cancellation_notice_subject' ),
			$vars
		);

		$body = $this->mailer->interpolate(
			(string) $this->settings->get( 'email_tpl_cancellation_notice_body' ),
			$vars
		);

		return $this->mailer->send( $client->getEmail(), $subject, $body );
	}

	/** @return array<string, string> */
	private function buildVars( Client $client, Program $program, ?SessionBooking $session ): array {
		$vars = [
			'client_name'  => $client->getName(),
			'client_email' => $client->getEmail(),
			'program_name' => $program->getTitle(),
			'site_name'    => get_bloginfo( 'name' ),
			'site_url'     => home_url(),
		];

		if ( $session !== null ) {
			$vars['scheduled_date'] = $session->getScheduledDate();
			$vars['start_time']     = $session->getStartTime();
			$vars['end_time']       = $session->getEndTime();
		} else {
			$vars['scheduled_date'] = '';
			$vars['start_time']     = '';
			$vars['end_time']       = '';
		}

		return $vars;
	}
}
