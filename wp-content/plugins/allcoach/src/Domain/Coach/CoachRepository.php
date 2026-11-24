<?php

declare(strict_types=1);

namespace AllCoach\Domain\Coach;

defined( 'ABSPATH' ) || exit;

interface CoachRepository {

	public function findById( int $id ): ?Coach;

	public function findByEmail( string $email ): ?Coach;

	public function findByUserId( int $userId ): ?Coach;

	/** @return array<Coach> */
	public function findAll( int $limit = 100, int $offset = 0 ): array;

	public function save( Coach $coach ): Coach;

	public function delete( int $id ): bool;

	public function count(): int;

	/** @return array<Coach> */
	public function search( string $query, int $limit = 100, int $offset = 0 ): array;

	public function searchCount( string $query ): int;
}
