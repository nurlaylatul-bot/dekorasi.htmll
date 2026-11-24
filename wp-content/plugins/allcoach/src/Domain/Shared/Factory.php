<?php

declare(strict_types=1);

namespace AllCoach\Domain\Shared;

defined( 'ABSPATH' ) || exit;

/**
 * @template T
 */
interface Factory {

	/**
	 * @return T
	 */
	public function create( array $data ): Entity;

	/**
	 * @return T
	 */
	public function fromArray( array $data ): Entity;
}
