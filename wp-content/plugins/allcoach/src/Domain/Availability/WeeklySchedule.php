<?php

declare(strict_types=1);

namespace AllCoach\Domain\Availability;

defined( 'ABSPATH' ) || exit;

final class WeeklySchedule {

	private const DAYS = [ 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday' ];

	/** @var array<string, array{repeat: bool, start_time: string, end_time: string}> */
	private readonly array $schedule;

	/**
	 * @param array<string, array{repeat?: bool, start_time?: string, end_time?: string}> $schedule
	 */
	public function __construct( array $schedule = [] ) {
		$normalized = [];
		foreach ( self::DAYS as $day ) {
			$dayData            = $schedule[ $day ] ?? [];
			$normalized[ $day ] = [
				'repeat'     => (bool) ( $dayData['repeat'] ?? false ),
				'start_time' => $this->normalizeTime( (string) ( $dayData['start_time'] ?? '' ) ),
				'end_time'   => $this->normalizeTime( (string) ( $dayData['end_time'] ?? '' ) ),
			];
		}
		$this->schedule = $normalized;
	}

	public static function fromArray( array $schedule ): self {
		return new self( $schedule );
	}

	public static function empty(): self {
		return new self( [] );
	}

	public static function default(): self {
		$hours = [ 'repeat' => true, 'start_time' => '09:00', 'end_time' => '17:00' ];
		$off   = [ 'repeat' => false, 'start_time' => '', 'end_time' => '' ];
		return new self( [
			'sunday'    => $off,
			'monday'    => $hours,
			'tuesday'   => $hours,
			'wednesday' => $hours,
			'thursday'  => $hours,
			'friday'    => $hours,
			'saturday'  => $off,
		] );
	}

	/**
	 * Get config for a day by index (0=Sunday .. 6=Saturday).
	 *
	 * @return array{repeat: bool, start_time: string, end_time: string}
	 */
	public function getDay( int $dayIndex ): array {
		$day = self::DAYS[ $dayIndex ] ?? 'sunday';
		return $this->schedule[ $day ];
	}

	/**
	 * Check if a day is available (repeat=true and valid start/end times).
	 */
	public function isDayAvailable( int $dayIndex ): bool {
		$day = $this->getDay( $dayIndex );
		return $day['repeat']
			&& $day['start_time'] !== ''
			&& $day['end_time'] !== ''
			&& $day['start_time'] < $day['end_time'];
	}

	/**
	 * Check if requested time slot fits within the day's window.
	 */
	public function slotFitsDay( int $dayIndex, string $startTime, string $endTime ): bool {
		if ( ! $this->isDayAvailable( $dayIndex ) ) {
			return false;
		}
		$day = $this->getDay( $dayIndex );
		return $startTime >= $day['start_time'] && $endTime <= $day['end_time'];
	}

	/** @return array<string, array{repeat: bool, start_time: string, end_time: string}> */
	public function toArray(): array {
		return $this->schedule;
	}

	private function normalizeTime( string $time ): string {
		$time = trim( $time );
		if ( $time === '' ) {
			return '';
		}
		$lower = strtolower( $time );
		if ( str_contains( $lower, 'am' ) || str_contains( $lower, 'pm' ) ) {
			$ts = strtotime( $time );
			if ( $ts !== false ) {
				return gmdate( 'H:i', $ts );
			}
		}
		return $time;
	}
}
