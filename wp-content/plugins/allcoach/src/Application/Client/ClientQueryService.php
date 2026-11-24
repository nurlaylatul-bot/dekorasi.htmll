<?php

declare(strict_types=1);

namespace AllCoach\Application\Client;

defined( 'ABSPATH' ) || exit;

use AllCoach\Domain\Client\Client;
use AllCoach\Domain\Client\ClientFilter;
use AllCoach\Domain\Client\ClientRepository;
use AllCoach\Domain\Client\ClientStatus;
use Carbon\CarbonImmutable;

class ClientQueryService {

	private int $totalCount = 0;

	public function __construct(
		private readonly ClientRepository $repository,
	) {}

	public function findById( int $id ): ?Client {
		return $this->repository->findById( $id );
	}

	public function findByEmail( string $email ): ?Client {
		return $this->repository->findByEmail( $email );
	}

	/** @return Client[] */
	public function findWithFilter( ClientFilter $filter, int $page = 1, int $perPage = 20 ): array {
		$offset           = ( $page - 1 ) * $perPage;
		$this->totalCount = $this->repository->countWithFilter( $filter );

		return $this->repository->findWithFilter( $filter, $perPage, $offset );
	}

	/** @return Client[] */
	public function getAll( int $page = 1, int $perPage = 20 ): array {
		return $this->findWithFilter( new ClientFilter(), $page, $perPage );
	}

	/** @return Client[] */
	public function getActive( int $page = 1, int $perPage = 20 ): array {
		return $this->findWithFilter( new ClientFilter( status: ClientStatus::active() ), $page, $perPage );
	}

	public function getTotalCount(): int {
		return $this->totalCount;
	}

	public function getCount(): int {
		return $this->repository->count();
	}

	public function getActiveCount(): int {
		return $this->repository->countWithFilter( new ClientFilter( status: ClientStatus::active() ) );
	}

	/**
	 * Clients registered within the last $days days — used for the "new clients" admin menu badge.
	 */
	public function getNewCount( int $days = 7 ): int {
		return $this->repository->countRegisteredSince( CarbonImmutable::now()->subDays( $days ) );
	}
}
