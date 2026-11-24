<?php

declare(strict_types=1);

namespace AllCoach\Application\Client;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\Events\EventDispatcher;
use AllCoach\Core\Exceptions\NotFoundException;
use AllCoach\Core\Exceptions\ValidationException;
use AllCoach\Domain\Client\Client;
use AllCoach\Domain\Client\ClientRepository;
use AllCoach\Domain\Client\ClientStatus;
use AllCoach\Domain\Client\Events\ClientUpdated;
use AllCoach\Support\Logger;

class UpdateClientService {

	public function __construct(
		private readonly ClientRepository $repository,
		private readonly Logger $logger,
		private readonly EventDispatcher $events,
	) {
	}

	public function execute( int $clientId, ClientDTO $dto ): Client {
		$client = $this->repository->findById( $clientId );
		if ( $client === null ) {
			throw NotFoundException::entity( esc_html__( 'Client', 'allcoach' ), absint( $clientId ) );
		}

		if ( $dto->email !== $client->getEmail() ) {
			$existingClient = $this->repository->findByEmail( $dto->email );
			if ( $existingClient !== null && $existingClient->getId() !== $clientId ) {
				throw ValidationException::withErrors(
					[
						'email' => sprintf(
							/* translators: %s: Client email */
							esc_html__( 'Client with email %s already exists', 'allcoach' ),
							esc_html( $dto->email )
						),
					]
				);
			}
		}

		$client->setFirstName( $dto->firstName );
		$client->setLastName( $dto->lastName );
		$client->setEmail( $dto->email );
		$client->setPhone( $dto->phone );
		$client->setTimezone( $dto->timezone );
		$client->setStatus( ClientStatus::fromString( $dto->status ) );
		$client->setNotes( $dto->notes );

		$client = $this->repository->save( $client );

		$this->logger->info(
			'Client updated',
			[
				'client_id' => $client->getId(),
				'email'     => $client->getEmail(),
			]
		);

		$this->events->dispatch( new ClientUpdated( $client ) );

		return $client;
	}
}
