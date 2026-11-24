<?php

declare(strict_types=1);

namespace AllCoach\Domain\Availability;

defined( 'ABSPATH' ) || exit;

use AllCoach\Domain\Shared\Entity;
use Carbon\CarbonImmutable;

class Availability extends Entity {

	private CarbonImmutable $createdAt;

	public function __construct(
		private int $userId,
		private string $timezone,
		private string $startDate,
		private WeeklySchedule $schedule,
		private bool $isProgramSpecific = false,
		/** @var int[] */
		private array $programIds = [],
	) {
		$this->createdAt = CarbonImmutable::now();
	}

	public function getUserId(): int {
		return $this->userId;
	}

	public function setUserId( int $userId ): void {
		$this->userId = $userId;
	}

	public function getTimezone(): string {
		return $this->timezone;
	}

	public function setTimezone( string $timezone ): void {
		$this->timezone = $timezone;
	}

	public function getStartDate(): string {
		return $this->startDate;
	}

	public function setStartDate( string $startDate ): void {
		$this->startDate = $startDate;
	}

	public function getSchedule(): WeeklySchedule {
		return $this->schedule;
	}

	public function setSchedule( WeeklySchedule $schedule ): void {
		$this->schedule = $schedule;
	}

	public function isProgramSpecific(): bool {
		return $this->isProgramSpecific;
	}

	public function setIsProgramSpecific( bool $isProgramSpecific ): void {
		$this->isProgramSpecific = $isProgramSpecific;
	}

	/** @return int[] */
	public function getProgramIds(): array {
		return $this->programIds;
	}

	/** @param int[] $programIds */
	public function setProgramIds( array $programIds ): void {
		$this->programIds = array_values( array_map( 'intval', array_filter( $programIds ) ) );
	}

	public function getCreatedAt(): CarbonImmutable {
		return $this->createdAt;
	}

	public static function reconstitute(
		int $id,
		int $userId,
		string $timezone,
		string $startDate,
		WeeklySchedule $schedule,
		bool $isProgramSpecific,
		array $programIds,
		CarbonImmutable $createdAt
	): self {
		$availability            = new self( $userId, $timezone, $startDate, $schedule, $isProgramSpecific, $programIds );
		$availability->id        = $id;
		$availability->createdAt = $createdAt;
		return $availability;
	}

	/**
	 * Check if a time slot fits within this availability.
	 */
	public function slotFits( int $dayOfWeek, string $startTime, string $endTime ): bool {
		return $this->schedule->slotFitsDay( $dayOfWeek, $startTime, $endTime );
	}

	public function toArray(): array {
		return [
			'id'                  => $this->id,
			'user_id'             => $this->userId,
			'timezone'            => $this->timezone,
			'start_date'          => $this->startDate,
			'schedule'            => $this->schedule->toArray(),
			'is_program_specific' => $this->isProgramSpecific,
			'program_ids'         => $this->programIds,
			'created_at'          => $this->createdAt->format( 'Y-m-d H:i:s' ),
		];
	}
}
