<?php

declare(strict_types=1);

namespace AllCoach\Domain\Section;

defined( 'ABSPATH' ) || exit;

use AllCoach\Domain\Shared\Entity;
use Carbon\CarbonImmutable;

class Section extends Entity {

	private CarbonImmutable $createdAt;

	public function __construct(
		private int $programId,
		private string $title,
		private string $description = '',
		private int $position = 0,
	) {
		$this->createdAt = CarbonImmutable::now();
	}

	public function getProgramId(): int {
		return $this->programId;
	}

	public function getTitle(): string {
		return $this->title;
	}

	public function setTitle( string $title ): void {
		$this->title = $title;
	}

	public function getDescription(): string {
		return $this->description;
	}

	public function setDescription( string $description ): void {
		$this->description = $description;
	}

	public function getPosition(): int {
		return $this->position;
	}

	public function setPosition( int $position ): void {
		$this->position = max( 0, $position );
	}

	public function getCreatedAt(): CarbonImmutable {
		return $this->createdAt;
	}

	public static function reconstitute(
		int $id,
		int $programId,
		string $title,
		string $description,
		int $position,
		CarbonImmutable $createdAt
	): self {
		$section            = new self( $programId, $title, $description, $position );
		$section->id        = $id;
		$section->createdAt = $createdAt;
		return $section;
	}

	public function toArray(): array {
		return [
			'id'          => $this->id,
			'program_id'  => $this->programId,
			'title'       => $this->title,
			'description' => $this->description,
			'position'    => $this->position,
			'created_at'  => $this->createdAt->format( 'Y-m-d H:i:s' ),
		];
	}
}
