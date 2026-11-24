<?php

declare(strict_types=1);

namespace AllCoach\Core\Jobs\Contracts;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\Jobs\AbstractJobPayload;

interface Job {

	/**
	 * Execute the job with the given payload.
	 *
	 * Constructor receives only service dependencies (autowired by DI).
	 * Payload data is passed here at execution time.
	 */
	public function handle( AbstractJobPayload $payload ): void;

	/**
	 * Reconstruct a typed payload from a serialised array.
	 */
	public static function payloadFromArray( array $data ): AbstractJobPayload;
}
