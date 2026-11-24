<?php

declare(strict_types=1);

namespace AllCoach\Application\Booking\Pipes;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\Exceptions\ValidationException;
use AllCoach\Domain\Booking\SessionBookingRepository;

class ValidateNoCoachOverlap {

	public function __construct(
		private readonly SessionBookingRepository $sessionBookingRepository,
	) {}

	public function handle( BookingContext $ctx, \Closure $next ): mixed {
		$dto = $ctx->convertedDto;

		if ( $this->sessionBookingRepository->hasOverlap( $ctx->coachId, $dto->scheduledDate, $dto->startTime, $dto->endTime ) ) {
			throw ValidationException::withErrors(
				[ 'overlap' => esc_html__( 'This time slot overlaps with another session for this coach.', 'allcoach' ) ]
			);
		}

		return $next( $ctx );
	}
}
