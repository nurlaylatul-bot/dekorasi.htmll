<?php

declare(strict_types=1);

namespace AllCoach\Application\Availability;

defined( 'ABSPATH' ) || exit;

use AllCoach\Domain\Availability\WeeklySchedule;

class AvailabilityDTO {

	public function __construct(
		public readonly ?int $id,
		public readonly int $userId,
		public readonly string $timezone,
		public readonly string $startDate,
		public readonly WeeklySchedule $schedule,
		public readonly bool $isProgramSpecific = false,
		/** @var int[] */
		public readonly array $programIds = [],
	) {
	}

	/**
	 * Build from request params. Accepts flat keys or nested schedule.
	 *
	 * @param array<string, mixed> $data
	 */
	public static function fromArray( array $data ): self {
		$shift = is_array( $data['shift'] ?? null ) ? $data['shift'] : [];
		$sched = is_array( $data['schedule'] ?? null ) ? $data['schedule'] : [];
		$get   = static function ( string $key, mixed $default = null ) use ( $data, $shift ) {
			if ( array_key_exists( $key, $data ) ) {
				return $data[ $key ];
			}
			return $shift[ $key ] ?? $default;
		};

		$id         = isset( $data['id'] ) ? (int) $data['id'] : null;
		$userId     = (int) ( $get( 'user_id' ) ?? 0 );
		$timezone   = (string) ( $get( 'timezone' ) ?? $data['timezone'] ?? '' );
		$startDate  = (string) ( $get( 'start_date' ) ?? '' );
		$schedule   = $sched;
		$programIds = self::normalizeProgramIds( $get( 'program_ids' ), $get( 'package_ids' ) );

		if ( $timezone === '' ) {
			$timezone = wp_timezone_string();
		}
		if ( $startDate === '' ) {
			$startDate = current_time( 'Y-m-d' );
		}

		return new self(
			id:                  $id,
			userId:             $userId,
			timezone:            $timezone,
			startDate:          $startDate,
			schedule:            WeeklySchedule::fromArray( $schedule ),
			isProgramSpecific: self::toBool( $get( 'is_program_specific', $get( 'is_package_specific', false ) ) ),
			programIds:         $programIds,
		);
	}

	/**
	 * @param mixed $programIds
	 * @param mixed $packageIds
	 * @return int[]
	 */
	private static function normalizeProgramIds( $programIds, $packageIds ): array {
		$raw = $programIds ?? $packageIds;
		if ( ! is_array( $raw ) ) {
			return $raw !== null && $raw !== '' ? [ (int) $raw ] : [];
		}
		return array_values( array_map( 'intval', array_filter( $raw ) ) );
	}

	private static function toBool( mixed $v ): bool {
		if ( is_bool( $v ) ) {
			return $v;
		}
		if ( $v === 'true' || $v === '1' ) {
			return true;
		}
		if ( $v === 'false' || $v === '0' || $v === '' ) {
			return false;
		}
		return (bool) (int) $v;
	}

	public function toArray(): array {
		return [
			'id'                  => $this->id,
			'user_id'             => $this->userId,
			'timezone'            => $this->timezone,
			'start_date'          => $this->startDate,
			'schedule'            => $this->schedule->toArray(),
			'is_program_specific' => $this->isProgramSpecific,
			'program_ids'         => $this->programIds,
		];
	}
}
