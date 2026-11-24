<?php

declare(strict_types=1);

namespace AllCoach\Core\Contracts;

defined( 'ABSPATH' ) || exit;

interface ResolverInterface {

	public function make( string $id, array $parameters = [] ): mixed;
}
