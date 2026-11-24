<?php

declare(strict_types=1);

namespace AllCoach\Domain\Program;

defined( 'ABSPATH' ) || exit;

use AllCoach\Domain\Shared\QueryFilter;

final class ProgramFilter extends QueryFilter {

	public function __construct(
		public readonly ?int $coachId = null,
		public readonly ?int $authorId = null,
		public readonly ?bool $isActive = null,
		public readonly ?bool $publishedOnly = null,
		public readonly ?int $categoryId = null,
		?string $search = null,
		string $orderBy = 'date',
		string $order = 'DESC',
	) {
		parent::__construct( $search, $orderBy, $order );
	}
}
