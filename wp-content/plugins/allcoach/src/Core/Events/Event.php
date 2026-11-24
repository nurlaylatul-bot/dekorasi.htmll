<?php

declare(strict_types=1);

namespace AllCoach\Core\Events;

defined( 'ABSPATH' ) || exit;

abstract class Event {

	private bool $propagationStopped = false;

	public function stopPropagation(): void {
		$this->propagationStopped = true;
	}

	public function isPropagationStopped(): bool {
		return $this->propagationStopped;
	}

	public function getName(): string {
		return static::class;
	}
}
