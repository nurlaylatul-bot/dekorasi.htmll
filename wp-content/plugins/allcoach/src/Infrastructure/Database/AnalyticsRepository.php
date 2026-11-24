<?php

declare(strict_types=1);

namespace AllCoach\Infrastructure\Database;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter

/**
 * Read-model repository for the FREE analytics dashboard: basic KPI totals and
 * a daily time-series. Advanced analytics (per-program filtering, week/month
 * bucketing, status/activity breakdowns) is a Pro feature and lives only in the
 * Pro plugin (AllCoachPro\Analytics\ProAnalyticsRepository) — not here.
 *
 * All queries exclude demo data (`is_demo = 0`). Ranges are inclusive `YYYY-MM-DD`.
 *
 * Every method takes an optional `$coachId`: when non-null the results are
 * scoped to programs owned by that coach (post meta `_program_coach_id`), so a
 * coach only ever sees their own numbers. `null` means admin/unscoped.
 */
class AnalyticsRepository {

	private string $orders;
	private string $orderItems;
	private string $enrollments;
	private string $sessions;
	private string $postmeta;

	public function __construct() {
		global $wpdb;
		$this->orders      = $wpdb->prefix . 'allcoach_orders';
		$this->orderItems  = $wpdb->prefix . 'allcoach_order_items';
		$this->enrollments = $wpdb->prefix . 'allcoach_enrollments';
		$this->sessions    = $wpdb->prefix . 'allcoach_session_bookings';
		$this->postmeta    = $wpdb->postmeta;
	}

	// -------------------------------------------------------------------------
	// KPI totals
	// -------------------------------------------------------------------------

