<?php

declare(strict_types=1);

namespace AllCoach\Domain\Client;

defined( 'ABSPATH' ) || exit;

use AllCoach\Domain\Shared\Entity;
use Carbon\CarbonImmutable;

class Client extends Entity {

	private string $firstName;
	private string $lastName;
	private string $email;
	private string $phone;
	private string $timezone;
	private ClientStatus $status;
	private string $notes;
	private bool $isDemo = false;
	private CarbonImmutable $createdAt;
	private ?CarbonImmutable $updatedAt = null;

	public function __construct(
		string $firstName,
		string $lastName,
		string $email,
		string $phone = '',
		string $timezone = '',
		?ClientStatus $status = null,
		string $notes = '',
		?bool $isDemo = false,
	) {
		$this->firstName = $firstName;
		$this->lastName  = $lastName;
		$this->setEmail( $email );
		$this->phone     = $phone;
		$this->timezone  = $timezone;
		$this->status    = $status ?? ClientStatus::active();
		$this->notes     = $notes;
		$this->createdAt = CarbonImmutable::now();
		$this->isDemo    = $isDemo ?? false;
	}

	/**
	 * Reconstitute a Client from persistence data.
	 */
	public static function reconstitute(
		int $id,
		string $firstName,
		string $lastName,
		string $email,
		string $phone,
		string $timezone,
		ClientStatus $status,
		string $notes,
		CarbonImmutable $createdAt,
		?CarbonImmutable $updatedAt = null,
		?bool $isDemo = false,
	): self {
		$client            = new self( $firstName, $lastName, $email, $phone, $timezone, $status, $notes, $isDemo );
		$client->id        = $id;
		$client->createdAt = $createdAt;
		$client->updatedAt = $updatedAt;
		return $client;
	}

	public function getFirstName(): string {
		return $this->firstName;
	}

	public function setFirstName( string $firstName ): void {
		$this->firstName = $firstName;
		$this->touch();
	}

	public function getLastName(): string {
		return $this->lastName;
	}

	public function setLastName( string $lastName ): void {
		$this->lastName = $lastName;
		$this->touch();
	}

	public function getName(): string {
		return trim( $this->firstName . ' ' . $this->lastName );
	}

	public function getEmail(): string {
		return $this->email;
	}

	public function setEmail( string $email ): void {
		if ( ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
			throw new \InvalidArgumentException( esc_html__( 'Invalid email address', 'allcoach' ) );
		}

		$this->email = $email;
		$this->touch();
	}

	public function getPhone(): string {
		return $this->phone;
	}

	public function setPhone( string $phone ): void {
		$this->phone = $phone;
		$this->touch();
	}

	public function getTimezone(): string {
		return $this->timezone;
	}

	public function setTimezone( string $timezone ): void {
		$this->timezone = $timezone;
		$this->touch();
	}

	public function getStatus(): ClientStatus {
		return $this->status;
	}

	public function setStatus( ClientStatus $status ): void {
		$this->status = $status;
		$this->touch();
	}

	public function getNotes(): string {
		return $this->notes;
	}

	public function setNotes( string $notes ): void {
		$this->notes = $notes;
		$this->touch();
	}

	public function getCreatedAt(): CarbonImmutable {
		return $this->createdAt;
	}

	public function getUpdatedAt(): ?CarbonImmutable {
		return $this->updatedAt;
	}

	public function isActive(): bool {
		return $this->status->equals( ClientStatus::active() );
	}

	public function isDemo(): bool {
		return $this->isDemo;
	}

	public function activate(): void {
		$this->setStatus( ClientStatus::active() );
	}

	public function deactivate(): void {
		$this->setStatus( ClientStatus::inactive() );
	}

	public function suspend(): void {
		$this->setStatus( ClientStatus::suspended() );
	}

	private function touch(): void {
		$this->updatedAt = CarbonImmutable::now();
	}

	public function toArray(): array {
		return [
			'id'         => $this->id,
			'first_name' => $this->firstName,
			'last_name'  => $this->lastName,
			'email'      => $this->email,
			'phone'      => $this->phone,
			'timezone'   => $this->timezone,
			'status'     => $this->status->getValue(),
			'notes'      => $this->notes,
			'created_at' => $this->createdAt->format( 'Y-m-d H:i:s' ),
			'updated_at' => $this->updatedAt?->format( 'Y-m-d H:i:s' ),
			'is_demo'    => $this->isDemo,
		];
	}
}
