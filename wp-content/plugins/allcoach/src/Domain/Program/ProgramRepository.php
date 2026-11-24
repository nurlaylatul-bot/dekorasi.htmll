<?php

declare(strict_types=1);

namespace AllCoach\Domain\Program;

defined( 'ABSPATH' ) || exit;

interface ProgramRepository {

	public function findById( int $id ): ?Program;

	/**
	 * @return array<Program>
	 */
	public function findAll( int $limit = 100, int $offset = 0 ): array;

	/**
	 * @return array<Program>
	 */
	public function findActive( int $limit = 100, int $offset = 0 ): array;

	/**
	 * @return array<Program>
	 */
	public function findByCoachId( int $coachId, int $limit = 100, int $offset = 0 ): array;

	/**
	 * @return array<Program>
	 */
	public function findByAuthorId( int $wpUserId, int $limit = 100, int $offset = 0 ): array;

	/**
	 * @return array<Program>
	 */
	public function findWithFilter( ProgramFilter $filter, int $limit = 100, int $offset = 0 ): array;

	public function save( Program $program ): Program;

	public function delete( int $id ): bool;

	/**
	 * Total count across ALL post statuses.
	 */
	public function count(): int;

	public function countActive(): int;

	public function countByAuthorId( int $wpUserId ): int;

	public function countWithFilter( ProgramFilter $filter ): int;

	/**
	 * @return array<Program>
	 */
	public function search( string $query, int $limit = 100, int $offset = 0 ): array;
}
