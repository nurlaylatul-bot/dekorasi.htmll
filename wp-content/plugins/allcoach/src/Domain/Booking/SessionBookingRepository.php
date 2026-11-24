<?php

declare(strict_types=1);

namespace AllCoach\Domain\Booking;

defined( 'ABSPATH' ) || exit;

interface SessionBookingRepository {

	public function findById( int $id ): ?SessionBooking;

	/**
	 * @return array<SessionBooking>
	 */
	public function findWithFilter(
		SessionBookingFilter $filter,
		int $limit = 100,
		int $offset = 0
	): array;

	public function countWithFilter(
		SessionBookingFilter $filter,
	): int;

	public function hasClientOverlap(
		int $clientId,
		string $date,
		string $startTime,
		string $endTime,
		?int $excludeId = null
	): bool;

	public function hasOverlap( int $coachId, string $date, string $startTime, string $endTime, ?int $excludeId = null ): bool;

	public function save( SessionBooking $sessionBooking ): SessionBooking;

	public function delete( int $id ): bool;

	/**
	 * Delete all bookings where the user is the client.
	 *
	 * @return int Number of bookings deleted.
	 */
	public function deleteByClient( int $clientId ): int;

	/**
	 * Delete all bookings where the user is the coach.
	 *
	 * @return int Number of bookings deleted.
	 */
	public function deleteByCoach( int $coachId ): int;
}
