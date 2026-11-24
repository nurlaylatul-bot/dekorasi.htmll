<?php

declare(strict_types=1);

namespace AllCoach\Core;

defined( 'ABSPATH' ) || exit;

use DI\ContainerBuilder;

interface ServiceProvider {

	public function register( ContainerBuilder $builder ): void;

	public function boot(): void;
}
