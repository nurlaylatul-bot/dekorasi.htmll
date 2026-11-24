<?php

declare(strict_types=1);

namespace AllCoach\Infrastructure\Google;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\Settings\SettingsManager;
use AllCoach\Domain\Booking\SessionBooking;
use AllCoach\Domain\Client\Client;
use AllCoach\Domain\Program\Program;
use AllCoach\Support\Logger;
use Carbon\CarbonImmutable;
use Google\Client as GoogleClient;
use Google\Service\Calendar;
use Google\Service\Calendar\ConferenceData;
use Google\Service\Calendar\CreateConferenceRequest;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\EventAttendee;
use Google\Service\Calendar\EventDateTime;
use Google\Service\Calendar\ConferenceSolutionKey;
use Google\Service\Calendar\EventReminder;
use Google\Service\Calendar\EventReminders;
use Google\Service\Exception as GoogleServiceException;

class GoogleCalendarService {

	private const CALENDAR_ID = 'primary';

	public function __construct(
		private readonly GoogleOAuthClient $oauthClient,
		private readonly GoogleTokenManager $tokenManager,
		private readonly SettingsManager $settings,
		private readonly Logger $logger,
	) {}

	/**
	 * @return array{event_id: string, meeting_url: string|null}|null
	 */
	public function createEvent( int $coachId, SessionBooking $session, Client $client, Program $program ): ?array {
		$accessToken = $this->oauthClient->getValidAccessToken( $coachId );
		if ( ! $accessToken ) {
			return null;
		}

		$event = $this->buildEvent( $session, $client, $program );

		$createRequest = new CreateConferenceRequest();
		$createRequest->setRequestId( 'allcoach-sb-' . $session->getId() );
		$key = new ConferenceSolutionKey();
		$key->setType( 'hangoutsMeet' );
		$createRequest->setConferenceSolutionKey( $key );
		$conferenceData = new ConferenceData();
		$conferenceData->setCreateRequest( $createRequest );
		$event->setConferenceData( $conferenceData );

		try {
			$calendar     = $this->getCalendarService( $accessToken );
			$createdEvent = $calendar->events->insert( self::CALENDAR_ID, $event, [ 'conferenceDataVersion' => 1 ] );
		} catch ( GoogleServiceException $e ) {
			$this->logger->error(
				'Google Calendar event creation failed',
				[
					'error'  => $e->getMessage(),
					'code'   => $e->getCode(),
					'errors' => $e->getErrors(),
				]
			);
			return null;
		}

		return [
			'event_id'    => $createdEvent->getId(),
			'meeting_url' => $this->extractMeetingUrl( $createdEvent ),
		];
	}

	public function deleteEvent( int $coachId, string $calendarEventId ): bool {
		$accessToken = $this->oauthClient->getValidAccessToken( $coachId );
		if ( ! $accessToken ) {
			return false;
		}

		try {
			$calendar = $this->getCalendarService( $accessToken );
			$calendar->events->delete( self::CALENDAR_ID, $calendarEventId );
			return true;
		} catch ( GoogleServiceException $e ) {
			$this->logger->error(
				'Google Calendar event deletion failed',
				[
					'error'    => $e->getMessage(),
					'code'     => $e->getCode(),
					'event_id' => $calendarEventId,
				]
			);
			return false;
		}
	}

	/**
	 * @return array{event_id: string, meeting_url: string|null}|null
	 */
	public function updateEvent( int $coachId, string $calendarEventId, SessionBooking $session, Client $client, Program $program ): ?array {
		$accessToken = $this->oauthClient->getValidAccessToken( $coachId );
		if ( ! $accessToken ) {
			return null;
		}

		$event = $this->buildEvent( $session, $client, $program );

		try {
			$calendar     = $this->getCalendarService( $accessToken );
			$updatedEvent = $calendar->events->patch(
				self::CALENDAR_ID,
				$calendarEventId,
				$event,
				[ 'conferenceDataVersion' => 1 ]
			);
		} catch ( GoogleServiceException $e ) {
			$this->logger->error(
				'Google Calendar event update failed',
				[
					'error'    => $e->getMessage(),
					'code'     => $e->getCode(),
					'event_id' => $calendarEventId,
				]
			);
			return null;
		}

		return [
			'event_id'    => $updatedEvent->getId() ?? $calendarEventId,
			'meeting_url' => $this->extractMeetingUrl( $updatedEvent ),
		];
	}

	private function getCalendarService( string $accessToken ): Calendar {
		$client = new GoogleClient();
		$client->setAccessToken(
			[
				'access_token' => $accessToken,
				'created'      => time(),
				'expires_in'   => 3600,
			]
		);

		return new Calendar( $client );
	}

