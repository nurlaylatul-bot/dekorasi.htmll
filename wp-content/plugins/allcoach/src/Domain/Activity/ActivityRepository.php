<?php

declare(strict_types=1);

namespace AllCoach\Domain\Activity;

defined( 'ABSPATH' ) || exit;

interface ActivityRepository {

	public function findById( int $id ): ?Activity;

	/** @return Activity[] */
	public function findByProgram( int $programId ): array;

	/** @return Activity[] */
	public function findByProgramAndWeek( int $programId, int $week ): array;

	/**
	 * @return Activity[]
	 */
	public function findByTemplate( int $templateId ): array;

	public function save( Activity $activity ): Activity;

	public function delete( int $id ): bool;

	public function deleteByProgram( int $programId ): int;
}
