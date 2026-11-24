<?php

declare(strict_types=1);

namespace AllCoach\Infrastructure\Mail\Notifications;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\Settings\SettingsManager;
use AllCoach\Domain\Booking\SessionBooking;
use AllCoach\Domain\Client\Client;
use AllCoach\Domain\Program\Program;
use AllCoach\Infrastructure\Mail\Mailer;

class SessionReminder24hNotification {

	public function __construct(
		private readonly Mailer $mailer,
		private readonly SettingsManager $settings,
	) {}

	public function send( SessionBooking $session, Client $client, Program $program ): bool {
		if ( ! $this->settings->get( 'notify_24_hour_reminder' ) ) {
			return false;
		}

		$vars = $this->buildVars( $session, $client, $program );

		$subject = $this->mailer->interpolate(
			(string) $this->settings->get( 'email_tpl_24h_reminder_subject' ),
			$vars
		);

		$body = $this->mailer->interpolate(
			(string) $this->settings->get( 'email_tpl_24h_reminder_body' ),
			$vars
		);

		return $this->mailer->send( $client->getEmail(), $subject, $body );
	}

	/** @return array<string, string> */
	private function buildVars( SessionBooking $session, Client $client, Program $program ): array {
		$meetingUrl = $session->getMeetingUrl();
		if ( $meetingUrl === null || $meetingUrl === '' ) {
			$meetingUrl = __( 'To be provided', 'allcoach' );
		}

		return [
			'client_name'     => $client->getName(),
			'program_name'    => $program->getTitle(),
			'scheduled_date'  => $session->getScheduledDate(),
			'start_time'      => $session->getStartTime(),
			'end_time'        => $session->getEndTime(),
			'meeting_url'     => $meetingUrl,
			'meeting_details' => $this->buildMeetingDetails( $session ),
			'site_name'       => get_bloginfo( 'name' ),
			'site_url'        => home_url(),
		];
	}

	/**
	 * Meeting line, adapted to the booking's meeting type: an in-person location
	 * for face-to-face bookings, otherwise a join link (or a placeholder when the
	 * link isn't available yet).
	 */
	private function buildMeetingDetails( SessionBooking $session ): string {
		if ( $session->getMeetingType()->isFaceToFace() ) {
			$location = (string) $session->getLocation();
			$value    = $location !== '' ? $location : __( 'To be provided', 'allcoach' );
			/* translators: %s: in-person meeting location. */
			return sprintf( __( 'Location: %s', 'allcoach' ), esc_html( $value ) );
		}

		$url = (string) $session->getMeetingUrl();
		$value = $url !== ''
			? sprintf( '<a href="%s">%s</a>', esc_url( $url ), esc_html( $url ) )
			: esc_html__( 'To be provided', 'allcoach' );
		/* translators: %s: meeting join link. */
		return sprintf( __( 'Join via: %s', 'allcoach' ), $value );
	}
}
