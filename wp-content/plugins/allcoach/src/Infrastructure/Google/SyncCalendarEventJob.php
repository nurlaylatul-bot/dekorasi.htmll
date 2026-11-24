<?php

declare(strict_types=1);

namespace AllCoach\Infrastructure\Google;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\Jobs\AbstractJob;
use AllCoach\Core\Jobs\AbstractJobPayload;
use AllCoach\Domain\Booking\SessionBookingRepository;
use AllCoach\Domain\Client\ClientRepository;
use AllCoach\Domain\Program\ProgramRepository;
use AllCoach\Support\Logger;

class SyncCalendarEventJob extends AbstractJob {

	public function __construct(
		private readonly SessionBookingRepository $sessionBookingRepository,
		private readonly ClientRepository $clientRepository,
		private readonly ProgramRepository $programRepository,
		private readonly GoogleCalendarService $calendarService,
		private readonly GoogleOAuthClient $oauthClient,
		private readonly Logger $logger,
	) {}

	public function handle( AbstractJobPayload $payload ): void {
		assert( $payload instanceof SyncCalendarEvenJobPayload );

		if ( ! $this->oauthClient->isConfigured() ) {
			return;
		}

		match ( $payload->action ) {
			'create' => $this->handleCreate( $payload->sessionBookingId ),
			'delete' => $this->handleDelete( $payload->sessionBookingId ),
			default  => $this->logger->error( 'Unknown calendar sync action', [ 'action' => $payload->action ] ),
		};
	}

	public static function payloadFromArray( array $data ): SyncCalendarEvenJobPayload {
		return SyncCalendarEvenJobPayload::fromArray( $data );
	}

	private function handleCreate( int $sessionBookingId ): void {
		$sb = $this->sessionBookingRepository->findById( $sessionBookingId );
		if ( ! $sb ) {
			$this->logger->error(
				'Session booking not found for calendar sync',
				[ 'id' => $sessionBookingId ]
			);
			return;
		}

		$coachId = $sb->getCoachId();

		$client = $this->clientRepository->findById( $sb->getClientId() );
		if ( ! $client ) {
			$this->logger->error(
				'Client not found for calendar sync',
				[ 'client_id' => $sb->getClientId() ]
			);
			return;
		}

		$program = $this->programRepository->findById( $sb->getProgramId() );
		if ( ! $program ) {
			$this->logger->error(
				'Program not found for calendar sync',
				[ 'program_id' => $sb->getProgramId() ]
			);
			return;
		}

		$result = $this->calendarService->createEvent( $coachId, $sb, $client, $program );
		if ( ! $result ) {
			return;
		}

		$sb->setCalendarEventId( $result['event_id'] );
		$sb->setMeetingUrl( $result['meeting_url'] );
		$this->sessionBookingRepository->save( $sb );

		$this->logger->info(
			'Calendar event created for session booking',
			[
				'session_booking_id' => $sessionBookingId,
				'event_id'           => $result['event_id'],
			]
		);
	}

	private function handleDelete( int $sessionBookingId ): void {
		$sb = $this->sessionBookingRepository->findById( $sessionBookingId );
		if ( ! $sb ) {
			return;
		}

		$calendarEventId = $sb->getCalendarEventId();
		if ( empty( $calendarEventId ) ) {
			return;
		}

		$deleted = $this->calendarService->deleteEvent( $sb->getCoachId(), $calendarEventId );

		if ( $deleted ) {
			$sb->setCalendarEventId( null );
			$sb->setMeetingUrl( null );
			$this->sessionBookingRepository->save( $sb );
			$this->logger->info( 'Calendar event deleted', [ 'event_id' => $calendarEventId ] );
		}
	}
}
