<?php

declare(strict_types=1);

namespace AllCoach\Application\Activity;

defined( 'ABSPATH' ) || exit;

final class ActivityDTO {

	public function __construct(
		public readonly string $title,
		public readonly string $description = '',
		public readonly string $schemaJson = '',
		public readonly ?int $coachId = null,
		public readonly bool $isPublished = false,
	) {}
}
