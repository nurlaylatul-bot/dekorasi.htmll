<?php

declare(strict_types=1);

namespace AllCoach\Core\Jobs\Contracts;

defined( 'ABSPATH' ) || exit;

interface JobPayload {

	public static function fromArray( array $data ): self;

	public function toArray(): array;
}
