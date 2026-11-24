<?php

declare(strict_types=1);

namespace AllCoach\Core\Data;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\Cache\CacheManager;
use AllCoach\Core\Constants;

class TimezoneData {

	private const CACHE_KEY = 'timezones';
	private const CACHE_TTL = 86400; // DAY_IN_SECONDS
	private const DATA_FILE = 'resources/data/timezones.json';

	public function __construct(
		private readonly CacheManager $cache,
	) {}

	/**
	 * @return list<Timezone>
	 */
	public function getAll(): array {
		return $this->cache->remember(
			self::CACHE_KEY,
			[ $this, 'loadFromFile' ],
			self::CACHE_TTL
		);
	}

	/**
	 * Flat list of IANA identifiers grouped by their display label.
	 *
	 * Returns an array of [ 'identifier' => string, 'label' => string ] entries
	 * suitable for building a <select>.
	 *
	 * @return list<array{identifier: string, label: string}>
	 */
	public function getOptions(): array {
		$options = [];

		foreach ( $this->getAll() as $tz ) {
			foreach ( $tz->utc as $identifier ) {
				$options[] = [
					'identifier' => $identifier,
					'label'      => $tz->text,
				];
			}
		}

		return $options;
	}

	/**
	 * Find the Timezone group that contains the given IANA identifier.
	 */
	public function findByIdentifier( string $identifier ): ?Timezone {
		foreach ( $this->getAll() as $tz ) {
			if ( in_array( $identifier, $tz->utc, true ) ) {
				return $tz;
			}
		}

		return null;
	}

	public function isValid( string $identifier ): bool {
		return $this->findByIdentifier( $identifier ) !== null;
	}

	/**
	 * @return list<Timezone>
	 */
	public function loadFromFile(): array {
		$path = Constants::path( self::DATA_FILE );

		try {
			if ( ! is_readable( $path ) ) {
				return [];
			}
			$json = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$data = json_decode( $json, true );

			if ( ! is_array( $data ) ) {
				return [];
			}

			$result = [];

			foreach ( $data as $item ) {
				if ( is_array( $item ) && ! empty( $item['value'] ) ) {
					$result[] = Timezone::fromArray( $item );
				}
			}

			return $result;
		} catch ( \Exception ) {
			return [];
		}
	}

	public function clearCache(): void {
		$this->cache->delete( self::CACHE_KEY );
	}
}
