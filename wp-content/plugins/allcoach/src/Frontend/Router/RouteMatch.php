<?php
declare(strict_types=1);
namespace AllCoach\Frontend\Router;

//phpcs:disable Universal.NamingConventions.NoReservedKeywordParameterNames.defaultFound

class RouteMatch {

	private Route $route;
	private string $raw;

	public function __construct( Route $route, string $raw ) {
		$this->route = $route;
		$this->raw   = $raw;
	}

	public function getRoute(): Route {
		return $this->route;
	}

	public function getSlug(): string {
		return $this->raw;
	}

	/**
	 * @param string $key
	 * @param string $default
	 */
	public function get( string $key, string $default = '' ): string {
		$value = get_query_var( $key, null );

		if ( null !== $value ) {
			return sanitize_text_field( (string) $value );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return isset( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( (string) $_GET[ $key ] ) ) : $default;
	}

	/**
	 * @param string $key
	 * @param int    $default
	 */
	public function getInt( string $key, int $default = 0 ): int {
		$value = $this->get( $key, (string) $default );
		return (int) $value;
	}

	public function getBool( string $key, bool $default = false ): bool {
		$value = strtolower( $this->get( $key, $default ? '1' : '0' ) );
		return in_array( $value, [ '1', 'true', 'yes' ], true );
	}

	/**
	 * @return array<string,string>
	 */
	public function allParams(): array {
		$out = [];
		foreach ( $this->route->getAllowedQueryParams() as $param ) {
			$value = $this->get( $param );
			if ( '' !== $value ) {
				$out[ $param ] = $value;
			}
		}
		return $out;
	}

	public function has( string $key ): bool {
		return '' !== $this->get( $key );
	}

	public function getPage(): int {
		return max( 1, $this->getInt( 'page', 1 ) );
	}
}
