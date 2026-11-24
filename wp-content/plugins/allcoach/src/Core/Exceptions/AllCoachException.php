<?php

declare(strict_types=1);

namespace AllCoach\Core\Exceptions;

defined( 'ABSPATH' ) || exit;

class AllCoachException extends \Exception {

	protected array $context = [];

	public function setContext( array $context ): self {
		$this->context = $context;
		return $this;
	}
	public function getContext(): array {
		return $this->context;
	}

	public static function withContext( string $message, array $context = [], int $code = 0, ?\Throwable $previous = null ): self {
		$exception = new static( $message, $code, $previous );
		$exception->setContext( $context );
		return $exception;
	}
}
