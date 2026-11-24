<?php

declare(strict_types=1);

namespace AllCoach\Domain\Enrollment;

defined( 'ABSPATH' ) || exit;

interface EnrollmentRepository {

	public function findById( int $id ): ?Enrollment;

	/** @return Enrollment[] */
	public function findAll( int $limit = 100, int $offset = 0 ): array;

	public function count(): int;

	/** @return Enrollment[] */
	public function findByUser( int $userId, int $limit = 100, int $offset = 0 ): array;

	/** @return Enrollment[] */
	public function findByProgram( int $programId, int $limit = 100, int $offset = 0 ): array;

	public function findByUserAndProgram( int $userId, int $programId ): ?Enrollment;

	public function findByOrderItemId( int $orderItemId ): ?Enrollment;

	public function save( Enrollment $enrollment ): Enrollment;

	public function countActiveByProgram( int $programId ): int;

	/** Count all enrollments for a program (any status). */
	public function countByProgram( int $programId ): int;

	public function countByUser( int $userId ): int;

	public function hasActiveEnrollment( int $userId, int $programId ): bool;

	public function delete( int $id ): bool;

	/**
	 * Delete all enrollments belonging to a user.
	 *
	 * @return int Number of enrollments deleted.
	 */
	public function deleteByUser( int $userId ): int;
}
