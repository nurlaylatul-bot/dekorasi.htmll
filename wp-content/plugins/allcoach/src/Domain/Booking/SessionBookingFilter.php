<?php

declare(strict_types=1);

namespace AllCoach\Domain\Booking;

defined( 'ABSPATH' ) || exit;

use AllCoach\Domain\Shared\QueryFilter;

final class SessionBookingFilter extends QueryFilter {

	public function __construct(
		public readonly ?int $enrollmentId = null,
		public readonly ?int $clientId = null,
		public readonly ?int $coachId = null,
		public readonly ?int $authorId = null,
		public readonly ?string $fromDate = null,
		public readonly ?string $toDate = null,
		public readonly ?string $status = null,
		public readonly bool $excludeCancelled = false,
		public readonly bool $excludeDemo = false,
		?string $search = null,
		string $orderBy = 'date',
		string $order = 'DESC',
	) {
		parent::__construct( $search, $orderBy, $order );
	}
}
