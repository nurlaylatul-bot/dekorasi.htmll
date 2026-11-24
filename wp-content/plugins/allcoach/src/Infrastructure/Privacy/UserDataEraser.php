<?php

declare(strict_types=1);

namespace AllCoach\Infrastructure\Privacy;

defined( 'ABSPATH' ) || exit;

use AllCoach\Domain\Activity\ActivityCompletionRepository;
use AllCoach\Domain\Booking\SessionBookingRepository;
use AllCoach\Domain\Enrollment\EnrollmentRepository;
use AllCoach\Domain\Order\OrderRepository;
use AllCoach\Infrastructure\Database\ConsentRepository;
use AllCoach\Traits\Hooks;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

/**
 * Removes all AllCoach data tied to a user (GDPR cascade delete).
 *
 * Purges custom-table rows across orders, enrollments, session bookings,
 * activity completions, demo sessions and consent records, plus every
 * `_allcoach_*` user meta key. Runs when a WordPress user is deleted.
 */
class UserDataEraser {

	use Hooks;

	public function __construct(
		private OrderRepository $orders,
		private EnrollmentRepository $enrollments,
		private SessionBookingRepository $sessionBookings,
		private ActivityCompletionRepository $activityCompletions,
		private ConsentRepository $consents,
	) {}

	public function erase( int $userId ): int {
		if ( $userId <= 0 ) {
			return 0;
		}

		/** Fires before a user's AllCoach data is erased. */
		$this->doAction( 'allcoach:user:before_erase', $userId );

		$removed = 0;
		$removed += $this->orders->deleteByUser( $userId );
		$removed += $this->enrollments->deleteByUser( $userId );
		$removed += $this->sessionBookings->deleteByClient( $userId );
		$removed += $this->sessionBookings->deleteByCoach( $userId );
		$removed += $this->activityCompletions->deleteByClient( $userId );
		$removed += $this->consents->deleteByUser( $userId );

		$this->deleteDemoSessions( $userId );
		$this->deleteUserMeta( $userId );

		/** Fires after a user's AllCoach data has been erased. */
		$this->doAction( 'allcoach:user:erased', $userId );

		return $removed;
	}

	/**
	 * Remove demo-session rows where the user is either the demo or the original user.
	 */
	private function deleteDemoSessions( int $userId ): void {
		global $wpdb;

		$table = $wpdb->prefix . 'allcoach_demo_sessions';

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE demo_user_id = %d OR original_user_id = %d",
				$userId,
				$userId
			)
		);
	}

	/**
	 * Remove every AllCoach user meta key ( `_allcoach_*` ) for the user.
	 *
	 * Covers profile fields, Google OAuth tokens and cart data in one pass,
	 * matching the prefix convention used by uninstall.php.
	 */
	private function deleteUserMeta( int $userId ): void {
		global $wpdb;

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key LIKE %s", // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				$userId,
				$wpdb->esc_like( '_allcoach_' ) . '%'
			)
		);
	}
}
