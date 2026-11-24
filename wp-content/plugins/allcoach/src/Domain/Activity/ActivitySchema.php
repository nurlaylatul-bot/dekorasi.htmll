<?php

declare(strict_types=1);

namespace AllCoach\Domain\Activity;

defined( 'ABSPATH' ) || exit;

final class ActivitySchema {

	public const VERSION = '2.0';

	/** Node types whose presence requires a submit button on the frontend. */
	private const INTERACTIVE_NODES = [
		'shortAnswer',
		'longAnswer',
		'multipleChoice',
		'checkbox',
		'date',
		'fileUpload',
	];

	/**
	 * @param array  $content
	 * @param string $version
	 */
	public function __construct(
		private readonly array $content = [],
		private readonly string $version = self::VERSION,
	) {}

	public function getContent(): array {
		return $this->content;
	}

	public function getVersion(): string {
		return $this->version;
	}

	public function isEmpty(): bool {
		return empty( $this->content );
	}

	public function hasInteractiveFields(): bool {
		return $this->nodeContainsTypes( $this->content, self::INTERACTIVE_NODES );
	}

	public static function empty(): self {
		return new self();
	}

	public static function fromJson( string $json ): self {
		$data = json_decode( $json, true );

		if ( ! is_array( $data ) ) {
			return self::empty();
		}

		return new self(
			$data['content'] ?? [],
			$data['version'] ?? self::VERSION,
		);
	}

	public function toJson(): string {
		return (string) wp_json_encode( $this->toArray() );
	}

	public function toArray(): array {
		return [
			'version' => $this->version,
			'content' => $this->content,
		];
	}

	private function nodeContainsTypes( array $node, array $types ): bool {
		if ( in_array( $node['type'] ?? '', $types, true ) ) {
			return true;
		}
		foreach ( $node['content'] ?? [] as $child ) {
			if ( $this->nodeContainsTypes( $child, $types ) ) {
				return true;
			}
		}
		return false;
	}
}
