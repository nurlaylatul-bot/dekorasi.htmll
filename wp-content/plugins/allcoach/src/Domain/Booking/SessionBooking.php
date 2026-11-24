<?php

declare(strict_types=1);

namespace AllCoach\Domain\Booking;

defined( 'ABSPATH' ) || exit;

use AllCoach\Domain\Shared\Entity;
use Carbon\CarbonImmutable;

class SessionBooking extends Entity {

	private SessionBookingStatus $status;
	private CarbonImmutable $createdAt;
	private ?CarbonImmutable $updatedAt = null;
	private ?string $meetingUrl            = null;
	private ?string $calendarEventId       = null;
	private MeetingType $meetingType;
	private ?string $location = null;

	public function __construct(
		private int $enrollmentId,
		private int $clientId,
		private int $coachId,
		private int $programId,
		private string $scheduledDate,
		private string $startTime,
		private string $endTime,
		private int $sessionNumber,
		private ?string $notes = null,
		private string $timezone = '',
		?MeetingType $meetingType = null,
		?string $location = null,
	) {
		$this->status     = SessionBookingStatus::scheduled();
		$this->createdAt  = CarbonImmutable::now();
		$this->meetingType = $meetingType ?? MeetingType::googleMeet();
		$this->location    = $location;
	}

	public function getEnrollmentId(): int {
		return $this->enrollmentId;
	}

	public function getClientId(): int {
		return $this->clientId;
	}

	public function getCoachId(): int {
		return $this->coachId;
	}

	public function getProgramId(): int {
		return $this->programId;
	}

	public function getScheduledDate(): string {
		return $this->scheduledDate;
	}

	public function getStartTime(): string {
		return $this->startTime;
	}

	public function getEndTime(): string {
		return $this->endTime;
	}

	public function getSessionNumber(): int {
		return $this->sessionNumber;
	}

	public function getTimezone(): string {
		return $this->timezone;
	}

	public function getStatus(): SessionBookingStatus {
		return $this->status;
	}

	public function getNotes(): ?string {
		return $this->notes;
	}

	public function getCreatedAt(): CarbonImmutable {
		return $this->createdAt;
	}

	public function getUpdatedAt(): ?CarbonImmutable {
		return $this->updatedAt;
	}

	public function complete(): void {
		if ( ! $this->status->isScheduled() ) {
			throw new \DomainException( 'Only scheduled sessions can be completed.' );
		}
		$this->status = SessionBookingStatus::completed();
		$this->touch();
	}

	public function cancel(): void {
		if ( $this->status->isCompleted() ) {
			throw new \DomainException( 'Cannot cancel a completed session.' );
		}
		$this->status = SessionBookingStatus::cancelled();
		$this->touch();
	}

	public function markNoShow(): void {
		if ( ! $this->status->isScheduled() ) {
			throw new \DomainException( 'Only scheduled sessions can be marked as no-show.' );
		}
		$this->status = SessionBookingStatus::noShow();
		$this->touch();
	}

	public function reschedule( string $date, string $startTime, string $endTime ): void {
		if ( $this->status->isCompleted() ) {
			throw new \DomainException( 'Cannot reschedule a completed session.' );
		}
		$this->scheduledDate = $date;
		$this->startTime     = $startTime;
		$this->endTime       = $endTime;
		$this->status        = SessionBookingStatus::scheduled();
		$this->touch();
	}

	public function setNotes( ?string $notes ): void {
		$this->notes = $notes;
		$this->touch();
	}

	public static function reconstitute(
		int $id,
		int $enrollmentId,
		int $clientId,
		int $coachId,
		int $programId,
		string $scheduledDate,
		string $startTime,
		string $endTime,
		int $sessionNumber,
		SessionBookingStatus $status,
		?string $notes,
		?string $meetingUrl,
		?string $calendarEventId,
		CarbonImmutable $createdAt,
		?CarbonImmutable $updatedAt,
		string $timezone = '',
		?MeetingType $meetingType = null,
		?string $location = null,
	): self {
		$booking                  = new self( $enrollmentId, $clientId, $coachId, $programId, $scheduledDate, $startTime, $endTime, $sessionNumber, $notes, $timezone, $meetingType, $location );
		$booking->id              = $id;
		$booking->status          = $status;
		$booking->meetingUrl      = $meetingUrl;
		$booking->calendarEventId = $calendarEventId;
		$booking->createdAt       = $createdAt;
		$booking->updatedAt       = $updatedAt;
		return $booking;
	}

	public function getMeetingUrl(): ?string {
		return $this->meetingUrl;
	}

	public function setMeetingUrl( ?string $meetingUrl ): void {
		$this->meetingUrl = $meetingUrl;
	}

	public function getCalendarEventId(): ?string {
		return $this->calendarEventId;
	}

	public function setCalendarEventId( ?string $calendarEventId ): void {
		$this->calendarEventId = $calendarEventId;
	}

	public function getMeetingType(): MeetingType {
		return $this->meetingType;
	}

	public function getLocation(): ?string {
		return $this->location;
	}

	private function touch(): void {
		$this->updatedAt = CarbonImmutable::now();
	}

	public function toArray(): array {
		return [
			'id'                => $this->id,
			'enrollment_id'     => $this->enrollmentId,
			'client_id'         => $this->clientId,
			'coach_id'          => $this->coachId,
			'program_id'        => $this->programId,
			'scheduled_date'    => $this->scheduledDate,
			'start_time'        => $this->startTime,
			'end_time'          => $this->endTime,
			'session_number'    => $this->sessionNumber,
			'status'            => $this->status->getValue(),
			'notes'             => $this->notes,
			'timezone'          => $this->timezone,
			'meeting_url'       => $this->meetingUrl,
			'calendar_event_id' => $this->calendarEventId,
			'meeting_type'      => $this->meetingType->getValue(),
			'location'          => $this->location,
			'created_at'        => $this->createdAt->format( 'Y-m-d H:i:s' ),
			'updated_at'        => $this->updatedAt?->format( 'Y-m-d H:i:s' ),
		];
	}
}
