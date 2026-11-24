<?php

declare(strict_types=1);

namespace AllCoach\Core\Pipeline;

defined( 'ABSPATH' ) || exit;

class Pipeline {

	private mixed $passable;
	private array $pipes = [];

	public function send( mixed $passable ): self {
		$this->passable = $passable;
		return $this;
	}

	public function through( array $pipes ): self {
		$this->pipes = $pipes;
		return $this;
	}

	public function pipe( callable $pipe ): self {
		$this->pipes[] = $pipe;
		return $this;
	}

	public function then( callable $destination ): mixed {
		$pipeline = array_reduce(
			array_reverse( $this->pipes ),
			$this->carry(),
			$destination
		);

		return $pipeline( $this->passable );
	}

	public function thenReturn(): mixed {
		return $this->then( fn( $passable ) => $passable );
	}

	protected function carry(): \Closure {
		return function ( $stack, $pipe ) {
			return function ( $passable ) use ( $stack, $pipe ) {
				if ( is_callable( $pipe ) ) {
					return $pipe( $passable, $stack );
				}

				if ( is_object( $pipe ) && method_exists( $pipe, 'handle' ) ) {
					return $pipe->handle( $passable, $stack );
				}

				throw new \RuntimeException( 'Invalid pipe: ' . esc_html( gettype( $pipe ) ) );
			};
		};
	}

	public static function make(): self {
		return new self();
	}
}
