<?php

declare(strict_types=1);

namespace AllCoach\Infrastructure\Jobs;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\Jobs\Contracts\JobDispatcher;
use AllCoach\Core\Jobs\Contracts\JobPayload;

class ActionSchedulerDispatcher implements JobDispatcher {

	private const HOOK  = 'allcoach/run_job';
	private const GROUP = 'allcoach';

	public function dispatch( string $jobClass, JobPayload $payload, int $delay = 0 ): int {
		if ( 0 === $delay ) {
			$this->runNow( $jobClass, $payload );
			return 0;
		}

		return (int) as_schedule_single_action(
			time() + $delay,
			self::HOOK,
			[ $this->encode( $jobClass, $payload ) ],
			self::GROUP
		);
	}

	public function dispatchUnique( string $jobClass, JobPayload $payload, int $delay = 0 ): int {
		if ( 0 === $delay ) {
			$this->runNow( $jobClass, $payload );
			return 0;
		}

		return (int) as_schedule_single_action(
			time() + $delay,
			self::HOOK,
			[ $this->encode( $jobClass, $payload ) ],
			self::GROUP,
			true
		);
	}

	public function scheduleAt( string $jobClass, JobPayload $payload, int $timestamp ): int {
		return (int) as_schedule_single_action(
			$timestamp,
			self::HOOK,
			[ $this->encode( $jobClass, $payload ) ],
			self::GROUP
		);
	}

	public function scheduleRecurring( string $jobClass, JobPayload $payload, int $intervalSeconds, int $startAt = 0 ): int {
		return (int) as_schedule_recurring_action(
			$startAt ?: time(),
			$intervalSeconds,
			self::HOOK,
			[ $this->encode( $jobClass, $payload ) ],
			self::GROUP
		);
	}

	public function cancel( string $jobClass, JobPayload $payload ): void {
		as_unschedule_all_actions(
			self::HOOK,
			[ $this->encode( $jobClass, $payload ) ],
			self::GROUP
		);
	}

	public function isPending( string $jobClass, JobPayload $payload ): bool {
		return (bool) as_has_scheduled_action(
			self::HOOK,
			[ $this->encode( $jobClass, $payload ) ],
			self::GROUP
		);
	}

	private function encode( string $jobClass, JobPayload $payload ): string {
		return (string) wp_json_encode(
			[
				'class'   => $jobClass,
				'payload' => $payload->toArray(),
			]
		);
	}

	private function runNow( string $jobClass, JobPayload $payload ): void {
		do_action( self::HOOK, $this->encode( $jobClass, $payload ) );
	}
}
