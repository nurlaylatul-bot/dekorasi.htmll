<?php

declare(strict_types=1);

namespace AllCoach\Domain\Booking;

defined( 'ABSPATH' ) || exit;

class MeetingType {

	private const GOOGLE_MEET  = 'google_meet';
	private const CUSTOM_URL   = 'custom_url';
	private const FACE_TO_FACE = 'face_to_face';

	private const VALID_TYPES = [
		self::GOOGLE_MEET,
		self::CUSTOM_URL,
		self::FACE_TO_FACE,
	];

	private string $value;

	private function __construct( string $value ) {
		if ( ! in_array( $value, self::VALID_TYPES, true ) ) {
			throw new \InvalidArgumentException(
				sprintf( 'Invalid meeting type: %s', esc_html( $value ) )
			);
		}
		$this->value = $value;
	}

	public static function googleMeet(): self {
		return new self( self::GOOGLE_MEET );
	}

	public static function customUrl(): self {
		return new self( self::CUSTOM_URL );
	}

	public static function faceToFace(): self {
		return new self( self::FACE_TO_FACE );
	}

	public static function fromString( string $value ): self {
		return new self( $value );
	}

	public function getValue(): string {
		return $this->value;
	}

	public function isGoogleMeet(): bool {
		return $this->value === self::GOOGLE_MEET;
	}

	public function isCustomUrl(): bool {
		return $this->value === self::CUSTOM_URL;
	}

	public function isFaceToFace(): bool {
		return $this->value === self::FACE_TO_FACE;
	}

	public function equals( MeetingType $other ): bool {
		return $this->value === $other->value;
	}

	public static function all(): array {
		return self::VALID_TYPES;
	}

	public function __toString(): string {
		return $this->value;
	}
}
