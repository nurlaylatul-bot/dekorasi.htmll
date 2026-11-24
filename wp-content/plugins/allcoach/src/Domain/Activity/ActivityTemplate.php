<?php

declare(strict_types=1);

namespace AllCoach\Domain\Activity;

defined( 'ABSPATH' ) || exit;

use AllCoach\Domain\Shared\Entity;
use Carbon\CarbonImmutable;

class ActivityTemplate extends Entity {

	private CarbonImmutable $createdAt;
	private ?CarbonImmutable $updatedAt = null;

	public function __construct(
		private string $title,
		private string $description,
		private ActivitySchema $schema,
		private ?int $coachId = null,
		private bool $isPublished = false,
	) {
		$this->createdAt = CarbonImmutable::now();
	}

	public function getTitle(): string {
		return $this->title;
	}

	public function setTitle( string $title ): void {
		if ( empty( trim( $title ) ) ) {
			throw new \InvalidArgumentException( 'Activity title cannot be empty.' );
		}
		$this->title = $title;
		$this->touch();
	}

	public function getDescription(): string {
		return $this->description;
	}

	public function setDescription( string $description ): void {
		$this->description = $description;
		$this->touch();
	}

	public function getSchema(): ActivitySchema {
		return $this->schema;
	}

	public function setSchema( ActivitySchema $schema ): void {
		$this->schema = $schema;
		$this->touch();
	}

	public function getCoachId(): ?int {
		return $this->coachId;
	}

	public function isPublished(): bool {
		return $this->isPublished;
	}

	public function publish(): void {
		$this->isPublished = true;
		$this->touch();
	}

	public function unpublish(): void {
		$this->isPublished = false;
		$this->touch();
	}

	public function getCreatedAt(): CarbonImmutable {
		return $this->createdAt;
	}

	public static function reconstitute(
		int $id,
		string $title,
		string $description,
		ActivitySchema $schema,
		?int $coachId,
		bool $isPublished,
		CarbonImmutable $createdAt,
		?CarbonImmutable $updatedAt
	): self {
		$template            = new self( $title, $description, $schema, $coachId, $isPublished );
		$template->id        = $id;
		$template->createdAt = $createdAt;
		$template->updatedAt = $updatedAt;
		return $template;
	}

	public function getUpdatedAt(): ?CarbonImmutable {
		return $this->updatedAt;
	}

	public function toArray(): array {
		return [
			'id'           => $this->id,
			'title'        => $this->title,
			'description'  => $this->description,
			'schema'       => $this->schema->toArray(),
			'coach_id'     => $this->coachId,
			'is_published' => $this->isPublished,
			'created_at'   => $this->createdAt->format( 'Y-m-d H:i:s' ),
			'updated_at'   => $this->updatedAt?->format( 'Y-m-d H:i:s' ),
		];
	}

	private function touch(): void {
		$this->updatedAt = CarbonImmutable::now();
	}
}
