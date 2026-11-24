<?php

declare(strict_types=1);

namespace AllCoach\Core\Data;

defined( 'ABSPATH' ) || exit;

final class Timezone {

	public function __construct(
		public readonly string $value,
		public readonly string $abbr,
		public readonly float $offset,
		public readonly string $text,
		/** @var list<string> */
		public readonly array $utc,
	) {}

	public static function fromArray( array $data ): self {
		return new self(
			(string) ( $data['value'] ?? '' ),
			(string) ( $data['abbr'] ?? '' ),
			(float) ( $data['offset'] ?? 0 ),
			(string) ( $data['text'] ?? '' ),
			array_values( array_filter( (array) ( $data['utc'] ?? [] ), 'is_string' ) ),
		);
	}
}
