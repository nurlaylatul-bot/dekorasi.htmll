<?php

declare(strict_types=1);

namespace AllCoach\Application\Booking;

defined( 'ABSPATH' ) || exit;

class SessionBookingDTO {

	public function __construct(
		public readonly int $enrollmentId,
		public readonly string $scheduledDate,
		public readonly string $startTime,
		public readonly string $endTime,
		public readonly ?string $notes = null,
		public readonly ?string $clientTimezone = null,
	) {}

	public static function fromArray( array $data ): self {
		return new self(
			enrollmentId: (int) ( $data['enrollment_id'] ?? 0 ),
			scheduledDate: $data['scheduled_date'] ?? '',
			startTime: $data['start_time'] ?? '',
			endTime: $data['end_time'] ?? '',
			notes: $data['notes'] ?? null,
			clientTimezone: ! empty( $data['timezone'] ) ? $data['timezone'] : null,
		);
	}

	public function toArray(): array {
		return [
			'enrollment_id'  => $this->enrollmentId,
			'scheduled_date' => $this->scheduledDate,
			'start_time'     => $this->startTime,
			'end_time'       => $this->endTime,
			'notes'          => $this->notes,
			'timezone'       => $this->clientTimezone,
		];
	}
}
