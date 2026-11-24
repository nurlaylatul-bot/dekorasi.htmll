<?php

declare(strict_types=1);

namespace AllCoach\Infrastructure\Database;

defined( 'ABSPATH' ) || exit;

use AllCoach\Traits\Hooks;

class TableCreator {

	use Hooks;

	public function dropTables(): void {
		/** Fires before all plugin tables are dropped. */
		$this->doAction( 'allcoach:tables:before_drop' );

		$migrator = new Migrator();
		$migrator->rollback();

		delete_option( '_allcoach_db_version' );

		/** Fires after all plugin tables have been dropped. */
		$this->doAction( 'allcoach:tables:after_drop' );
	}
}
