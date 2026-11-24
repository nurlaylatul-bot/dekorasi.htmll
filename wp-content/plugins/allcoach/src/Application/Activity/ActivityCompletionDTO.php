<?php

declare(strict_types=1);

namespace AllCoach\Application\Activity;

defined( 'ABSPATH' ) || exit;

class ActivityCompletionDTO {

	public function __construct(
		public readonly int $enrollmentId,
		public readonly int $activityId,
		public readonly array $responseData = [],
	) {}

	public static function fromArray( array $data ): self {
		$responseData = $data['response_data'] ?? $data['response'] ?? [];
		if ( is_string( $responseData ) ) {
			$responseData = (array) json_decode( $responseData, true );
		}

		return new self(
			enrollmentId: (int) ( $data['enrollment_id'] ?? 0 ),
			activityId: (int) ( $data['activity_id'] ?? 0 ),
			responseData: is_array( $responseData ) ? $responseData : [],
		);
	}

	public function toArray(): array {
		return [
			'enrollment_id' => $this->enrollmentId,
			'activity_id'   => $this->activityId,
			'response_data' => $this->responseData,
		];
	}
}
