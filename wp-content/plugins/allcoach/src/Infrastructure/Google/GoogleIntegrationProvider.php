<?php

declare(strict_types=1);

namespace AllCoach\Infrastructure\Google;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\Jobs\Contracts\JobDispatcher;
use AllCoach\Domain\Booking\SessionBooking;
use AllCoach\Domain\Booking\SessionBookingRepository;
use AllCoach\Domain\Client\ClientRepository;
use AllCoach\Domain\Program\ProgramRepository;
use AllCoach\Core\Contracts\ServiceProviderInterface;
use AllCoach\Support\Logger;
use AllCoach\Traits\Hooks;

class GoogleIntegrationProvider implements ServiceProviderInterface {

	use Hooks;

	public function __construct(
		private readonly JobDispatcher $dispatcher,
		private readonly GoogleCalendarService $calendarService,
		private readonly GoogleOAuthClient $oauthClient,
		private readonly SessionBookingRepository $sessionBookingRepository,
		private readonly ClientRepository $clientRepository,
		private readonly ProgramRepository $programRepository,
		private readonly Logger $logger,
	) {}

	public function boot(): void {
		$this->addAction( 'allcoach:session:booked', [ $this, 'onSessionBooked' ] );
		$this->addAction( 'allcoach:session:cancelled', [ $this, 'onSessionCancelled' ] );
	}

	public function onSessionBooked( SessionBooking $session ): void {
		if ( ! $session->getMeetingType()->isGoogleMeet() ) {
			return;
		}

		if ( ! $this->oauthClient->isConfigured() ) {
			return;
		}

		$client = $this->clientRepository->findById( $session->getClientId() );
		if ( ! $client ) {
			return;
		}

		$program = $this->programRepository->findById( $session->getProgramId() );
		if ( ! $program ) {
			return;
		}

		try {
			$result = $this->calendarService->createEvent(
				$session->getCoachId(),
				$session,
				$client,
				$program
			);

			if ( $result ) {
				$session->setCalendarEventId( $result['event_id'] );
				$session->setMeetingUrl( $result['meeting_url'] );
				$this->sessionBookingRepository->save( $session );

				$this->logger->info(
					'Calendar event created inline',
					[
						'session_booking_id' => $session->getId(),
						'event_id'           => $result['event_id'],
					]
				);
			}
		} catch ( \Throwable $e ) {
			$this->logger->error(
				'Inline calendar sync failed',
				[
					'session_booking_id' => $session->getId(),
					'error'              => $e->getMessage(),
				]
			);
		}
	}

	public function onSessionCancelled( SessionBooking $session ): void {
		if ( empty( $session->getCalendarEventId() ) ) {
			return;
		}

		$this->dispatcher->dispatch(
			SyncCalendarEventJob::class,
			new SyncCalendarEvenJobPayload(
				action: 'delete',
				sessionBookingId: (int) $session->getId(),
			)
		);
	}
}
