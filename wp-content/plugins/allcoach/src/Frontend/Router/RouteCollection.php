<?php
declare(strict_types=1);
namespace AllCoach\Frontend\Router;

class RouteCollection {

	/** @var Route[] */
	private array $routes = [];

	public function add( Route $route ): void {
		$this->routes[] = $route;
	}

	public function match( string $raw ): ?RouteMatch {
		$raw = trim( $raw, '/' );

		foreach ( $this->routes as $route ) {
			if ( $route->matches( $raw ) ) {
				return new RouteMatch( $route, $raw );
			}
		}

		return null;
	}

	public function getByName( string $name ): ?Route {
		foreach ( $this->routes as $route ) {
			if ( $route->getName() === $name ) {
				return $route;
			}
		}

		return null;
	}

	/**
	 * @return Route[]
	 */
	public function all(): array {
		return $this->routes;
	}
}
