<?php

declare(strict_types=1);

namespace AllCoach\Application\Booking\Pipes;

defined( 'ABSPATH' ) || exit;

use AllCoach\Application\Availability\AvailabilityQueryService;
use AllCoach\Core\Exceptions\ValidationException;
use AllCoach\Domain\Availability\Availability;
use Carbon\CarbonImmutable;

class ValidateCoachAvailability {

	public function __construct(
		private readonly AvailabilityQueryService $availabilityQueryService,
	) {}

	public function handle( BookingContext $ctx, \Closure $next ): mixed {
		$dto            = $ctx->convertedDto;
		$availabilities = $this->availabilityQueryService->findByUserOrDefault( $ctx->coachId );
		$requestedDate  = CarbonImmutable::createFromFormat( 'Y-m-d', $dto->scheduledDate );
		$dayOfWeek      = (int) $requestedDate->format( 'w' );

		$isWithinAvailability = false;

		foreach ( $availabilities as $availability ) {
			if ( $this->slotFits( $availability, $dto->startTime, $dto->endTime, $requestedDate, $dayOfWeek ) ) {
				$isWithinAvailability = true;
				break;
			}
		}

		if ( ! $isWithinAvailability ) {
			throw ValidationException::withErrors(
				[ 'availability' => esc_html__( 'The requested time slot is outside the coach\'s availability.', 'allcoach' ) ]
			);
		}

		return $next( $ctx );
	}

	private function slotFits(
		Availability $availability,
		string $startTime,
		string $endTime,
		CarbonImmutable $requestedDate,
		int $dayOfWeek
	): bool {
		$availStartDate = CarbonImmutable::createFromFormat( 'Y-m-d', $availability->getStartDate() );
		if ( ! $availStartDate ) {
			return false;
		}

		if ( $requestedDate->setTime( 0, 0 ) < $availStartDate->setTime( 0, 0 ) ) {
			return false;
		}

		return $availability->slotFits( $dayOfWeek, $startTime, $endTime );
	}
}
