<?php

declare(strict_types=1);

namespace AllCoach\Core\Exceptions;

defined( 'ABSPATH' ) || exit;

class NotFoundException extends AllCoachException {

	public static function entity( string $entityType, int $id ): self {
		return self::withContext(
			sprintf(
				/* translators: %s: Entity type */
				esc_html__( '%s not found', 'allcoach' ),
				esc_html( $entityType )
			),
			[
				'entity_type' => $entityType,
				'id'          => $id,
			]
		);
	}
}
