<?php

declare(strict_types=1);

namespace AllCoach\Application\Analytics;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\Settings\SettingsManager;
use AllCoach\Infrastructure\Database\AnalyticsRepository;
use Carbon\CarbonImmutable;

/**
 * FREE analytics dashboard: KPI cards with period-over-period deltas and a daily
 * time-series. Advanced analytics (custom granularity, per-program filtering,
 * status/activity breakdowns) is a Pro feature implemented in the Pro plugin —
 * this service intentionally computes none of it.
 */
class AnalyticsQueryService {

	public function __construct(
		private readonly AnalyticsRepository $repository,
		private readonly SettingsManager $settingsManager,
	) {}

	/**
	 * @return array<string, mixed>
	 */
	public function getDashboard( string $from, string $to, ?int $coachId = null ): array {
		[ $prevFrom, $prevTo ] = $this->previousPeriod( $from, $to );

		$currency = (string) ( $this->settingsManager->get( 'currency' ) ?: 'USD' );

		$revenue     = $this->repository->revenueBetween( $from, $to, $coachId );
		$orders      = $this->repository->orderCountBetween( $from, $to, $coachId );
		$enrollments = $this->repository->enrollmentCountBetween( $from, $to, $coachId );
		$sessions    = $this->repository->sessionCountBetween( $from, $to, $coachId );
		$clients     = $this->repository->clientCountBetween( $from, $to, $coachId );
		$avgOrder    = $this->avgOrderRevenue( $revenue, $this->repository->paidOrderCountBetween( $from, $to, $coachId ) );

		$prevRevenue  = $this->repository->revenueBetween( $prevFrom, $prevTo, $coachId );
		$prevAvgOrder = $this->avgOrderRevenue( $prevRevenue, $this->repository->paidOrderCountBetween( $prevFrom, $prevTo, $coachId ) );

		$kpis = [
			'revenue'           => $this->kpi( $revenue, $prevRevenue )
				+ [ 'currency' => $currency ],
			'avg_order_revenue' => $this->kpi( $avgOrder, $prevAvgOrder )
				+ [ 'currency' => $currency ],
			'orders'            => $this->kpi( $orders, $this->repository->orderCountBetween( $prevFrom, $prevTo, $coachId ) ),
			'clients'           => $this->kpi( $clients, $this->repository->clientCountBetween( $prevFrom, $prevTo, $coachId ) ),
			'enrollments'       => $this->kpi( $enrollments, $this->repository->enrollmentCountBetween( $prevFrom, $prevTo, $coachId ) ),
			'sessions'          => $this->kpi( $sessions, $this->repository->sessionCountBetween( $prevFrom, $prevTo, $coachId ) ),
		];

		return [
			'range'      => [
				'from'     => $from,
				'to'       => $to,
				'group_by' => 'day',
			],
			'currency'   => $currency,
			'kpis'       => $kpis,
			'timeseries' => $this->buildTimeseries( $from, $to, $coachId ),
			// Breakdowns are Pro-only; Free returns empty sets (the UI shows a
			// blurred sample showcase instead).
			'breakdowns' => [
				'orders_by_status'    => [],
				'sessions_by_status'  => [],
				'activity_completion' => [],
				'revenue_by_program'  => [],
			],
		];
	}

	/** Average revenue per paid order (0 when there are no paid orders). */
	private function avgOrderRevenue( float $revenue, int $paidOrders ): float {
		return $paidOrders > 0 ? round( $revenue / $paidOrders, 2 ) : 0.0;
	}

	/**
	 * @return array{value: float|int, previous: float|int, change_pct: float|null}
	 */
	private function kpi( float|int $value, float|int $previous ): array {
		if ( $previous > 0 ) {
			$change = round( ( ( $value - $previous ) / $previous ) * 100, 1 );
		} elseif ( $value > 0 ) {
			$change = 100.0;
		} else {
			$change = 0.0;
		}

		return [
			'value'      => $value,
			'previous'   => $previous,
			'change_pct' => $change,
		];
	}

	/**
	 * @return array{0: string, 1: string}
	 */
	private function previousPeriod( string $from, string $to ): array {
		$start = CarbonImmutable::parse( $from )->startOfDay();
		$end   = CarbonImmutable::parse( $to )->startOfDay();
		$days  = $start->diffInDays( $end ) + 1;

		$prevTo   = $start->subDay();
		$prevFrom = $prevTo->subDays( $days - 1 );

		return [ $prevFrom->format( 'Y-m-d' ), $prevTo->format( 'Y-m-d' ) ];
	}

	/**
	 * @return list<array{date: string, revenue: float, orders: int, enrollments: int, sessions: int}>
	 */
	private function buildTimeseries( string $from, string $to, ?int $coachId = null ): array {
		$orders      = $this->repository->orderSeries( $from, $to, $coachId );
		$enrollments = $this->repository->enrollmentSeries( $from, $to, $coachId );
		$sessions    = $this->repository->sessionSeries( $from, $to, $coachId );

		$series = [];
		$cursor = CarbonImmutable::parse( $from )->startOfDay();
		$end    = CarbonImmutable::parse( $to )->startOfDay();

		while ( $cursor->lessThanOrEqualTo( $end ) ) {
			$bucket   = $cursor->format( 'Y-m-d' );
			$series[] = [
				'date'        => $bucket,
				'revenue'     => (float) ( $orders[ $bucket ]['revenue'] ?? 0 ),
				'orders'      => (int) ( $orders[ $bucket ]['orders'] ?? 0 ),
				'enrollments' => (int) ( $enrollments[ $bucket ] ?? 0 ),
				'sessions'    => (int) ( $sessions[ $bucket ] ?? 0 ),
			];
			$cursor = $cursor->addDay();
		}

		return $series;
	}
}
