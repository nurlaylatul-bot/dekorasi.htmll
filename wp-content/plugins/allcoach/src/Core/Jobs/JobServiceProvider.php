<?php

declare(strict_types=1);

namespace AllCoach\Core\Jobs;

defined( 'ABSPATH' ) || exit;

use AllCoach\Infrastructure\Jobs\ActionSchedulerRunner;
use AllCoach\Core\Contracts\ServiceProviderInterface;
use AllCoach\Traits\Hooks;

class JobServiceProvider implements ServiceProviderInterface {

	use Hooks;

	public function __construct(
		private readonly ActionSchedulerRunner $runner,
	) {}

	public function boot(): void {
		$this->initActionScheduler();
		$this->runner->register();
		$this->addAction( 'allcoach:activated', [ $this, 'onActivation' ] );
	}

	private function initActionScheduler(): void {
		$bootstrap = dirname( __DIR__, 3 ) . '/vendor/woocommerce/action-scheduler/action-scheduler.php';

		if ( file_exists( $bootstrap ) && ! function_exists( 'as_schedule_single_action' ) ) {
			require_once $bootstrap;
		}
	}

	public function onActivation(): void {
		if ( class_exists( \ActionScheduler_DBStoreMigrator::class ) ) {
			// \ActionScheduler_DBStoreMigrator::migrate();
		}
	}
}
