<?php

declare(strict_types=1);

namespace AllCoach\Domain\Availability;

defined( 'ABSPATH' ) || exit;

interface AvailabilityRepository {

	public function findById( int $id ): ?Availability;

	/**
	 * @return Availability[]
	 */
	public function findByUser( int $userId, int $limit = 100, int $offset = 0 ): array;

	public function countByUser( int $userId ): int;

	public function save( Availability $availability ): Availability;

	public function delete( int $id ): bool;
}
