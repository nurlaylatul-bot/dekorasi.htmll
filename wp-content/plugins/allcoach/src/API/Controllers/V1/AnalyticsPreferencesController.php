<?php

declare(strict_types=1);

namespace AllCoach\API\Controllers\V1;

defined( 'ABSPATH' ) || exit;

use AllCoach\API\RestController;

/**
 * Per-user analytics dashboard preferences: GET/POST /allcoach/v1/analytics/preferences.
 *
 * Stores the KPI card metric picks and the overview chart's focused metric in
 * user meta (not query params), so a coach sees the same picks wherever they
 * open the dashboard rather than only on the device/browser that set them.
 *
 * Each preference lives in its own meta key (not one shared blob) because the
 * KPI cards and the overview chart save independently of each other — a
 * shared blob would need a read-merge-write per save, and two saves racing
 * would let the later write's stale read clobber the earlier one's change.
 */
class AnalyticsPreferencesController extends RestController {

	protected string $restBase = 'analytics/preferences';

	private const KPI_SLOTS_META_KEY = '_allcoach_analytics_kpi_slots';

	private const OVERVIEW_FOCUS_META_KEY = '_allcoach_analytics_overview_focus';

	private const BREAKDOWN_SLOTS_META_KEY = '_allcoach_analytics_breakdown_slots';

	private const METRIC_KEYS = [ 'revenue', 'avg_order_revenue', 'orders', 'clients', 'enrollments', 'sessions' ];

	private const FOCUS_KEYS = [ 'all', 'revenue', 'orders', 'enrollments', 'sessions' ];

	private const BREAKDOWN_KEYS = [ 'orders_by_status', 'sessions_by_status', 'activity_completion', 'revenue_by_program' ];

	public function registerRoutes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->restBase,
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'getPreferences' ],
					'permission_callback' => fn() => $this->permCoachOrAdmin(),
				],
				[
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'updatePreferences' ],
					'permission_callback' => fn() => $this->permCoachOrAdmin(),
					'args'                => [
						'kpi_slots'      => [
							'description' => __( 'Metric shown in each KPI card slot.', 'allcoach' ),
							'type'        => 'array',
							'items'       => [
								'type' => 'string',
								'enum' => self::METRIC_KEYS,
							],
						],
						'overview_focus' => $this->argEnum(
							description: __( 'Which metric the overview chart is focused on.', 'allcoach' ),
							values:      self::FOCUS_KEYS,
						),
						'breakdown_slots' => [
							'description' => __( 'Breakdown shown in each donut slot.', 'allcoach' ),
							'type'        => 'array',
							'items'       => [
								'type' => 'string',
								'enum' => self::BREAKDOWN_KEYS,
							],
						],
					],
				],
			]
		);
	}

	public function getPreferences(): \WP_REST_Response {
		return $this->successResponse( $this->readPreferences() );
	}

	public function updatePreferences( \WP_REST_Request $request ): \WP_REST_Response {
		$userId = get_current_user_id();

		$slots = $request->get_param( 'kpi_slots' );
		if ( is_array( $slots ) ) {
			$slots = array_values(
				array_filter(
					array_map( 'sanitize_key', $slots ),
					fn( $slot ) => in_array( $slot, self::METRIC_KEYS, true )
				)
			);
			if ( $slots ) {
				update_user_meta( $userId, self::KPI_SLOTS_META_KEY, $slots );
			}
		}

		$focus = $request->get_param( 'overview_focus' );
		if ( is_string( $focus ) && in_array( $focus, self::FOCUS_KEYS, true ) ) {
			update_user_meta( $userId, self::OVERVIEW_FOCUS_META_KEY, $focus );
		}

		$breakdowns = $request->get_param( 'breakdown_slots' );
		if ( is_array( $breakdowns ) ) {
			$breakdowns = array_values(
				array_filter(
					array_map( 'sanitize_key', $breakdowns ),
					fn( $slot ) => in_array( $slot, self::BREAKDOWN_KEYS, true )
				)
			);
			if ( $breakdowns ) {
				update_user_meta( $userId, self::BREAKDOWN_SLOTS_META_KEY, $breakdowns );
			}
		}

		return $this->successResponse( $this->readPreferences() );
	}

	private function readPreferences(): array {
		$userId     = get_current_user_id();
		$slots      = get_user_meta( $userId, self::KPI_SLOTS_META_KEY, true );
		$focus      = get_user_meta( $userId, self::OVERVIEW_FOCUS_META_KEY, true );
		$breakdowns = get_user_meta( $userId, self::BREAKDOWN_SLOTS_META_KEY, true );

		return [
			'kpi_slots'       => is_array( $slots ) ? $slots : [],
			'overview_focus'  => is_string( $focus ) && $focus !== '' ? $focus : null,
			'breakdown_slots' => is_array( $breakdowns ) ? $breakdowns : [],
		];
	}
}
