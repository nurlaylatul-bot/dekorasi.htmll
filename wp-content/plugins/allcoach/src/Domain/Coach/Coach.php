<?php

declare(strict_types=1);

namespace AllCoach\Domain\Coach;

defined( 'ABSPATH' ) || exit;

use AllCoach\Domain\Shared\Entity;
use Carbon\CarbonImmutable;

class Coach extends Entity {

	private string $name;
	private string $email;
	private ?string $bio = null;
	private CarbonImmutable $createdAt;
	private ?CarbonImmutable $updatedAt = null;

	public function __construct( string $name, string $email ) {
		$this->setName( $name );
		$this->setEmail( $email );
		$this->createdAt = CarbonImmutable::now();
	}

	public function getName(): string {
		return $this->name;
	}

	public function setName( string $name ): void {
		if ( empty( trim( $name ) ) ) {
			throw new \InvalidArgumentException( 'Coach name cannot be empty.' );
		}
		$this->name = $name;
		$this->touch();
	}

	public function getEmail(): string {
		return $this->email;
	}

	public function setEmail( string $email ): void {
		if ( ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
			throw new \InvalidArgumentException( 'Invalid coach email address.' );
		}
		$this->email = $email;
		$this->touch();
	}

	public function getBio(): ?string {
		return $this->bio;
	}

	public function setBio( ?string $bio ): void {
		$this->bio = $bio;
		$this->touch();
	}

	public function getUserId(): ?int {
		return $this->id !== null ? (int) $this->id : null;
	}

	public function getCreatedAt(): CarbonImmutable {
		return $this->createdAt;
	}

	public static function reconstitute(
		int $id,
		string $name,
		string $email,
		?string $bio,
		CarbonImmutable $createdAt,
		?CarbonImmutable $updatedAt
	): self {
		$coach            = new self( $name, $email );
		$coach->id        = $id;
		$coach->bio       = $bio;
		$coach->createdAt = $createdAt;
		$coach->updatedAt = $updatedAt;
		return $coach;
	}

	public function getUpdatedAt(): ?CarbonImmutable {
		return $this->updatedAt;
	}

	public function toArray(): array {
		return [
			'id'         => $this->id,
			'user_id'    => $this->id,
			'name'       => $this->name,
			'email'      => $this->email,
			'bio'        => $this->bio,
			'created_at' => $this->createdAt->format( 'Y-m-d H:i:s' ),
			'updated_at' => $this->updatedAt?->format( 'Y-m-d H:i:s' ),
		];
	}

	private function touch(): void {
		$this->updatedAt = CarbonImmutable::now();
	}
}
