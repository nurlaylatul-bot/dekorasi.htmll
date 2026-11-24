<?php

declare(strict_types=1);

namespace AllCoach\Domain\Session;

defined( 'ABSPATH' ) || exit;

interface SessionRepository {

	public function findById( int $id ): ?Session;

	/**
	 * The single session for a program (for now we support only one session per program; multiple may be allowed later).
	 */
	public function findOneByProgramId( int $programId ): ?Session;

	/** @return Session[] */
	public function findByProgram( int $programId ): array;

	public function countByProgram( int $programId ): int;

	public function totalDurationByProgram( int $programId ): int;

	public function save( Session $session ): Session;

	public function delete( int $id ): bool;

	public function deleteByProgramId( int $programId ): int;
}
