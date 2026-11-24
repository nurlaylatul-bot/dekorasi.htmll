<?php

declare(strict_types=1);

namespace AllCoach\Application\Client;

defined( 'ABSPATH' ) || exit;

class ClientDTO {

	public function __construct(
		public readonly ?int $id,
		public readonly string $firstName,
		public readonly string $lastName,
		public readonly string $email,
		public readonly string $phone,
		public readonly string $timezone,
		public readonly string $status,
		public readonly string $notes,
	) {
	}

	public static function fromArray( array $data ): self {
		return new self(
			id: isset( $data['id'] ) ? (int) $data['id'] : null,
			firstName: $data['first_name'] ?? '',
			lastName: $data['last_name'] ?? '',
			email: $data['email'] ?? '',
			phone: $data['phone'] ?? '',
			timezone: $data['timezone'] ?? '',
			status: $data['status'] ?? 'active',
			notes: $data['notes'] ?? '',
		);
	}

	public function toArray(): array {
		return [
			'id'         => $this->id,
			'first_name' => $this->firstName,
			'last_name'  => $this->lastName,
			'email'      => $this->email,
			'phone'      => $this->phone,
			'timezone'   => $this->timezone,
			'status'     => $this->status,
			'notes'      => $this->notes,
		];
	}
}
