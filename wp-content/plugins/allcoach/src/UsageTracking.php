<?php

declare(strict_types=1);

namespace AllCoach;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter

use AllCoach\Core\Constants;
use AllCoach\Core\RoleManager;
use AllCoach\Core\Settings\SettingsManager;

/**
 * Populates the ThemeGrill SDK Logger module's '{key}_logger_data' filter with
 * aggregate, anonymized usage counts. The Logger module already handles the
 * opt-in gate, scheduling, and transport - this only supplies the payload.
 */
class UsageTracking {

	private const NOTIFICATION_KEYS = [
		'notify_new_booking_confirmation',
		'notify_new_booking_alert',
		'notify_cancellation_notice',
		'notify_24_hour_reminder',
		'notify_order_confirmed',
		'notify_order_cancelled',
		'notify_welcome_client',
		'notify_session_booking_confirmation',
		'notify_session_completed_follow_up',
		'notify_session_cancelled_alert',
	];

	public function __construct( private readonly SettingsManager $settings ) {
		add_filter( Constants::productKey() . '_logger_data', [ $this, 'buildPayload' ] );
	}

	public function buildPayload( array $data ): array {
		if ( ! $this->settings->get( 'send_anonymous_usage_data' ) ) {
			return $data;
		}

		global $wpdb;

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . 'allcoach_orders' ) ) === null ) {
			return $data;
		}

		return [
			'is_premium' => (bool) apply_filters( 'allcoach_is_premium', false ),
			'counts'     => [
				'programs_published'         => (int) ( wp_count_posts( 'allcoach_program' )->publish ?? 0 ),
				'coaches'                    => $this->countUsersByRole( RoleManager::ROLE_COACH ),
				'clients'                    => $this->countUsersByRole( RoleManager::ROLE_CLIENT ),
				'enrollments_active'         => $this->countByStatus( 'allcoach_enrollments', 'active' ),
				'orders_total'               => $this->countAll( 'allcoach_orders' ),
				'orders_paid'                => $this->countByStatus( 'allcoach_orders', 'paid' ),
				'orders_failed'              => $this->countByStatus( 'allcoach_orders', 'failed' ),
				'orders_refunded'            => $this->countByStatus( 'allcoach_orders', 'refunded' ),
				'session_bookings_total'     => $this->countAll( 'allcoach_session_bookings' ),
				'session_bookings_completed' => $this->countByStatus( 'allcoach_session_bookings', 'completed' ),
				'session_bookings_cancelled' => $this->countByStatus( 'allcoach_session_bookings', 'cancelled' ),
				'activity_completions_total' => $this->countAll( 'allcoach_activity_completions' ),
			],
			'settings'   => [
				'currency'                 => (string) $this->settings->get( 'currency' ),
				'payment_offline_enabled'  => (bool) $this->settings->get( 'payment_offline_enabled' ),
				'google_meet_enabled'      => (bool) $this->settings->get( 'google_meet_enabled' ),
				'notifications_enabled'    => $this->countEnabledNotifications(),
				'delete_data_on_uninstall' => (bool) $this->settings->get( 'delete_data_on_uninstall' ),
			],
		];
	}

	private function countUsersByRole( string $role ): int {
		$counts = count_users();

		return (int) ( $counts['avail_roles'][ $role ] ?? 0 );
	}

	private function countAll( string $table ): int {
		global $wpdb;

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}{$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	private function countByStatus( string $table, string $status ): int {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}{$table} WHERE status = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$status
			)
		);
	}

	private function countEnabledNotifications(): int {
		$enabled = 0;

		foreach ( self::NOTIFICATION_KEYS as $key ) {
			if ( $this->settings->get( $key ) ) {
				++$enabled;
			}
		}

		return $enabled;
	}
}
