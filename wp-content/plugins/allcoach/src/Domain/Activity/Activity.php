<?php

declare(strict_types=1);

namespace AllCoach\Domain\Activity;

defined( 'ABSPATH' ) || exit;

use AllCoach\Domain\Shared\Entity;
use Carbon\CarbonImmutable;

class Activity extends Entity {

	private CarbonImmutable $createdAt;

	public function __construct(
		private int $programId,
		private ActivitySchema $schema,
		private array $settings = [],
		private int $day = 1,
		private int $week = 1,
		private int $position = 0,
		private ?int $sectionId = null,
		private ?int $templateId = null,
	) {
		$this->createdAt = CarbonImmutable::now();
	}

	public function getProgramId(): int {
		return $this->programId;
	}

	public function getTemplateId(): ?int {
		return $this->templateId;
	}

	public function isFromLibrary(): bool {
		return $this->templateId !== null;
	}

	public function getSchema(): ActivitySchema {
		return $this->schema;
	}

	public function setSchema( ActivitySchema $schema ): void {
		$this->schema = $schema;
	}

	public function getSettings(): array {
		return $this->settings;
	}

	public function setSettings( array $settings ): void {
		$this->settings = $settings;
	}

	public function getDay(): int {
		return $this->day;
	}

	public function getWeek(): int {
		return $this->week;
	}

	public function getPosition(): int {
		return $this->position;
	}

	public function getSectionId(): ?int {
		return $this->sectionId;
	}

	public function setSectionId( ?int $sectionId ): void {
		$this->sectionId = $sectionId;
	}

	public function reschedule( int $day, int $week, int $position ): void {
		if ( $day < 1 || $week < 1 ) {
			throw new \InvalidArgumentException( 'Day and week must be positive integers.' );
		}
		$this->day      = $day;
		$this->week     = $week;
		$this->position = $position;
	}

	public function getCreatedAt(): CarbonImmutable {
		return $this->createdAt;
	}

	public static function reconstitute(
		int $id,
		int $programId,
		ActivitySchema $schema,
		array $settings,
		int $day,
		int $week,
		int $position,
		?int $sectionId,
		?int $templateId,
		CarbonImmutable $createdAt
	): self {
		$activity            = new self( $programId, $schema, $settings, $day, $week, $position, $sectionId, $templateId );
		$activity->id        = $id;
		$activity->createdAt = $createdAt;
		return $activity;
	}

	public function toArray(): array {
		return [
			'id'          => $this->id,
			'program_id'  => $this->programId,
			'template_id' => $this->templateId,
			'section_id'  => $this->sectionId,
			'schema'      => $this->schema->toArray(),
			'settings'    => $this->settings,
			'day'         => $this->day,
			'week'        => $this->week,
			'position'    => $this->position,
			'created_at'  => $this->createdAt->format( 'Y-m-d H:i:s' ),
		];
	}
}
