<?php

declare(strict_types=1);

namespace AllCoach\Domain\Session;

defined( 'ABSPATH' ) || exit;

use AllCoach\Domain\Shared\Entity;
use Carbon\CarbonImmutable;

class Session extends Entity {

	private CarbonImmutable $createdAt;

	public function __construct(
		private int $programId,
		private int $durationMinutes,
		private int $repetitionCount = 1,
		private int $position = 0,
	) {
		$this->createdAt = CarbonImmutable::now();
	}

	public function getProgramId(): int {
		return $this->programId;
	}

	public function getDurationMinutes(): int {
		return $this->durationMinutes;
	}

	public function setDurationMinutes( int $durationMinutes ): void {
		if ( $durationMinutes < 0 ) {
			throw new \InvalidArgumentException( 'Duration cannot be negative' );
		}
		$this->durationMinutes = $durationMinutes;
	}

	public function getRepetitionCount(): int {
		return $this->repetitionCount;
	}

	public function setRepetitionCount( int $repetitionCount ): void {
		if ( $repetitionCount < 1 ) {
			throw new \InvalidArgumentException( 'Repetition count must be at least 1' );
		}
		$this->repetitionCount = $repetitionCount;
	}

	public function getPosition(): int {
		return $this->position;
	}

	public function setPosition( int $position ): void {
		$this->position = $position;
	}

	public function getCreatedAt(): CarbonImmutable {
		return $this->createdAt;
	}

	public static function reconstitute(
		int $id,
		int $programId,
		int $durationMinutes,
		int $repetitionCount,
		int $position,
		CarbonImmutable $createdAt
	): self {
		$session            = new self( $programId, $durationMinutes, $repetitionCount, $position );
		$session->id        = $id;
		$session->createdAt = $createdAt;
		return $session;
	}

	public function toArray(): array {
		return [
			'id'               => $this->id,
			'program_id'       => $this->programId,
			'duration_minutes' => $this->durationMinutes,
			'repetition_count' => $this->repetitionCount,
			'position'         => $this->position,
			'created_at'       => $this->createdAt->format( 'Y-m-d H:i:s' ),
		];
	}
}
