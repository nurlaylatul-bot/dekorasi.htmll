<?php

declare(strict_types=1);

namespace AllCoach\Core\I18n;

defined( 'ABSPATH' ) || exit;

use AllCoach\Domain\Activity\ActivitySchema;

class ActivitySchemaTranslator {

	public function registerStrings( int $activityId, ActivitySchema $schema ): void {
	}

	public function translate( int $activityId, ActivitySchema $schema ): ActivitySchema {
		return $schema;
	}
}
