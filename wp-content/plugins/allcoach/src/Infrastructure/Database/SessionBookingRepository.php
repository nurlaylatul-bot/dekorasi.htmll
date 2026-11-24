<?php

declare(strict_types=1);

namespace AllCoach\Infrastructure\Database;

defined( 'ABSPATH' ) || exit;

use AllCoach\Domain\Booking\MeetingType;
use AllCoach\Domain\Booking\SessionBooking;
use AllCoach\Domain\Booking\SessionBookingRepository as SessionBookingRepositoryInterface;
use AllCoach\Domain\Booking\SessionBookingStatus;
use AllCoach\Domain\Booking\SessionBookingFilter;
use Carbon\CarbonImmutable;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter

class SessionBookingRepository implements SessionBookingRepositoryInterface {

	private string $table;

	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'allcoach_session_bookings';
	}

	public function findById( int $id ): ?SessionBooking {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE id = %d LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$id
			),
			ARRAY_A
		);

		return $row ? $this->hydrate( $row ) : null;
	}

	public function findWithFilter(
		SessionBookingFilter $filter,
		int $limit = 100,
		int $offset = 0
	): array {
		global $wpdb;

		[ $where, $bindings ] = $this->buildWhere( $filter );

		$sql = "SELECT * FROM {$this->table} sb{$where} ORDER BY scheduled_date ASC, start_time ASC LIMIT %d OFFSET %d"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$bindings[] = $limit;
		$bindings[] = $offset;

		$rows = $wpdb->get_results(
			$wpdb->prepare( $sql, $bindings ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		) ?: [];

		return array_map( [ $this, 'hydrate' ], $rows );
	}

	public function countWithFilter( SessionBookingFilter $filter ): int {
		global $wpdb;

		[ $where, $bindings ] = $this->buildWhere( $filter );

		$sql = "SELECT COUNT(*) FROM {$this->table} sb{$where}"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( empty( $bindings ) ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			return (int) $wpdb->get_var( $sql );
		}

		return (int) $wpdb->get_var(
			$wpdb->prepare( $sql, $bindings ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);
	}

	public function hasOverlap(
		int $coachId,
		string $date,
		string $startTime,
		string $endTime,
		?int $excludeId = null
	): bool {
		return $this->overlapExists( 'coach_id', $coachId, $date, $startTime, $endTime, $excludeId );
	}

	public function hasClientOverlap(
		int $clientId,
		string $date,
		string $startTime,
		string $endTime,
		?int $excludeId = null
	): bool {
		return $this->overlapExists( 'client_id', $clientId, $date, $startTime, $endTime, $excludeId );
	}

	public function save( SessionBooking $sessionBooking ): SessionBooking {
		return $sessionBooking->isNew()
			? $this->insert( $sessionBooking )
			: $this->update( $sessionBooking );
	}

	public function delete( int $id ): bool {
		global $wpdb;

		return $wpdb->delete( $this->table, [ 'id' => $id ], [ '%d' ] ) !== false;
	}

	public function deleteByClient( int $clientId ): int {
		global $wpdb;
		$result = $wpdb->delete( $this->table, [ 'client_id' => $clientId ], [ '%d' ] );
		return $result !== false ? $result : 0;
	}

	public function deleteByCoach( int $coachId ): int {
		global $wpdb;
		$result = $wpdb->delete( $this->table, [ 'coach_id' => $coachId ], [ '%d' ] );
		return $result !== false ? $result : 0;
	}

	private function insert( SessionBooking $sb ): SessionBooking {
		global $wpdb;

		// Inherit demo flags from the parent enrollment so admin queries can exclude them.
		$enrollmentsTable = $wpdb->prefix . 'allcoach_enrollments';
		$demoRow          = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT is_demo, demo_token, expires_at FROM {$enrollmentsTable} WHERE id = %d LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$sb->getEnrollmentId()
			),
			ARRAY_A
		);

		$isDemo    = (int) ( $demoRow['is_demo'] ?? 0 );
		$demoToken = $demoRow['demo_token'] ?? null;
		$expiresAt = $demoRow['expires_at'] ?? null;

		$result = $wpdb->insert(
			$this->table,
			[
				'enrollment_id'     => $sb->getEnrollmentId(),
				'client_id'         => $sb->getClientId(),
				'coach_id'          => $sb->getCoachId(),
				'program_id'        => $sb->getProgramId(),
				'scheduled_date'    => $sb->getScheduledDate(),
				'start_time'        => $sb->getStartTime(),
				'end_time'          => $sb->getEndTime(),
				'session_number'    => $sb->getSessionNumber(),
				'status'            => $sb->getStatus()->getValue(),
				'notes'             => $sb->getNotes(),
				'timezone'          => $sb->getTimezone(),
				'meeting_url'       => $sb->getMeetingUrl(),
				'calendar_event_id' => $sb->getCalendarEventId(),
				'meeting_type'      => $sb->getMeetingType()->getValue(),
				'location'          => $sb->getLocation(),
				'is_demo'           => $isDemo,
				'demo_token'        => $demoToken,
				'expires_at'        => $expiresAt,
				'created_at'        => $sb->getCreatedAt()->format( 'Y-m-d H:i:s' ),
				'updated_at'        => $sb->getUpdatedAt()?->format( 'Y-m-d H:i:s' ),
			],
			[ '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' ]
		);

		if ( false === $result ) {
			throw new \RuntimeException( 'Failed to create session booking.' );
		}

		return SessionBooking::reconstitute(
			id:              $wpdb->insert_id,
			enrollmentId:    $sb->getEnrollmentId(),
			clientId:        $sb->getClientId(),
			coachId:         $sb->getCoachId(),
			programId:       $sb->getProgramId(),
			scheduledDate:   $sb->getScheduledDate(),
			startTime:       $sb->getStartTime(),
			endTime:         $sb->getEndTime(),
			sessionNumber:   $sb->getSessionNumber(),
			status:          $sb->getStatus(),
			notes:           $sb->getNotes(),
			meetingUrl:      $sb->getMeetingUrl(),
			calendarEventId: $sb->getCalendarEventId(),
			createdAt:       $sb->getCreatedAt(),
			updatedAt:       $sb->getUpdatedAt(),
			timezone:        $sb->getTimezone(),
			meetingType:     $sb->getMeetingType(),
			location:        $sb->getLocation(),
		);
	}

	private function update( SessionBooking $sb ): SessionBooking {
		global $wpdb;

		$result = $wpdb->update(
			$this->table,
			[
				'scheduled_date'    => $sb->getScheduledDate(),
				'start_time'        => $sb->getStartTime(),
				'end_time'          => $sb->getEndTime(),
				'status'            => $sb->getStatus()->getValue(),
				'notes'             => $sb->getNotes(),
				'meeting_url'       => $sb->getMeetingUrl(),
				'calendar_event_id' => $sb->getCalendarEventId(),
				'meeting_type'      => $sb->getMeetingType()->getValue(),
				'location'          => $sb->getLocation(),
				'updated_at'        => current_time( 'mysql' ),
			],
			[ 'id' => $sb->getId() ],
			[ '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ],
			[ '%d' ]
		);

		if ( $result === false ) {
			throw new \RuntimeException( 'Failed to update session booking.' );
		}

		return $sb;
	}

	/**
	 * Builds a WHERE clause + ordered bindings array from a SessionBookingFilter.
	 * Both findWithFilter() and countWithFilter() use this — single source of truth.
	 *
	 * @return array{0: string, 1: array<mixed>}
	 */
	private function buildWhere( SessionBookingFilter $filter ): array {

		/** @var \wpdb $wpdb */
		global $wpdb;
		$conditions = [];
		$bindings   = [];

		if ( $filter->enrollmentId !== null ) {
			$conditions[] = 'enrollment_id = %d';
			$bindings[]   = $filter->enrollmentId;
		}

		if ( $filter->clientId !== null ) {
			$conditions[] = 'client_id = %d';
			$bindings[]   = $filter->clientId;
		}

		if ( $filter->coachId !== null ) {
			$conditions[] = 'coach_id = %d';
			$bindings[]   = $filter->coachId;
		}

		if ( $filter->status !== null ) {
			$conditions[] = 'status = %s';
			$bindings[]   = $filter->status;
		}

		if ( $filter->excludeCancelled === true ) {
			$conditions[] = "status != 'cancelled'";
			// No binding needed — value is hardcoded, not user-supplied.
		}

		if ( $filter->excludeDemo === true ) {
			$conditions[] = 'sb.is_demo = 0';
		}

		if ( $filter->fromDate !== null ) {
			$conditions[] = 'scheduled_date >= %s';
			$bindings[]   = $filter->fromDate;
		}

		if ( $filter->toDate !== null ) {
			$conditions[] = 'scheduled_date <= %s';
			$bindings[]   = $filter->toDate;
		}

		if ( $filter->search !== null && $filter->search !== '' ) {
			$searchTerm   = '%' . $this->escapeLike( $filter->search ) . '%';
			$conditions[] = '(
        EXISTS (
            SELECT 1 FROM ' . $wpdb->users . ' u
            WHERE u.ID = sb.client_id
              AND u.display_name LIKE %s
        )
        OR EXISTS (
            SELECT 1 FROM ' . $wpdb->usermeta . ' um_first
            INNER JOIN ' . $wpdb->usermeta . ' um_last
                ON um_last.user_id = um_first.user_id
               AND um_last.meta_key = \'last_name\'
            WHERE um_first.user_id = sb.client_id
              AND um_first.meta_key = \'first_name\'
              AND CONCAT(um_first.meta_value, \' \', um_last.meta_value) LIKE %s
        )
        OR EXISTS (
            SELECT 1 FROM ' . $wpdb->prefix . 'allcoach_enrollments e
			INNER JOIN ' . $wpdb->posts . ' p ON p.ID = e.program_id
			WHERE e.id = sb.enrollment_id
				and p.post_type = \'program\'
              AND p.post_status = \'publish\'
              AND p.post_title LIKE %s
        )
    )';
			$bindings[]   = $searchTerm;
			$bindings[]   = $searchTerm;
			$bindings[]   = $searchTerm;
		}

		$where = empty( $conditions )
			? ''
			: ' WHERE ' . implode( ' AND ', $conditions );

		return [ $where, $bindings ];
	}

	/**
	 * Shared overlap check for both coach and client.
	 * Finds any non-cancelled booking on the same date whose time window
	 * intersects [startTime, endTime).
	 */
	private function overlapExists(
		string $ownerColumn,
		int $ownerId,
		string $date,
		string $startTime,
		string $endTime,
		?int $excludeId
	): bool {
		global $wpdb;

		// Validate column name against a whitelist — never interpolate user input.
		$allowedColumns = [ 'coach_id', 'client_id' ];
		if ( ! in_array( $ownerColumn, $allowedColumns, true ) ) {
			throw new \InvalidArgumentException( esc_html( "Invalid owner column: {$ownerColumn}" ) );
		}

		$sql      = "SELECT COUNT(*) FROM {$this->table} WHERE {$ownerColumn} = %d AND scheduled_date = %s AND start_time < %s AND end_time > %s AND status != 'cancelled'"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$bindings = [ $ownerId, $date, $endTime, $startTime ];

		if ( $excludeId !== null ) {
			$sql       .= ' AND id != %d';
			$bindings[] = $excludeId;
		}

		return (int) $wpdb->get_var(
			$wpdb->prepare( $sql, $bindings ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		) > 0;
	}

	private function escapeLike( string $value ): string {
		global $wpdb;
		return $wpdb->esc_like( $value );
	}

	/**
	 * @param array<string, mixed> $row
	 */
	private function hydrate( array $row ): SessionBooking {
		return SessionBooking::reconstitute(
			id:              (int) $row['id'],
			enrollmentId:    (int) $row['enrollment_id'],
			clientId:        (int) $row['client_id'],
			coachId:         (int) $row['coach_id'],
			programId:       (int) $row['program_id'],
			scheduledDate:   $row['scheduled_date'],
			startTime:       $row['start_time'],
			endTime:         $row['end_time'],
			sessionNumber:   (int) $row['session_number'],
			status:          SessionBookingStatus::fromString( $row['status'] ),
			notes:           $row['notes'] ?? null,
			meetingUrl:      $row['meeting_url'] ?? null,
			calendarEventId: $row['calendar_event_id'] ?? null,
			createdAt:       CarbonImmutable::parse( $row['created_at'] ),
			updatedAt: ! empty( $row['updated_at'] ) ? CarbonImmutable::parse( $row['updated_at'] ) : null,
			timezone:        $row['timezone'] ?? '',
			meetingType:     MeetingType::fromString( $row['meeting_type'] ?? 'google_meet' ),
			location:        $row['location'] ?? null,
		);
	}
}
