<?php

declare(strict_types=1);

namespace AllCoach\Application\Coach;

defined( 'ABSPATH' ) || exit;

class CoachDTO {

	public function __construct(
		public readonly ?int $id,
		public readonly string $name,
		public readonly string $email,
		public readonly string $bio = '',
	) {
	}

	public static function fromArray( array $data ): self {
		return new self(
			id:    isset( $data['id'] ) ? (int) $data['id'] : null,
			name:  $data['name'] ?? '',
			email: $data['email'] ?? '',
			bio:   $data['bio'] ?? '',
		);
	}

	public function toArray(): array {
		return [
			'id'    => $this->id,
			'name'  => $this->name,
			'email' => $this->email,
			'bio'   => $this->bio,
		];
	}
}
