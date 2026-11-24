<?php
declare(strict_types=1);
namespace AllCoach\Frontend\Router;

class RouteGroup {

	private string $prefix;
	private RouteCollection $collection;

	public function __construct( string $prefix, RouteCollection $collection ) {
		$this->prefix     = trim( $prefix, '/' );
		$this->collection = $collection;
	}

	/**
	 * @param string          $subPath
	 * @param callable|string $handler
	 */
	public function route( string $subPath, $handler ): Route {
		$route = new Route( trim( $subPath, '/' ), $handler );
		$this->collection->add( $route );
		return $route;
	}

	/**
	 * @param callable|string $handler
	 */
	public function index( $handler ): Route {
		return $this->route( '', $handler );
	}

	public function getPrefix(): string {
		return $this->prefix;
	}
}