	private function buildEvent( SessionBooking $session, Client $client, Program $program ): Event {
		$timezone      = $this->resolveIanaTimezone( $session->getTimezone() );
		$scheduledDate = $session->getScheduledDate();
		$startTime     = $session->getStartTime();
		$endTime       = $session->getEndTime();

		if ( ! $scheduledDate || ! $startTime || ! $endTime ) {
			throw new \InvalidArgumentException(
				sprintf( 'Session #%d is missing date or time values.', absint( $session->getId() ) )
			);
		}

		$startDt = $scheduledDate . 'T' . $this->normalizeTime( $startTime );
		$endDt   = $scheduledDate . 'T' . $this->normalizeTime( $endTime );

		$siteName = get_bloginfo( 'name' );
		$summary  = sprintf( '%s — Session #%d', $program->getTitle(), $session->getSessionNumber() );

		$descriptionParts = [
			sprintf( 'Program: %s', $program->getTitle() ),
			sprintf( 'Session: #%d', $session->getSessionNumber() ),
			sprintf( 'Client: %s (%s)', $client->getName(), $client->getEmail() ),
		];

		if ( ! empty( $session->getNotes() ) ) {
			$descriptionParts[] = sprintf( "Notes:\n%s", $session->getNotes() );
		}

		$descriptionParts[] = sprintf( 'Managed by %s', $siteName );
		$description        = implode( "\n\n", $descriptionParts );

		$start = new EventDateTime();
		$start->setDateTime( $startDt );
		$start->setTimeZone( $timezone );

		$end = new EventDateTime();
		$end->setDateTime( $endDt );
		$end->setTimeZone( $timezone );

		$attendees = [];

		if ( filter_var( $client->getEmail(), FILTER_VALIDATE_EMAIL ) ) {
			$clientAttendee = new EventAttendee();
			$clientAttendee->setEmail( $client->getEmail() );
			$clientAttendee->setDisplayName( $client->getName() );
			$attendees[] = $clientAttendee;
		} else {
			$this->logger->warning(
				'Invalid client email — skipping attendee',
				[
					'client_id'  => $client->getId(),
					'email'      => $client->getEmail(),
					'session_id' => $session->getId(),
				]
			);
		}

		$emailReminder = new EventReminder();
		$emailReminder->setMethod( 'email' );
		$emailReminder->setMinutes( 24 * 60 );

		$popupReminder = new EventReminder();
		$popupReminder->setMethod( 'popup' );
		$popupReminder->setMinutes( 30 );

		$reminders = new EventReminders();
		$reminders->setUseDefault( false );
		$reminders->setOverrides( [ $emailReminder, $popupReminder ] );

		$event = new Event();
		$event->setSummary( $summary );
		$event->setDescription( $description );
		$event->setStart( $start );
		$event->setEnd( $end );
		$event->setAttendees( $attendees );
		$event->setReminders( $reminders );
		$event->setColorId( '2' );

		return $event;
	}

	private function normalizeTime( string $time ): string {
		return substr_count( $time, ':' ) === 1 ? $time . ':00' : $time;
	}

	/**
	 * Ensure the timezone is a valid IANA identifier.
	 */
	private function resolveIanaTimezone( string $timezone ): string {
		$timezone = trim( $timezone );

		if ( $timezone !== '' && ! preg_match( '/^[+-]\d/', $timezone ) ) {
			try {
				new \DateTimeZone( $timezone );
				return $timezone;
			} catch ( \Exception ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Do nothing
			}
		}

		$wpTimezone = wp_timezone_string();
		if ( $wpTimezone !== '' && ! preg_match( '/^[+-]\d/', $wpTimezone ) ) {
			try {
				new \DateTimeZone( $wpTimezone );
				return $wpTimezone;
			} catch ( \Exception ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Do nothing
			}
		}
		try {
			$wpTz   = wp_timezone();
			$offset = $wpTz->getOffset( CarbonImmutable::now( new \DateTimeZone( 'UTC' ) ) );
			$abbrs  = \DateTimeZone::listAbbreviations();

			foreach ( $abbrs as $zones ) {
				foreach ( $zones as $zone ) {
					if ( $zone['offset'] === $offset && $zone['timezone_id'] !== '' ) {
						return $zone['timezone_id'];
					}
				}
			}
		} catch ( \Exception ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Do nothing
		}

		return 'UTC';
	}

	private function extractMeetingUrl( Event $event ): ?string {
		if ( $event->hangoutLink ?? null ) {
			return $event->hangoutLink;
		}

		$conferenceData = $event->getConferenceData();
		if ( ! $conferenceData ) {
			return null;
		}

		$entryPoints = $conferenceData->getEntryPoints();
		if ( ! $entryPoints ) {
			return null;
		}

		foreach ( $entryPoints as $entry ) {
			if ( $entry->getEntryPointType() === 'video' ) {
				return $entry->getUri();
			}
		}

		return null;
	}
}
