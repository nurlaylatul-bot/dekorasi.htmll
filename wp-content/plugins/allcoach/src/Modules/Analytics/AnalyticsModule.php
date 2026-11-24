<?php

declare(strict_types=1);

namespace AllCoach\Modules\Analytics;

defined( 'ABSPATH' ) || exit;

use AllCoach\API\Controllers\V1\AnalyticsController;
use AllCoach\API\Controllers\V1\AnalyticsPreferencesController;
use AllCoach\Modules\CoreModule;

/**
 * Analytics dashboard — an always-on core feature. Registered as a core module
 * (like Demo) so its wiring flows through the module system rather than being
 * hardcoded in ApiServiceProvider. Its REST controllers are contributed via the
 * `allcoach:rest:controllers` filter in boot(). The Pro plugin layers advanced
 * analytics on top through its own license-gated module.
 */
class AnalyticsModule extends CoreModule {

	protected string $id          = 'analytics';
	protected string $name        = 'Analytics';
	protected string $description = 'Dashboard with revenue, orders, enrollments and session insights.';
	protected string $version     = '1.0.0';

	public function boot(): void {
		$this->addFilter( 'allcoach:rest:controllers', [ $this, 'registerControllers' ] );
	}

	/**
	 * @param array<int, class-string> $controllers
	 * @return array<int, class-string>
	 */
	public function registerControllers( array $controllers ): array {
		$controllers[] = AnalyticsController::class;
		$controllers[] = AnalyticsPreferencesController::class;
		return $controllers;
	}
}
