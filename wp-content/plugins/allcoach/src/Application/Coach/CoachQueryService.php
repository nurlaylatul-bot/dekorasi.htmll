<?php

declare(strict_types=1);

namespace AllCoach\Application\Coach;

defined( 'ABSPATH' ) || exit;

use AllCoach\Domain\Coach\Coach;
use AllCoach\Domain\Coach\CoachFilter;
use AllCoach\Domain\Coach\CoachRepository;

class CoachQueryService {

	private int $totalCount = 0;

	public function __construct(
		private readonly CoachRepository $repository
	) {
	}

	public function findById( int $id ): ?Coach {
		return $this->repository->findById( $id );
	}

	public function findByEmail( string $email ): ?Coach {
		return $this->repository->findByEmail( $email );
	}

	/** @return array<Coach> */
	public function getAllCoaches( int $page = 1, int $perPage = 20 ): array {
		return $this->findWithFilter( new CoachFilter(), $page, $perPage );
	}

	/** @return array<Coach> */
	public function search( string $query, int $page = 1, int $perPage = 20 ): array {
		return $this->findWithFilter( new CoachFilter( search: $query ), $page, $perPage );
	}

	/**
	 * @return array<Coach>
	 */
	public function findWithFilter( CoachFilter $filter, int $page = 1, int $perPage = 20 ): array {
		$offset = ( $page - 1 ) * $perPage;

		if ( $filter->search !== null && $filter->search !== '' ) {
			$this->totalCount = $this->repository->searchCount( $filter->search );
			return $this->repository->search( $filter->search, $perPage, $offset );
		}

		$this->totalCount = $this->repository->count();
		return $this->repository->findAll( $perPage, $offset );
	}

	public function getTotalCount(): int {
		return $this->totalCount;
	}
}
