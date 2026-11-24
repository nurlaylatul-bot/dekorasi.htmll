<?php

declare(strict_types=1);

namespace AllCoach\Domain\Activity;

defined( 'ABSPATH' ) || exit;

interface ActivityTemplateRepository {

	public function findById( int $id ): ?ActivityTemplate;

	/** @return ActivityTemplate[] */
	public function findByCoachId( int $coachId, int $limit = 100, int $offset = 0 ): array;

	/** @return ActivityTemplate[] */
	public function findAll( int $limit = 100, int $offset = 0 ): array;

	/** @return ActivityTemplate[] */
	public function search( string $query, int $limit = 50, int $offset = 0 ): array;

	public function save( ActivityTemplate $library ): ActivityTemplate;

	public function delete( int $id ): bool;

	public function count(): int;

	public function countByCoachId( int $coachId ): int;
}
