<?php

declare(strict_types=1);

namespace AllCoach\Modules;

defined( 'ABSPATH' ) || exit;

use AllCoach\Traits\Singleton;

class ModuleRegistry {

	use Singleton;

	/** @var array<string, ModuleInterface> */
	private array $modules = [];

	/** @var array<string, bool> */
	private array $booted = [];

	public function register( ModuleInterface $module ): void {
		$moduleId = $module->getId();

		if ( isset( $this->modules[ $moduleId ] ) ) {
			return;
		}

		$this->modules[ $moduleId ] = $module;
		$this->booted[ $moduleId ]  = false;
	}

	public function getModule( string $id ): ?ModuleInterface {
		return $this->modules[ $id ] ?? null;
	}

	/**
	 * @return array<string, ModuleInterface>
	 */
	public function getAllModules(): array {
		return $this->modules;
	}

	/**
	 * @return array<string, ModuleInterface>
	 */
	public function getEnabledModules(): array {
		return array_filter( $this->modules, fn( ModuleInterface $module ) => $module->isEnabled() );
	}

	public function hasModule( string $id ): bool {
		return isset( $this->modules[ $id ] );
	}

	public function isBooted( string $id ): bool {
		return $this->booted[ $id ] ?? false;
	}

	public function markAsBooted( string $id ): void {
		$this->booted[ $id ] = true;
	}

	public function enableModule( string $id ): bool {
		$module = $this->getModule( $id );

		if ( ! $module ) {
			return false;
		}

		$module->enable();
		return true;
	}

	public function disableModule( string $id ): bool {
		$module = $this->getModule( $id );

		if ( ! $module ) {
			return false;
		}

		$module->disable();
		return true;
	}

	public function count(): int {
		return count( $this->modules );
	}
}
