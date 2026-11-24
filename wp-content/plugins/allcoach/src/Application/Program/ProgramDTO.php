<?php

declare(strict_types=1);

namespace AllCoach\Application\Program;

defined( 'ABSPATH' ) || exit;

class ProgramDTO {

	public function __construct(
		public readonly ?int $id,
		public readonly string $title,
		public readonly string $description,
		public readonly int $durationValue,
		public readonly string $durationUnit,
		public readonly float $priceAmount,
		public readonly int $maxEnrollments,
		public readonly bool $isActive,
		public readonly ?int $coachId = null,
		public readonly ?int $thumbnail = null,
		public readonly string $meetingType = 'google_meet',
		public readonly ?string $customMeetingUrl = null,
		public readonly ?string $meetingLocation = null
	) {}

	public static function fromArray( array $data ): self {
		return new self(
			id: isset( $data['id'] ) ? (int) $data['id'] : null,
			title: $data['title'] ?? '',
			description: $data['description'] ?? '',
			durationValue: (int) ( $data['duration_value'] ?? $data['duration'] ?? 0 ),
			durationUnit: $data['duration_unit'] ?? 'weeks',
			priceAmount: (float) ( $data['price_amount'] ?? $data['price'] ?? 0.0 ),
			maxEnrollments: (int) ( $data['max_enrollments'] ?? 0 ),
			isActive: (bool) ( $data['is_active'] ?? true ),
			coachId: isset( $data['coach_id'] ) && $data['coach_id'] !== '' ? (int) $data['coach_id'] : null,
			thumbnail: isset( $data['thumbnail'] ) ? (int) $data['thumbnail'] : null,
			meetingType: $data['meeting_type'] ?? 'google_meet',
			customMeetingUrl: $data['custom_meeting_url'] ?? null,
			meetingLocation: $data['meeting_location'] ?? null
		);
	}

	public function toArray(): array {
		return [
			'id'                 => $this->id,
			'title'              => $this->title,
			'description'        => $this->description,
			'duration_value'     => $this->durationValue,
			'duration_unit'      => $this->durationUnit,
			'price_amount'       => $this->priceAmount,
			'max_enrollments'    => $this->maxEnrollments,
			'is_active'          => $this->isActive,
			'coach_id'           => $this->coachId,
			'thumbnail'          => $this->thumbnail,
			'meeting_type'       => $this->meetingType,
			'custom_meeting_url' => $this->customMeetingUrl,
			'meeting_location'   => $this->meetingLocation,
		];
	}
}
