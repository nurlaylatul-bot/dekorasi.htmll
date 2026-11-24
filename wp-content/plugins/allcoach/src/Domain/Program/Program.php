<?php

declare(strict_types=1);

namespace AllCoach\Domain\Program;

defined( 'ABSPATH' ) || exit;

use AllCoach\Domain\Booking\MeetingType;
use AllCoach\Domain\Shared\Entity;
use Carbon\CarbonImmutable;


class Program extends Entity {

	private string $title;
	private string $description;
	private ProgramDuration $duration;
	private float $price;
	private int $maxEnrollments;
	private bool $isActive;
	private ?int $coachId = null;
	private CarbonImmutable $createdAt;
	private ?CarbonImmutable $updatedAt = null;
	private ?int $thumbnailId           = null;
	private MeetingType $meetingType;
	private ?string $customMeetingUrl = null;
	private ?string $meetingLocation  = null;

	public function __construct(
		string $title,
		string $description,
		ProgramDuration $duration,
		float $price,
		int $maxEnrollments = 0,
		bool $isActive = true,
		?int $coachId = null,
		?int $thumbnailId = null,
		?MeetingType $meetingType = null,
		?string $customMeetingUrl = null,
		?string $meetingLocation = null
	) {
		$this->setTitle( $title );
		$this->description = $description;
		$this->duration    = $duration;
		$this->setPrice( $price );
		$this->setMaxEnrollments( $maxEnrollments );
		$this->isActive    = $isActive;
		$this->coachId     = $coachId;
		$this->createdAt   = CarbonImmutable::now();
		$this->thumbnailId = $thumbnailId;
		$this->setMeeting( $meetingType ?? MeetingType::googleMeet(), $customMeetingUrl, $meetingLocation );
	}

	public function getCoachId(): ?int {
		return $this->coachId;
	}

	public function setCoachId( ?int $coachId ): void {
		$this->coachId = $coachId;
		$this->touch();
	}

	public function getTitle(): string {
		return $this->title;
	}

	public function setTitle( string $title ): void {
		if ( empty( $title ) ) {
			throw new \InvalidArgumentException( 'Program title cannot be empty' );
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

	public function getDuration(): ProgramDuration {
		return $this->duration;
	}

	public function setDuration( ProgramDuration $duration ): void {
		$this->duration = $duration;
		$this->touch();
	}

	public function getPrice(): float {
		return $this->price;
	}

	public function setPrice( float $amount ): void {
		if ( $amount < 0 ) {
			throw new \InvalidArgumentException( 'Price cannot be negative' );
		}
		$this->price = round( $amount, 2 );
		$this->touch();
	}

	public function getMaxEnrollments(): int {
		return $this->maxEnrollments;
	}

	public function setMaxEnrollments( int $maxEnrollments ): void {
		if ( $maxEnrollments < 0 ) {
			throw new \InvalidArgumentException( 'Max enrollments cannot be negative' );
		}
		$this->maxEnrollments = $maxEnrollments;
		$this->touch();
	}

	public function isActive(): bool {
		return $this->isActive;
	}

	public function activate(): void {
		$this->isActive = true;
		$this->touch();
	}

	public function deactivate(): void {
		$this->isActive = false;
		$this->touch();
	}

	public function hasEnrollmentLimit(): bool {
		return $this->maxEnrollments > 0;
	}

	public function isFree(): bool {
		return $this->price === 0.0;
	}

	public function getCreatedAt(): CarbonImmutable {
		return $this->createdAt;
	}

	public static function reconstitute(
		int $id,
		string $title,
		string $description,
		ProgramDuration $duration,
		float $price,
		int $maxEnrollments,
		bool $isActive,
		?int $coachId,
		?int $thumbnailId,
		CarbonImmutable $createdAt,
		?CarbonImmutable $updatedAt,
		?MeetingType $meetingType = null,
		?string $customMeetingUrl = null,
		?string $meetingLocation = null
	): self {
		$program            = new self( $title, $description, $duration, $price, $maxEnrollments, $isActive, $coachId, $thumbnailId, $meetingType, $customMeetingUrl, $meetingLocation );
		$program->id        = $id;
		$program->createdAt = $createdAt;
		$program->updatedAt = $updatedAt;
		return $program;
	}

	public function getUpdatedAt(): ?CarbonImmutable {
		return $this->updatedAt;
	}

	private function touch(): void {
		$this->updatedAt = CarbonImmutable::now();
	}

	public function setThumbnailId( ?int $thumbnailId ): void {
		$this->thumbnailId = $thumbnailId;
		$this->touch();
	}

	public function getThumbnailId(): ?int {
		return $this->thumbnailId;
	}

	public function getMeetingType(): MeetingType {
		return $this->meetingType;
	}

	public function getCustomMeetingUrl(): ?string {
		return $this->customMeetingUrl;
	}

	public function getMeetingLocation(): ?string {
		return $this->meetingLocation;
	}

	public function setMeeting( MeetingType $meetingType, ?string $customMeetingUrl, ?string $meetingLocation ): void {
		if ( $meetingType->isCustomUrl() && empty( $customMeetingUrl ) ) {
			throw new \InvalidArgumentException( 'Custom meeting URL is required for the custom_url meeting type.' );
		}
		if ( $meetingType->isFaceToFace() && empty( $meetingLocation ) ) {
			throw new \InvalidArgumentException( 'Meeting location is required for the face_to_face meeting type.' );
		}

		$this->meetingType       = $meetingType;
		$this->customMeetingUrl  = $meetingType->isCustomUrl() ? $customMeetingUrl : null;
		$this->meetingLocation   = $meetingType->isFaceToFace() ? $meetingLocation : null;
		$this->touch();
	}

	public function toArray(): array {
		return [
			'id'                 => $this->id,
			'title'              => $this->title,
			'description'        => $this->description,
			'duration'           => $this->duration->toArray(),
			'price'              => [
				'amount'  => $this->price,
				'is_free' => $this->isFree(),
			],
			'max_enrollments'    => $this->maxEnrollments,
			'is_active'          => $this->isActive(),
			'coach_id'           => $this->coachId,
			'meeting_type'       => $this->meetingType->getValue(),
			'custom_meeting_url' => $this->customMeetingUrl,
			'meeting_location'   => $this->meetingLocation,
			'created_at'         => $this->createdAt->format( 'Y-m-d H:i:s' ),
			'updated_at'         => $this->updatedAt?->format( 'Y-m-d H:i:s' ),
		];
	}
}
