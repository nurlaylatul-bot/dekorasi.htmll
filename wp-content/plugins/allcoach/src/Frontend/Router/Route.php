<?php
declare(strict_types=1);
namespace AllCoach\Frontend\Router;

// phpcs:disable Universal.NamingConventions.NoReservedKeywordParameterNames.matchFound
class Route {

	private string $subPath;

	private string $name = '';

	/** @var string[] */
	private array $allowedQueryParams = [];

	/** @var callable|string */
	private $handler;

	/** @var string|callable|null */
	private $title = null;

	/**
	 * @param string          $subPath
	 * @param callable|string $handler
	 */
	public function __construct( string $subPath, $handler ) {
		$this->subPath = trim( $subPath, '/' );
		$this->handler = $handler;
	}

	public function name( string $name ): self {
		$this->name = $name;
		return $this;
	}

	public function params( string ...$params ): self {
		$this->allowedQueryParams = array_merge(
			$this->allowedQueryParams,
			array_values( $params )
		);
		return $this;
	}

	/** @param string|callable $title */
	public function title( $title ): self {
		$this->title = $title;
		return $this;
	}

	public function matches( string $raw ): bool {
		return trim( $raw, '/' ) === $this->subPath;
	}

	public function handle( RouteMatch $match ): void {
		if ( is_callable( $this->handler ) ) {
			( $this->handler )( $match );
			return;
		}

		if ( is_string( $this->handler ) && str_contains( $this->handler, '::' ) ) {
			[ $class, $method ] = explode( '::', $this->handler, 2 );
			( new $class() )->{$method}( $match );
			return;
		}

		if ( is_string( $this->handler ) && class_exists( $this->handler ) ) {
			( new $this->handler() )->handle( $match );
		}
	}

	public function getTitle( RouteMatch $match ): string {
		if ( is_callable( $this->title ) ) {
			$ref    = new \ReflectionFunction( \Closure::fromCallable( $this->title ) );
			$result = $ref->getNumberOfParameters() > 0
				? ( $this->title )( $match )
				: ( $this->title )();
			return (string) $result;
		}
		return (string) ( $this->title ?? '' );
	}

	public function getSubPath(): string {
		return $this->subPath;
	}

	public function getName(): string {
		return $this->name;
	}

	/** @return string[] */
	public function getAllowedQueryParams(): array {
		return $this->allowedQueryParams;
	}
}
