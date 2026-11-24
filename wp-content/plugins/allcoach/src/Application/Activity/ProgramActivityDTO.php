<?php

declare(strict_types=1);

namespace AllCoach\Application\Activity;

defined( 'ABSPATH' ) || exit;

final class ProgramActivityDTO {

	public function __construct(
		public readonly int $programId,
		public readonly int $sectionId,
		public readonly int $day,
		public readonly int $week,
		public readonly int $position = 0,
		public readonly ?int $templateId = null,
		public readonly string $schemaJson = '',
		public readonly array $settings = [],
	) {}
}