	/** Net revenue (paid minus refunded) within the inclusive date range. */
	public function revenueBetween( string $from, string $to, ?int $coachId = null ): float {
		global $wpdb;

		[ $dateCond, $dateParams ]   = $this->dateRange( 'created_at', $from, $to, true );
		[ $coachCond, $coachParams ] = $this->coachOrderCond( $coachId );
		$params                      = array_merge( $dateParams, $coachParams );

		return (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE( SUM( CASE WHEN status = 'paid' THEN total WHEN status = 'refunded' THEN -total ELSE 0 END ), 0 )
				FROM {$this->orders}
				WHERE is_demo = 0 AND {$dateCond}{$coachCond}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				...$params
			)
		);
	}

	/** Count of all orders created within the range. */
	public function orderCountBetween( string $from, string $to, ?int $coachId = null ): int {
		[ $cond, $params ] = $this->coachOrderCond( $coachId );
		return $this->countBetween( $this->orders, 'created_at', $from, $to, true, $cond, $params );
	}

	/** Count of paid orders created within the range (denominator for avg order revenue). */
	public function paidOrderCountBetween( string $from, string $to, ?int $coachId = null ): int {
		global $wpdb;

		[ $dateCond, $dateParams ]   = $this->dateRange( 'created_at', $from, $to, true );
		[ $coachCond, $coachParams ] = $this->coachOrderCond( $coachId );
		$params                      = array_merge( $dateParams, $coachParams );

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->orders}
				WHERE is_demo = 0 AND status = 'paid' AND {$dateCond}{$coachCond}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				...$params
			)
		);
	}

	/** Count of distinct clients enrolled within the range. */
	public function clientCountBetween( string $from, string $to, ?int $coachId = null ): int {
		global $wpdb;

		[ $dateCond, $dateParams ]   = $this->dateRange( 'enrolled_at', $from, $to, true );
		[ $coachCond, $coachParams ] = $this->coachProgramCond( $coachId );
		$params                      = array_merge( $dateParams, $coachParams );

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT( DISTINCT user_id ) FROM {$this->enrollments}
				WHERE is_demo = 0 AND {$dateCond}{$coachCond}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				...$params
			)
		);
	}

	/** Count of enrollments created within the range. */
	public function enrollmentCountBetween( string $from, string $to, ?int $coachId = null ): int {
		[ $cond, $params ] = $this->coachProgramCond( $coachId );
		return $this->countBetween( $this->enrollments, 'enrolled_at', $from, $to, true, $cond, $params );
	}

	/** Count of session bookings scheduled within the range. */
	public function sessionCountBetween( string $from, string $to, ?int $coachId = null ): int {
		[ $cond, $params ] = $this->coachProgramCond( $coachId );
		return $this->countBetween( $this->sessions, 'scheduled_date', $from, $to, false, $cond, $params );
	}

	// -------------------------------------------------------------------------
	// Daily time series
	// -------------------------------------------------------------------------

	/**
	 * Orders + net revenue per day.
	 *
	 * @return array<string, array{orders: int, revenue: float}> Keyed by Y-m-d.
	 */
	public function orderSeries( string $from, string $to, ?int $coachId = null ): array {
		global $wpdb;

		[ $dateCond, $dateParams ]   = $this->dateRange( 'created_at', $from, $to, true );
		[ $coachCond, $coachParams ] = $this->coachOrderCond( $coachId );
		$params                      = array_merge( $dateParams, $coachParams );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE( created_at ) AS bucket, COUNT(*) AS orders,
					COALESCE( SUM( CASE WHEN status = 'paid' THEN total WHEN status = 'refunded' THEN -total ELSE 0 END ), 0 ) AS revenue
				FROM {$this->orders}
				WHERE is_demo = 0 AND {$dateCond}{$coachCond}
				GROUP BY bucket", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				...$params
			),
			ARRAY_A
		) ?: [];

		$out = [];
		foreach ( $rows as $row ) {
			$out[ (string) $row['bucket'] ] = [
				'orders'  => (int) $row['orders'],
				'revenue' => (float) $row['revenue'],
			];
		}

		return $out;
	}

	/**
	 * Enrollment count per day.
	 *
	 * @return array<string, int> Keyed by Y-m-d.
	 */
	public function enrollmentSeries( string $from, string $to, ?int $coachId = null ): array {
		[ $cond, $params ] = $this->coachProgramCond( $coachId );
		return $this->countSeries( $this->enrollments, 'enrolled_at', $from, $to, true, $cond, $params );
	}

	/**
	 * Session-booking count per day.
	 *
	 * @return array<string, int> Keyed by Y-m-d.
	 */
	public function sessionSeries( string $from, string $to, ?int $coachId = null ): array {
		[ $cond, $params ] = $this->coachProgramCond( $coachId );
		return $this->countSeries( $this->sessions, 'scheduled_date', $from, $to, false, $cond, $params );
	}

	// -------------------------------------------------------------------------
	// Internals
	// -------------------------------------------------------------------------

	/**
	 * Coach-ownership condition for the orders table. Orders carry no coach
	 * column, so ownership is derived through order items → the program post's
	 * `_program_coach_id` meta.
	 *
	 * @return array{0: string, 1: array<int, string>} SQL fragment + bind params.
	 */
	private function coachOrderCond( ?int $coachId ): array {
		if ( $coachId === null ) {
			return [ '', [] ];
		}
		return [
			" AND id IN ( SELECT oi.order_id FROM {$this->orderItems} oi
				INNER JOIN {$this->postmeta} pm ON pm.post_id = oi.program_id
					AND pm.meta_key = '_program_coach_id' AND pm.meta_value = %s )",
			[ (string) $coachId ],
		];
	}

	/**
	 * Coach-ownership condition for tables with a direct `program_id` column
	 * (enrollments, session bookings), scoped to the coach's programs.
	 *
	 * @return array{0: string, 1: array<int, string>} SQL fragment + bind params.
	 */
	private function coachProgramCond( ?int $coachId ): array {
		if ( $coachId === null ) {
			return [ '', [] ];
		}
		return [
			" AND program_id IN ( SELECT post_id FROM {$this->postmeta}
				WHERE meta_key = '_program_coach_id' AND meta_value = %s )",
			[ (string) $coachId ],
		];
	}

	/**
	 * @param array<int, string> $extraParams
	 */
	private function countBetween( string $table, string $dateColumn, string $from, string $to, bool $isDatetime, string $extraCond = '', array $extraParams = [] ): int {
		global $wpdb;

		[ $cond, $params ] = $this->dateRange( $dateColumn, $from, $to, $isDatetime );
		$params            = array_merge( $params, $extraParams );

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE is_demo = 0 AND {$cond}{$extraCond}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				...$params
			)
		);
	}

	/**
	 * @param array<int, string> $extraParams
	 * @return array<string, int>
	 */
	private function countSeries( string $table, string $dateColumn, string $from, string $to, bool $isDatetime, string $extraCond = '', array $extraParams = [] ): array {
		global $wpdb;

		$bucket            = "DATE( {$dateColumn} )";
		[ $cond, $params ] = $this->dateRange( $dateColumn, $from, $to, $isDatetime );
		$params            = array_merge( $params, $extraParams );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT {$bucket} AS bucket, COUNT(*) AS c FROM {$table}
				WHERE is_demo = 0 AND {$cond}{$extraCond}
				GROUP BY bucket", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				...$params
			),
			ARRAY_A
		) ?: [];

		$out = [];
		foreach ( $rows as $row ) {
			$out[ (string) $row['bucket'] ] = (int) $row['c'];
		}

		return $out;
	}

	/**
	 * Build an index-friendly date-range condition for the given column.
	 *
	 * For datetime columns this returns a half-open range
	 * (`col >= from AND col < to+1day`) instead of `DATE(col) BETWEEN …` so
	 * MySQL can use an index on the raw column. Pure DATE columns (e.g.
	 * `scheduled_date`) already index fine with an inclusive BETWEEN.
	 *
	 * @return array{0: string, 1: array<int, string>} SQL fragment + bind params.
	 */
	private function dateRange( string $column, string $from, string $to, bool $isDatetime ): array {
		if ( $isDatetime ) {
			$toExclusive = ( new \DateTimeImmutable( $to ) )->modify( '+1 day' )->format( 'Y-m-d' );
			return [ "{$column} >= %s AND {$column} < %s", [ $from, $toExclusive ] ];
		}
		return [ "{$column} BETWEEN %s AND %s", [ $from, $to ] ];
	}
}
