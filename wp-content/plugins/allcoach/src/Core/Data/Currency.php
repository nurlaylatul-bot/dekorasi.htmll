<?php

declare(strict_types=1);

namespace AllCoach\Core\Data;

defined( 'ABSPATH' ) || exit;

final class Currency {

	public function __construct(
		public readonly string $code,
		public readonly string $name,
		public readonly string $symbol,
		public readonly string $symbolNative,
		public readonly string $namePlural,
		public readonly int $decimalDigits,
		public readonly int $rounding,
	) {}

	public static function fromArray( array $data ): self {
		return new self(
			strtoupper( (string) ( $data['code'] ?? '' ) ),
			(string) ( $data['name'] ?? '' ),
			(string) ( $data['symbol'] ?? '' ),
			(string) ( $data['symbol_native'] ?? '' ),
			(string) ( $data['name_plural'] ?? '' ),
			(int) ( $data['decimal_digits'] ?? 2 ),
			(int) ( $data['rounding'] ?? 0 ),
		);
	}
}
