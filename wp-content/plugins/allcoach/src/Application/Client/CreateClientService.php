<?php

declare(strict_types=1);

namespace AllCoach\Application\Client;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\Events\EventDispatcher;
use AllCoach\Core\Exceptions\NotFoundException;
use AllCoach\Domain\Client\Client;
use AllCoach\Domain\Client\ClientRepository;
use AllCoach\Domain\Client\ClientStatus;
use AllCoach\Domain\Client\Events\ClientCreated;
use AllCoach\Support\Logger;

class CreateClientService {

	public function __construct(
		private readonly ClientRepository $repository,
		private readonly Logger $logger,
		private readonly EventDispatcher $events
	) {
	}

	public function execute( ClientDTO $dto ): Client {
		$existingClient = $this->repository->findByEmail( $dto->email );
		if ( $existingClient !== null ) {
			throw NotFoundException::entity( esc_html__( 'Client', 'allcoach' ), absint( $dto->id ) );
		}

		$client = new Client(
			firstName: $dto->firstName,
			lastName: $dto->lastName,
			email: $dto->email,
			phone: $dto->phone,
			timezone: $dto->timezone,
			status: ClientStatus::fromString( $dto->status ),
			notes: $dto->notes,
		);

		$client = $this->repository->save( $client );

		$this->logger->info(
			'Client created',
			[
				'client_id' => $client->getId(),
				'email'     => $client->getEmail(),
			]
		);

		$this->events->dispatch( new ClientCreated( $client ) );

		return $client;
	}
}
