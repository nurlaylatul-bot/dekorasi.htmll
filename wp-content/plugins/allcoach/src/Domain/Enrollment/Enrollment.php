<?php

declare(strict_types=1);

namespace AllCoach\Domain\Enrollment;

defined( 'ABSPATH' ) || exit;

use AllCoach\Domain\Shared\Entity;
use Carbon\CarbonImmutable;

class Enrollment extends Entity {

	private EnrollmentStatus $status;
	private CarbonImmutable $createdAt;

	public function __construct(
		private int $userId,
		private int $programId,
		private int $orderItemId,
		private CarbonImmutable $enrolledAt,
		private ?CarbonImmutable $expiresAt = null
	) {
		$this->status    = EnrollmentStatus::active();
		$this->createdAt = CarbonImmutable::now();
	}

	public static function reconstitute(
		int $id,
		int $userId,
		int $programId,
		int $orderItemId,
		EnrollmentStatus $status,
		CarbonImmutable $enrolledAt,
		?CarbonImmutable $expiresAt,
		CarbonImmutable $createdAt,
	): self {
		$enrollment            = new self( $userId, $programId, $orderItemId, $enrolledAt, $expiresAt );
		$enrollment->id        = $id;
		$enrollment->status    = $status;
		$enrollment->createdAt = $createdAt;
		return $enrollment;
	}

	public function getUserId(): int {
		return $this->userId;
	}

	public function getProgramId(): int {
		return $this->programId;
	}

	public function getOrderItemId(): int {
		return $this->orderItemId;
	}

	public function getStatus(): EnrollmentStatus {
		return $this->status;
	}

	public function getEnrolledAt(): CarbonImmutable {
		return $this->enrolledAt;
	}

	public function getExpiresAt(): ?CarbonImmutable {
		return $this->expiresAt;
	}

	public function getCreatedAt(): CarbonImmutable {
		return $this->createdAt;
	}

	public function cancel(): void {
		$this->status = EnrollmentStatus::cancelled();
	}

	public function reactivate(): void {
		$this->status = EnrollmentStatus::active();
	}

	public function expire(): void {
		$this->status = EnrollmentStatus::expired();
	}

	public function complete(): void {
		$this->status = EnrollmentStatus::completed();
	}

	public function isActive(): bool {
		return $this->status->isActive();
	}

	public function toArray(): array {
		return [
			'id'            => $this->id,
			'user_id'       => $this->userId,
			'program_id'    => $this->programId,
			'order_item_id' => $this->orderItemId,
			'status'        => $this->status->getValue(),
			'enrolled_at'   => $this->enrolledAt->format( 'Y-m-d H:i:s' ),
			'expires_at'    => $this->expiresAt?->format( 'Y-m-d H:i:s' ),
			'created_at'    => $this->createdAt->format( 'Y-m-d H:i:s' ),
		];
	}
}
