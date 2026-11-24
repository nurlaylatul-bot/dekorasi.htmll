<?php

declare(strict_types=1);

namespace AllCoach\Domain\Shared;

defined( 'ABSPATH' ) || exit;

abstract class Entity {

	protected ?int $id = null;

	public function getId(): ?int {
		return $this->id;
	}

	protected function setId( int $id ): void {
		$this->id = $id;
	}

	public function isNew(): bool {
		return null === $this->id;
	}

	public function equals( Entity $other ): bool {
		return get_class( $this ) === get_class( $other ) && $this->id === $other->getId();
	}
}
