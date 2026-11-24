<?php

declare(strict_types=1);

namespace AllCoach\Application\Availability;

defined( 'ABSPATH' ) || exit;

use AllCoach\Domain\Availability\Availability;
use AllCoach\Domain\Availability\AvailabilityRepository;
use AllCoach\Domain\Availability\WeeklySchedule;

class AvailabilityQueryService {

	public function __construct(
		private readonly AvailabilityRepository $repository,
	) {}

	public static function defaultAvailability( int $userId ): Availability {
		return new Availability(
			userId: $userId,
			timezone: 'UTC',
			startDate: wp_date( 'Y-m-d' ),
			schedule: WeeklySchedule::default(),
		);
	}

	public function findById( int $id ): ?Availability {
		return $this->repository->findById( $id );
	}

	/**
	 * @return Availability[]
	 */
	public function findByUser( int $userId, int $page = 1, int $perPage = 50 ): array {
		$offset = ( $page - 1 ) * $perPage;
		return $this->repository->findByUser( $userId, $perPage, $offset );
	}

	/**
	 * @return Availability[]
	 */
	public function findByUserOrDefault( int $userId, int $page = 1, int $perPage = 50 ): array {
		$availabilities = $this->findByUser( $userId, $page, $perPage );

		if ( empty( $availabilities ) ) {
			return [ self::defaultAvailability( $userId ) ];
		}

		return $availabilities;
	}

	public function countByUser( int $userId ): int {
		return $this->repository->countByUser( $userId );
	}
}
