<?php

declare(strict_types=1);

namespace AllCoach\Domain\Client;

defined( 'ABSPATH' ) || exit;

use AllCoach\Domain\Shared\QueryFilter;

final class ClientFilter extends QueryFilter {

	public function __construct(
		public readonly ?int $coachId = null,
		public readonly ?int $authorId = null,
		public readonly ?ClientStatus $status = null,
		?string $search = null,
		string $orderBy = 'date',
		string $order = 'DESC',
	) {
		parent::__construct( $search, $orderBy, $order );
	}
}
