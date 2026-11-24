<?php

declare(strict_types=1);

namespace AllCoach\Domain\Client;

defined( 'ABSPATH' ) || exit;

class ClientBuilder {

	private string $firstName     = '';
	private string $lastName      = '';
	private string $email         = '';
	private string $phone         = '';
	private ?ClientStatus $status = null;
	private string $notes         = '';

	public function withFirstName( string $firstName ): self {
		$this->firstName = $firstName;
		return $this;
	}

	public function withLastName( string $lastName ): self {
		$this->lastName = $lastName;
		return $this;
	}

	public function withEmail( string $email ): self {
		$this->email = $email;
		return $this;
	}

	public function withPhone( string $phone ): self {
		$this->phone = $phone;
		return $this;
	}

	public function withStatus( ClientStatus $status ): self {
		$this->status = $status;
		return $this;
	}

	public function asActive(): self {
		$this->status = ClientStatus::active();
		return $this;
	}

	public function asInactive(): self {
		$this->status = ClientStatus::inactive();
		return $this;
	}

	public function withNotes( string $notes ): self {
		$this->notes = $notes;
		return $this;
	}

	public function build(): Client {
		return new Client(
			firstName: $this->firstName,
			lastName: $this->lastName,
			email: $this->email,
			phone: $this->phone,
			status: $this->status,
			notes: $this->notes
		);
	}

	public static function make(): self {
		return new self();
	}
}
