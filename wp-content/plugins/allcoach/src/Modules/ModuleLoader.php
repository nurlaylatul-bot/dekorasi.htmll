<?php

declare(strict_types=1);

namespace AllCoach\Modules;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\Container;
use AllCoach\Modules\Analytics\AnalyticsModule;
use AllCoach\Modules\Demo\DemoModule;
use AllCoach\Modules\PayPal\PayPalAddon;
use AllCoach\Support\Logger;
use AllCoach\Traits\Hooks;

class ModuleLoader {

	use Hooks;

	private Container $container;
	private ModuleRegistry $registry;
	private Logger $logger;

	/** @var array<string, class-string<ModuleInterface>> */
	private array $availableModules = [
		'demo'      => DemoModule::class,
		'paypal'    => PayPalAddon::class,
		'analytics' => AnalyticsModule::class,
	];

	public function __construct( Container $container, Logger $logger ) {
		$this->container = $container;
		$this->registry  = ModuleRegistry::getInstance();
		$this->logger    = $logger;
	}

	public function loadModules(): void {
		$modules       = $this->discoverModules();
		$sortedModules = $this->sortModulesByDependencies( $modules );

		foreach ( $sortedModules as $module ) {
			$this->registerModule( $module );
		}

		foreach ( $sortedModules as $module ) {
			$this->bootModule( $module );
		}

		/** Fires after all modules have been loaded. */
		$this->doAction( 'allcoach:modules:loaded', $sortedModules );
	}

	/**
	 * @return array<ModuleInterface>
	 */
	private function discoverModules(): array {
		$modules = [];

		/**
		 * Allow add-ons (e.g. AllCoach Pro) to register modules without editing
		 * this file. Map of module id => class-string implementing ModuleInterface.
		 * Must be added before Plugin::boot() runs loadModules() — Pro adds it from
		 * its bootstrap at autoload time.
		 *
		 * @param array<string, class-string<ModuleInterface>> $availableModules
		 */
		$availableModules = $this->applyFilters( 'allcoach:modules:available', $this->availableModules );

		foreach ( $availableModules as $moduleId => $moduleClass ) {
			if ( ! class_exists( $moduleClass ) ) {
				$this->logger->warning( "Module class not found: {$moduleClass}" );
				continue;
			}

			try {
				$module    = new $moduleClass( $this->container );
				$modules[] = $module;
			} catch ( \Exception $e ) {
				$this->logger->error(
					"Failed to instantiate module: {$moduleClass}",
					[
						'error' => $e->getMessage(),
					]
				);
			}
		}

		return $modules;
	}

	/**
	 * @param array<ModuleInterface> $modules
	 * @return array<ModuleInterface>
	 */
	private function sortModulesByDependencies( array $modules ): array {
		$sorted  = [];
		$visited = [];

		foreach ( $modules as $module ) {
			$this->sortModuleDependencies( $module, $modules, $sorted, $visited );
		}

		return $sorted;
	}


	private function sortModuleDependencies(
		ModuleInterface $module,
		array $allModules,
		array &$sorted,
		array &$visited
	): void {
		$moduleId = $module->getId();

		if ( isset( $visited[ $moduleId ] ) ) {
			return;
		}

		$visited[ $moduleId ] = true;

		foreach ( $module->getDependencies() as $dependencyId ) {
			$dependency = $this->findModuleById( $dependencyId, $allModules );

			if ( $dependency ) {
				$this->sortModuleDependencies( $dependency, $allModules, $sorted, $visited );
			}
		}

		$sorted[] = $module;
	}

	private function findModuleById( string $id, array $modules ): ?ModuleInterface {
		foreach ( $modules as $module ) {
			if ( $module->getId() === $id ) {
				return $module;
			}
		}

		return null;
	}

	private function registerModule( ModuleInterface $module ): void {
		try {
			$this->registry->register( $module );
			$module->register();
		} catch ( \Exception $e ) {
			$this->logger->error(
				"Failed to register module: {$module->getId()}",
				[
					'error' => $e->getMessage(),
				]
			);
		}
	}

	private function bootModule( ModuleInterface $module ): void {
		if ( ! $module->isEnabled() ) {
			return;
		}

		if ( $this->registry->isBooted( $module->getId() ) ) {
			return;
		}

		try {
			$module->boot();
			$this->registry->markAsBooted( $module->getId() );
		} catch ( \Exception $e ) {
			$this->logger->error(
				"Failed to boot module: {$module->getId()}",
				[
					'error' => $e->getMessage(),
				]
			);
		}
	}

	public function getRegistry(): ModuleRegistry {
		return $this->registry;
	}
}
