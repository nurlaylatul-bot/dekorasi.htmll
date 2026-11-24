<?php

declare(strict_types=1);

namespace AllCoach\Core\Exceptions;

defined( 'ABSPATH' ) || exit;

class ValidationException extends AllCoachException {

	private array $errors = [];

	public function setErrors( array $errors ): self {
		$this->errors = $errors;
		return $this;
	}

	public function getErrors(): array {
		return $this->errors;
	}

	public static function withErrors( array $errors, string $message = 'Validation failed' ): self {
		$exception = new self( $message );
		$exception->setErrors( $errors );
		return $exception;
	}
}
