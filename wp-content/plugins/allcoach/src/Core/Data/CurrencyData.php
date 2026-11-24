<?php

declare(strict_types=1);

namespace AllCoach\Core\Data;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\Cache\CacheManager;
use AllCoach\Core\Constants;

class CurrencyData {

	private const CACHE_KEY = 'currencies';
	private const CACHE_TTL = 86400; // DAY_IN_SECONDS
	private const DATA_FILE = 'resources/data/currencies.json';

	public function __construct(
		private readonly CacheManager $cache,
	) {}

	/**
	 * @return list<Currency>
	 */
	public function getAll(): array {
		return $this->cache->remember(
			self::CACHE_KEY,
			[ $this, 'loadFromFile' ],
			self::CACHE_TTL
		);
	}

	/**
	 * @return list<string>
	 */
	public function getCodes(): array {
		return array_map(
			static fn ( Currency $currency ) => $currency->code,
			$this->getAll()
		);
	}

	public function getByCode( string $code ): ?Currency {
		$code       = strtoupper( $code );
		$currencies = $this->getIndexed();

		return $currencies[ $code ] ?? null;
	}

	public function isValid( string $code ): bool {
		return $this->getByCode( $code ) !== null;
	}

	/**
	 * @return array<string, Currency>
	 */
	private function getIndexed(): array {
		$indexed = [];

		foreach ( $this->getAll() as $currency ) {
			$indexed[ $currency->code ] = $currency;
		}

		return $indexed;
	}

	/**
	 * @return list<Currency>
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
				if ( is_array( $item ) && ! empty( $item['code'] ) ) {
					$result[] = Currency::fromArray( $item );
				}
			}

			return $result;
		} catch ( \Exception $e ) {
			return [];
		}
	}

	public function clearCache(): void {
		$this->cache->delete( self::CACHE_KEY );
	}
}
