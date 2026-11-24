<?php

declare(strict_types=1);

namespace AllCoach\Frontend;

defined( 'ABSPATH' ) || exit;

use AllCoach\Traits\Hooks;
use AllCoach\Frontend\Router\Router as CoreRouter;


final class Router {

	use Hooks;


	public function register(): void {}

	public static function getCurrentEndpoint(): string {
		return CoreRouter::instance()->currentSubPath();
	}

	public static function getCurrentEndpointValue(): string {
		return (string) get_query_var( 'resource_id', '' );
	}

	public static function url(
		string $endpoint = '',
		string|int $id = '',
		int $page = 0,
		array $query = []
	): string {
		$params = $query;

		if ( $id !== '' && $id !== 0 ) {
			$params['resource_id'] = (string) $id;
		}

		if ( $page > 1 ) {
			$params['page'] = $page;
		}

		return CoreRouter::instance()->buildUrl( $endpoint, $params );
	}
}
