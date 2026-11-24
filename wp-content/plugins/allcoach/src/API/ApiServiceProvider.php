<?php

declare(strict_types=1);

namespace AllCoach\API;

defined( 'ABSPATH' ) || exit;

use AllCoach\API\Controllers\V1\AvailabilitiesController;
use AllCoach\API\Controllers\V1\OrdersController;
use AllCoach\API\Controllers\V1\SessionBookingsController;
use AllCoach\API\Controllers\V1\ClientsController;
use AllCoach\API\Controllers\V1\ProgramsController;
use AllCoach\API\Controllers\V1\ActivitiesController;
use AllCoach\API\Controllers\V1\LibraryActivitiesController;
use AllCoach\API\Controllers\V1\SectionsController;
use AllCoach\API\Controllers\V1\CoachesController;
use AllCoach\API\Controllers\V1\ModulesController;
use AllCoach\API\Controllers\V1\ProgramCategoriesController;
use AllCoach\API\Controllers\V1\GoogleAuthController;
use AllCoach\API\Controllers\V1\ClientBookingController;
use AllCoach\API\Controllers\V1\ClientProfileController;
use AllCoach\API\Controllers\V1\SettingsController;
use AllCoach\API\Controllers\V1\AuthController;
use AllCoach\API\Controllers\V1\CartController;
use AllCoach\API\Controllers\V1\LogsController;
use AllCoach\API\Controllers\V1\DemoController;
use AllCoach\API\Controllers\V1\PayPalWebhookController;
use AllCoach\Core\Container;
use AllCoach\Core\ServiceProvider;
use AllCoach\Traits\Hooks;
use DI\ContainerBuilder;

class ApiServiceProvider implements ServiceProvider {

	use Hooks;

	public function __construct(
		private readonly Container $container
	) {}

	public function register( ContainerBuilder $builder ): void {}

	public function boot(): void {
		$this->addAction( 'rest_api_init', [ $this, 'registerRoutes' ] );
	}

	public function registerRoutes(): void {
		$controllers = [
			AvailabilitiesController::class,
			OrdersController::class,
			SessionBookingsController::class,
			CoachesController::class,
			ClientsController::class,
			ModulesController::class,
			ProgramsController::class,
			SectionsController::class,
			LibraryActivitiesController::class,
			ActivitiesController::class,
			ProgramCategoriesController::class,
			SettingsController::class,
			GoogleAuthController::class,
			ClientBookingController::class,
			ClientProfileController::class,
			AuthController::class,
			CartController::class,
			LogsController::class,
			DemoController::class,
		];

		/**
		 * Allow add-ons/modules to register REST controllers without editing this
		 * file. An enabled addon appends its controller class in boot() (which
		 * runs before rest_api_init). Each entry must be resolvable from the
		 * container (bound in config/services.php).
		 *
		 * @param array<int, class-string> $controllers
		 */
		$controllers = $this->applyFilters( 'allcoach:rest:controllers', $controllers );

		foreach ( $controllers as $class ) {
			if ( $this->container->has( $class ) ) {
				$this->container->get( $class )->registerRoutes();
			}
		}
	}
}
