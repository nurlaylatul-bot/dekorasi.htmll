<?php

declare(strict_types=1);

namespace AllCoach\Infrastructure\Mail\Notifications;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\Settings\SettingsManager;
use AllCoach\Domain\Booking\SessionBooking;
use AllCoach\Domain\Client\Client;
use AllCoach\Domain\Program\Program;
use AllCoach\Infrastructure\Mail\Mailer;

class SessionCompletedFollowUpNotification {

	public function __construct(
		private readonly Mailer $mailer,
		private readonly SettingsManager $settings,
	) {}

	public function send( SessionBooking $session, Client $client, Program $program ): bool {
		if ( ! $this->settings->get( 'notify_session_completed_follow_up' ) ) {
			return false;
		}

		$vars = $this->buildVars( $session, $client, $program );

		$subject = $this->mailer->interpolate(
			(string) $this->settings->get( 'email_tpl_session_completed_follow_up_subject' ),
			$vars
		);

		$body = $this->mailer->interpolate(
			(string) $this->settings->get( 'email_tpl_session_completed_follow_up_body' ),
			$vars
		);

		return $this->mailer->send( $client->getEmail(), $subject, $body );
	}

	/** @return array<string, string> */
	private function buildVars( SessionBooking $session, Client $client, Program $program ): array {
		return [
			'client_name'    => $client->getName(),
			'program_name'   => $program->getTitle(),
			'scheduled_date' => $session->getScheduledDate(),
			'start_time'     => $session->getStartTime(),
			'end_time'       => $session->getEndTime(),
			'site_name'      => get_bloginfo( 'name' ),
			'site_url'       => home_url(),
		];
	}
}
