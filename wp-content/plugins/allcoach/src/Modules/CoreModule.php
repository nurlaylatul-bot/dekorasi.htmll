<?php

declare(strict_types=1);

namespace AllCoach\Modules;

defined( 'ABSPATH' ) || exit;

abstract class CoreModule extends AbstractModule {

	public function isEnabled(): bool {
		return true;
	}

	public function enable(): void {}

	public function disable(): void {}

	protected function loadModuleState(): void {}

	protected function saveModuleState(): void {}
}
