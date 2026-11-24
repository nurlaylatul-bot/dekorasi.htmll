<?php

declare(strict_types=1);

namespace AllCoach\Domain\Shared;

defined( 'ABSPATH' ) || exit;

abstract class QueryFilter {

	public function __construct(
		public readonly ?string $search = null,
		public readonly string $orderBy = 'date',
		public readonly string $order = 'DESC',
	) {}
}
