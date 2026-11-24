<?php

declare(strict_types=1);

namespace AllCoach\Application\Booking;

defined( 'ABSPATH' ) || exit;

use AllCoach\Domain\Booking\SessionBooking;
use AllCoach\Domain\Booking\SessionBookingFilter;
use AllCoach\Domain\Booking\SessionBookingRepository;

class SessionBookingQueryService {

	private int $totalCount = 0;

	public function __construct(
		private readonly SessionBookingRepository $repository,
	) {}

	public function findById( int $id ): ?SessionBooking {
		return $this->repository->findById( $id );
	}

	/** @return SessionBooking[] */
	public function findByEnrollment( int $enrollmentId ): array {
		return $this->repository->findWithFilter(
			new SessionBookingFilter( enrollmentId: $enrollmentId ),
			limit: 1000,
		);
	}

	/** @return SessionBooking[] */
	public function findWithFilter( SessionBookingFilter $filter, int $page = 1, int $perPage = 50 ): array {
		$offset           = ( $page - 1 ) * $perPage;
		$this->totalCount = $this->repository->countWithFilter( $filter );

		return $this->repository->findWithFilter( $filter, $perPage, $offset );
	}

	public function getTotalCount(): int {
		return $this->totalCount;
	}

	/**
	 * Returns session progress for an enrollment.
	 *
	 * @return array{scheduled: int, completed: int, total: int, remaining: int}
	 */
	public function getProgress( int $enrollmentId, int $totalSessions ): array {
		$scheduled = $this->repository->countWithFilter(
			new SessionBookingFilter( enrollmentId: $enrollmentId, excludeCancelled: true )
		);
		$completed = $this->repository->countWithFilter(
			new SessionBookingFilter( enrollmentId: $enrollmentId, status: 'completed' )
		);

		return [
			'scheduled' => $scheduled,
			'completed' => $completed,
			'total'     => $totalSessions,
			'remaining' => max( 0, $totalSessions - $scheduled ),
		];
	}
}
