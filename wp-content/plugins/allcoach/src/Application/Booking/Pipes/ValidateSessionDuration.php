<?php

declare(strict_types=1);

namespace AllCoach\Application\Booking\Pipes;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\Exceptions\ValidationException;
use Carbon\CarbonImmutable;

class ValidateSessionDuration {

	public function handle( BookingContext $ctx, \Closure $next ): mixed {
		$expectedMinutes = $ctx->session->getDurationMinutes();

		if ( $expectedMinutes <= 0 ) {
			return $next( $ctx );
		}

		$dto    = $ctx->convertedDto;
		$start  = CarbonImmutable::parse( $dto->startTime );
		$end    = CarbonImmutable::parse( $dto->endTime );
		$actual = $start && $end ? ( $end->getTimestamp() - $start->getTimestamp() ) / 60 : 0;

		if ( abs( $actual - $expectedMinutes ) > 1 ) {
			throw ValidationException::withErrors(
				[
					'duration' => sprintf(
						/* translators: %d: expected duration in minutes */
						esc_html__( 'Session duration must be %d minutes.', 'allcoach' ),
						absint( $expectedMinutes )
					),
				]
			);
		}

		return $next( $ctx );
	}
}
