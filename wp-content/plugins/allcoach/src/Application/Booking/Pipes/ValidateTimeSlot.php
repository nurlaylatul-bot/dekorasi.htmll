<?php

declare(strict_types=1);

namespace AllCoach\Application\Booking\Pipes;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\Exceptions\ValidationException;
use Carbon\CarbonImmutable;

class ValidateTimeSlot {

	public function handle( BookingContext $ctx, \Closure $next ): mixed {
		$dto  = $ctx->convertedDto;
		$date = CarbonImmutable::createFromFormat( 'Y-m-d', $dto->scheduledDate );

		if ( ! $date ) {
			throw ValidationException::withErrors(
				[ 'scheduled_date' => esc_html__( 'Invalid date format. Use YYYY-MM-DD.', 'allcoach' ) ]
			);
		}

		$now = CarbonImmutable::now( $date->getTimezone() );
		if ( $date->format( 'Y-m-d' ) < $now->format( 'Y-m-d' ) ) {
			throw ValidationException::withErrors(
				[ 'scheduled_date' => esc_html__( 'Cannot book sessions in the past.', 'allcoach' ) ]
			);
		}

		if ( $dto->startTime >= $dto->endTime ) {
			throw ValidationException::withErrors(
				[ 'start_time' => esc_html__( 'Start time must be before end time.', 'allcoach' ) ]
			);
		}

		return $next( $ctx );
	}
}
