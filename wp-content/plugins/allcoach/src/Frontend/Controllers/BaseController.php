<?php

declare(strict_types=1);

namespace AllCoach\Frontend\Controllers;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\RoleManager;
use AllCoach\Frontend\Router;
use AllCoach\Support\TemplateLoader;

abstract class BaseController {

	public function __construct(
		protected readonly TemplateLoader $templateLoader,
	) {}

	protected function render( string $template, array $args = [] ): void {
		$args = array_merge( $this->defaultArgs(), $args );
		$this->templateLoader->loadTemplate( $template, $args );
	}

	protected function defaultArgs(): array {
		$user = wp_get_current_user();

		return [
			'currentUser' => $user,
			'isCoach'     => in_array( RoleManager::ROLE_COACH, $user->roles, true ),
			'isClient'    => in_array( RoleManager::ROLE_CLIENT, $user->roles, true ),
			'isAdmin'     => current_user_can( 'manage_options' ),
			'routerUrl'   => fn( string $endpoint = '', string|int $value = '' ) => Router::url( $endpoint, $value ),
		];
	}

	protected function getEndpointValue(): int {
		return absint( Router::getCurrentEndpointValue() );
	}

	protected function getCurrentPage(): int {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['paged'] ) ) {
			return max( 1, absint( $_GET['paged'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		$paged = (int) get_query_var( 'paged', 0 );
		if ( $paged > 0 ) {
			return $paged;
		}

		$requestUri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		if ( preg_match( '#/page/(\d+)/?#', $requestUri, $matches ) ) {
			return max( 1, (int) $matches[1] );
		}

		return 1;
	}

	protected function redirect( string $url, int $status = 302 ): void {
		wp_safe_redirect( $url, $status );
		exit;
	}
}
