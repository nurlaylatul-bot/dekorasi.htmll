<?php

declare(strict_types=1);

namespace AllCoach\Domain\Order;

defined( 'ABSPATH' ) || exit;

use AllCoach\Domain\Shared\QueryFilter;

final class OrderFilter extends QueryFilter {

	public function __construct(
		public readonly ?int $clientId = null,
		public readonly ?int $programId = null,
		public readonly ?string $status = null,
		public readonly ?string $fromDate = null,
		public readonly ?string $toDate = null,
		public readonly bool $excludeDemo = false,
		?string $search = null,
		string $orderBy = 'date',
		string $order = 'DESC',
	) {
		parent::__construct( $search, $orderBy, $order );
	}
}
