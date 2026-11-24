<?php

declare(strict_types=1);

namespace AllCoach\Infrastructure\Jobs;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\Jobs\Contracts\Job;
use AllCoach\Support\Logger;
use AllCoach\Traits\Hooks;
use Psr\Container\ContainerInterface;

class ActionSchedulerRunner {

	use Hooks;

	public const HOOK = 'allcoach/run_job';

	public function __construct(
		private readonly ContainerInterface $container,
		private readonly Logger $logger,
	) {}

	public function register(): void {
		$this->addAction( self::HOOK, [ $this, 'run' ] );
	}

	public function run( string $jobData ): void {
		$data = json_decode( $jobData, true );

		if ( ! is_array( $data ) || empty( $data['class'] ) ) {
			$this->logger->error(
				'AllCoach JobRunner: received malformed job data.',
				[ 'data' => $jobData ]
			);
			return;
		}

		$class       = $data['class'];
		$payloadData = $data['payload'] ?? [];

		if ( ! class_exists( $class ) ) {
			$this->logger->error(
				"AllCoach JobRunner: job class [{$class}] not found."
			);
			return;
		}

		if ( ! is_subclass_of( $class, Job::class ) ) {
			$this->logger->error(
				"AllCoach JobRunner: [{$class}] does not implement Job interface."
			);
			return;
		}

		try {
			/** @var Job $job */
			$job     = $this->container->get( $class );
			$payload = $class::payloadFromArray( $payloadData );
			$job->handle( $payload );
		} catch ( \Throwable $e ) {
			$this->logger->error(
				"AllCoach JobRunner: job [{$class}] threw an exception.",
				[
					'message' => $e->getMessage(),
					'file'    => $e->getFile(),
					'line'    => $e->getLine(),
				]
			);

			// Re-throw so Action Scheduler records this as a failed action.
			throw $e;
		}
	}
}
