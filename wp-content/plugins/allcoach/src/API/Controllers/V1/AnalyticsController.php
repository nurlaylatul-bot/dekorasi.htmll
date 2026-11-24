<?php

declare(strict_types=1);

namespace AllCoach\API\Controllers\V1;

defined( 'ABSPATH' ) || exit;

use AllCoach\API\RestController;
use AllCoach\Application\Analytics\AnalyticsQueryService;
use Carbon\CarbonImmutable;

/**
 * FREE analytics endpoint: GET /allcoach/v1/analytics.
 *
 * Basic KPIs + a daily time-series for the last 30 days. Advanced analytics
 * (custom range, week/month granularity, per-program filtering, breakdowns)
 * is a Pro feature served by the Pro plugin's /analytics/advanced route.
 */
class AnalyticsController extends RestController {

	protected string $restBase = 'analytics';

	public function __construct(
		private readonly AnalyticsQueryService $queryService,
	) {}

	public function registerRoutes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->restBase,
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'getDashboard' ],
					'permission_callback' => fn() => $this->permCoachOrAdmin(),
					'args'                => [
						'from_date' => $this->argString(
							description: __( 'Range start date (YYYY-MM-DD).', 'allcoach' ),
						),
						'to_date'   => $this->argString(
							description: __( 'Range end date (YYYY-MM-DD).', 'allcoach' ),
						),
					],
				],
			]
		);
	}

	public function getDashboard( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		try {
			$today   = CarbonImmutable::now( wp_timezone() )->startOfDay();
			$default = $today->subDays( 29 );

			$from = $this->normalizeDate( (string) ( $request->get_param( 'from_date' ) ?? '' ), $default );
			$to   = $this->normalizeDate( (string) ( $request->get_param( 'to_date' ) ?? '' ), $today );

			if ( CarbonImmutable::parse( $from )->greaterThan( CarbonImmutable::parse( $to ) ) ) {
				[ $from, $to ] = [ $to, $from ];
			}

			// Scope to the caller's own data unless they are an admin. The
			// permission callback already limits callers to coach|admin, so a
			// non-admin here is always a coach.
			$coachId = $this->isAdmin() ? null : get_current_user_id();

			return $this->successResponse(
				$this->queryService->getDashboard( $from, $to, $coachId )
			);
		} catch ( \Throwable $e ) {
			return $this->exceptionToResponse( $e );
		}
	}

	private function normalizeDate( string $value, CarbonImmutable $fallback ): string {
		$value = trim( $value );
		if ( $value === '' ) {
			return $fallback->format( 'Y-m-d' );
		}
		try {
			return CarbonImmutable::parse( $value )->format( 'Y-m-d' );
		} catch ( \Throwable ) {
			return $fallback->format( 'Y-m-d' );
		}
	}
}
