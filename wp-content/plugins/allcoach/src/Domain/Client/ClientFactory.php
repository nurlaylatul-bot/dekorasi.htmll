<?php

declare(strict_types=1);

namespace AllCoach\Domain\Client;

defined( 'ABSPATH' ) || exit;

use AllCoach\Domain\Shared\Factory;

class ClientFactory implements Factory {

	public function create( array $data ): Client {
		return ClientBuilder::make()
			->withFirstName( $data['first_name'] ?? '' )
			->withLastName( $data['last_name'] ?? '' )
			->withEmail( $data['email'] ?? '' )
			->withPhone( $data['phone'] ?? '' )
			->withStatus(
				isset( $data['status'] )
					? ClientStatus::fromString( $data['status'] )
					: ClientStatus::active()
			)
			->withNotes( $data['notes'] ?? '' )
			->build();
	}

	public function fromArray( array $data ): Client {
		return $this->create( $data );
	}

	public function createActive( string $firstName, string $lastName, string $email ): Client {
		return ClientBuilder::make()
			->withFirstName( $firstName )
			->withLastName( $lastName )
			->withEmail( $email )
			->asActive()
			->build();
	}
}
