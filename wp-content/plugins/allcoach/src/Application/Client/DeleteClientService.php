<?php

declare(strict_types=1);

namespace AllCoach\Application\Client;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\Events\EventDispatcher;
use AllCoach\Core\Exceptions\NotFoundException;
use AllCoach\Core\Exceptions\ValidationException;
use AllCoach\Domain\Client\ClientRepository;
use AllCoach\Domain\Client\Events\ClientDeleted;
use AllCoach\Support\Logger;

class DeleteClientService {

	public function __construct(
		private readonly ClientRepository $repository,
		private readonly Logger $logger,
		private readonly EventDispatcher $events
	) {
	}

	public function execute( int $clientId ): void {
		$client = $this->repository->findById( $clientId );
		if ( $client === null ) {
			throw NotFoundException::entity( esc_html__( 'Client', 'allcoach' ), absint( $clientId ) );
		}

		if ( ! $this->repository->delete( $clientId ) ) {
			throw ValidationException::withErrors(
				[
					'client_id' => sprintf(
						/* translators: %d: Client ID */
						esc_html__( 'Failed to delete client with ID %d', 'allcoach' ),
						absint( $clientId )
					),
				]
			);
		}

		$this->logger->info(
			'Client deleted',
			[
				'client_id' => $clientId,
				'email'     => $client->getEmail(),
			]
		);

		$this->events->dispatch( new ClientDeleted( $client ) );
	}
}
