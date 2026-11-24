<?php
declare(strict_types=1);
namespace AllCoach\Frontend\Router;

use AllCoach\Frontend\AccountPages;
use AllCoach\Traits\Hooks;

class Router {

	use Hooks;

	const QUERY_VAR = 'allcoach_route';

	private RouteCollection $routes;

	private static ?Router $instance = null;

	private function __construct() {
		$this->routes = new RouteCollection();
	}

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function boot( bool $dispatch = true, bool $titleFilter = true ): void {
		$this->addFilter( 'query_vars', [ $this, 'registerQueryVars' ] );
		$this->addAction( 'init', [ $this, 'addRewriteRules' ] );
		$this->addAction( 'init', [ $this, 'maybeFlushRules' ], 99 );

		if ( $dispatch ) {
			$this->addAction( 'template_redirect', [ $this, 'dispatch' ] );
		}

		if ( $titleFilter ) {
			$this->addFilter( 'document_title_parts', [ $this, 'filterDocumentTitle' ] );
		}
	}

	/**
	 * @param string[] $vars
	 * @return string[]
	 */
	public function registerQueryVars( array $vars ): array {
		$vars[] = self::QUERY_VAR;

		foreach ( $this->routes->all() as $route ) {
			foreach ( $route->getAllowedQueryParams() as $param ) {
				if ( ! in_array( $param, $vars, true ) ) {
					$vars[] = $param;
				}
			}
		}

		return $vars;
	}

	/**
	 * Add rewrite rules for pretty permalinks.
	 * Plain permalinks use ?page_id=X&allcoach_route=subpath — no rules needed.
	 */
	public function addRewriteRules(): void {
		if ( ! get_option( 'permalink_structure' ) ) {
			return;
		}

		$pageId    = AccountPages::getAccountPageId();
		$permalink = $pageId > 0 ? get_permalink( $pageId ) : false;

		if ( ! $permalink ) {
			return;
		}

		$basePath = trim( wp_parse_url( $permalink, PHP_URL_PATH ), '/' );

		foreach ( $this->routes->all() as $route ) {
			$subPath = $route->getSubPath();

			if ( $subPath === '' ) {
				continue; // Index = the account page itself; WP resolves it without a rewrite rule.
			}

			add_rewrite_rule(
				'^' . $basePath . '/' . $subPath . '/?$',
				'index.php?pagename=' . $basePath . '&' . self::QUERY_VAR . '=' . rawurlencode( $subPath ),
				'top'
			);

			add_rewrite_rule(
				'^' . $basePath . '/' . $subPath . '/page/([0-9]+)/?$',
				'index.php?pagename=' . $basePath . '&' . self::QUERY_VAR . '=' . rawurlencode( $subPath ) . '&paged=$matches[1]',
				'top'
			);
		}
	}

	public function maybeFlushRules(): void {
		if ( ! get_option( 'permalink_structure' ) ) {
			return;
		}

		$pageId    = AccountPages::getAccountPageId();
		$permalink = $pageId > 0 ? get_permalink( $pageId ) : false;

		if ( ! $permalink ) {
			return;
		}

		$basePath = trim( wp_parse_url( $permalink, PHP_URL_PATH ), '/' );
		$cached   = (array) get_option( 'rewrite_rules', [] );

		foreach ( $this->routes->all() as $route ) {
			$subPath = $route->getSubPath();

			if ( $subPath === '' ) {
				continue;
			}

			if ( ! isset( $cached[ '^' . $basePath . '/' . $subPath . '/?$' ] )
				|| ! isset( $cached[ '^' . $basePath . '/' . $subPath . '/page/([0-9]+)/?$' ] ) ) {
				flush_rewrite_rules();
				return;
			}
		}
	}

	public function dispatch(): void {
		if ( ! AccountPages::isAccountPage() ) {
			return;
		}

		$raw   = $this->currentSubPath();
		$match = $this->routes->match( $raw );

		if ( null === $match ) {
			return;
		}

		$match->getRoute()->handle( $match );
	}

	/**
	 * @param array<string,string> $parts
	 * @return array<string,string>
	 */
	public function filterDocumentTitle( array $parts ): array {
		if ( ! AccountPages::isAccountPage() ) {
			return $parts;
		}

		$match = $this->routes->match( $this->currentSubPath() );

		if ( null !== $match ) {
			$title = $match->getRoute()->getTitle( $match );
			if ( $title !== '' ) {
				$parts['title'] = $title;
			}
		}

		return $parts;
	}

	public function group( string $prefix, callable $callback ): RouteGroup {
		$group = new RouteGroup( $prefix, $this->routes );
		$callback( $group );
		return $group;
	}

	public function add( Route $route ): Route {
		$this->routes->add( $route );
		return $route;
	}

	public function getRoutes(): RouteCollection {
		return $this->routes;
	}

	/**
	 * Generate a URL for a named route.
	 *
	 * @param string               $name
	 * @param array<string,scalar> $params
	 */
	public function urlFor( string $name, array $params = [] ): string {
		$route = $this->routes->getByName( $name );

		if ( null === $route ) {
			return '';
		}

		return $this->buildUrl( $route->getSubPath(), $params );
	}

	/**
	 * Build a URL for any sub-path under the account page.
	 * Handles both pretty and plain permalink structures automatically.
	 *
	 * @param string               $subPath  e.g. "programs", "profile", "" for account root.
	 * @param array<string,scalar> $params   Extra query parameters.
	 */
	public function buildUrl( string $subPath = '', array $params = [] ): string {
		$pageId    = AccountPages::getAccountPageId();
		$permalink = $pageId > 0 ? get_permalink( $pageId ) : false;

		if ( ! $permalink ) {
			return home_url( '/' );
		}

		if ( get_option( 'permalink_structure' ) ) {
			$base = trailingslashit( $permalink );
			$url  = $subPath !== '' ? $base . trailingslashit( $subPath ) : $base;
		} else {
			$args = [ 'page_id' => $pageId ];
			if ( $subPath !== '' ) {
				$args[ self::QUERY_VAR ] = $subPath;
			}
			$url = add_query_arg( $args, home_url( '/' ) );
		}

		if ( ! empty( $params ) ) {
			$url = add_query_arg( array_map( 'strval', $params ), $url );
		}

		return $url;
	}

	/**
	 * Return the active route sub-path, regardless of permalink structure.
	 */
	public function currentSubPath(): string {
		$raw = (string) get_query_var( self::QUERY_VAR, '' );

		// Plain permalinks: query var is not set via rewrite; fall back to $_GET.
		if ( $raw === '' && ! get_option( 'permalink_structure' ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$raw = isset( $_GET[ self::QUERY_VAR ] )
				? sanitize_text_field( wp_unslash( (string) $_GET[ self::QUERY_VAR ] ) )
				: '';
		}

		return trim( $raw, '/' );
	}

	/**
	 * Return the matched RouteMatch for the current request, or null if not on an account page
	 * or no route matched.
	 */
	public function currentMatch(): ?RouteMatch {
		if ( ! AccountPages::isAccountPage() ) {
			return null;
		}

		return $this->routes->match( $this->currentSubPath() );
	}
}
