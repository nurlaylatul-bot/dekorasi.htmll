<?php

declare(strict_types=1);

namespace AllCoach\Domain\Activity;

defined( 'ABSPATH' ) || exit;

use AllCoach\Domain\Shared\Entity;
use Carbon\CarbonImmutable;

class ActivityCompletion extends Entity {

	private CarbonImmutable $completedAt;
	private CarbonImmutable $createdAt;

	public function __construct(
		private int $enrollmentId,
		private int $clientId,
		private int $activityId,
		private int $programId,
		private array $responseData = [],
	) {
		$this->completedAt = CarbonImmutable::now();
		$this->createdAt   = CarbonImmutable::now();
	}

	public function getEnrollmentId(): int {
		return $this->enrollmentId;
	}

	public function getClientId(): int {
		return $this->clientId;
	}

	public function getActivityId(): int {
		return $this->activityId;
	}

	public function getProgramId(): int {
		return $this->programId;
	}

	public function getResponseData(): array {
		return $this->responseData;
	}

	public function setResponseData( array $responseData ): void {
		$this->responseData = $responseData;
	}

	public function getCompletedAt(): CarbonImmutable {
		return $this->completedAt;
	}

	public function setCompletedAt( CarbonImmutable $completedAt ): void {
		$this->completedAt = $completedAt;
	}

	public function getCreatedAt(): CarbonImmutable {
		return $this->createdAt;
	}

	public static function reconstitute(
		int $id,
		int $enrollmentId,
		int $clientId,
		int $activityId,
		int $programId,
		array $responseData,
		CarbonImmutable $completedAt,
		CarbonImmutable $createdAt
	): self {
		$completion              = new self( $enrollmentId, $clientId, $activityId, $programId, $responseData );
		$completion->id          = $id;
		$completion->completedAt = $completedAt;
		$completion->createdAt   = $createdAt;
		return $completion;
	}

	public function toArray(): array {
		return [
			'id'            => $this->id,
			'enrollment_id' => $this->enrollmentId,
			'client_id'     => $this->clientId,
			'activity_id'   => $this->activityId,
			'program_id'    => $this->programId,
			'response_data' => $this->responseData,
			'completed_at'  => $this->completedAt->format( 'Y-m-d H:i:s' ),
			'created_at'    => $this->createdAt->format( 'Y-m-d H:i:s' ),
		];
	}
}
