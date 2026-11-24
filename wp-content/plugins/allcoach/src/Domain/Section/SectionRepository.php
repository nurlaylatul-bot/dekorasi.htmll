<?php

declare(strict_types=1);

namespace AllCoach\Domain\Section;

defined( 'ABSPATH' ) || exit;

interface SectionRepository {

	public function findById( int $id ): ?Section;

	/**
	 * @return Section[]
	 */
	public function findByProgram( int $programId ): array;

	public function save( Section $section ): Section;

	public function delete( int $id ): bool;

	public function deleteByProgram( int $programId ): int;
}
