<?php

declare(strict_types=1);

namespace AllCoach\Infrastructure\Database;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\Constants;
use AllCoach\Traits\Hooks;

class Migrator {

	use Hooks;

	private const OPTION_KEY = '_allcoach_migrations';

	private string $migrationsPath;

	public function __construct() {
		$this->migrationsPath = Constants::path( 'database/migrations/' );
	}

	/**
	 * Run all pending migrations in order.
	 */
	public function run(): void {
		$ran     = $this->getRanMigrations();
		$files   = $this->discoverMigrationFiles();
		$pending = $this->filterPending( $files, $ran );

		if ( empty( $pending ) ) {
			return;
		}

		/** Fires before any pending migrations are executed. */
		$this->doAction( 'allcoach:migrations:before_run', $pending );

		foreach ( $pending as $file ) {
			$name = $this->migrationName( $file );

			/** Fires before a single migration runs. */
			$this->doAction( 'allcoach:migration:before', $name );

			$migration = $this->resolve( $file );
			$migration->up();

			$ran[] = $name;
			$this->saveRanMigrations( $ran );

			/** Fires after a single migration runs. */
			$this->doAction( 'allcoach:migration:after', $name );
		}

		/** Fires after all pending migrations have executed. */
		$this->doAction( 'allcoach:migrations:after_run', $pending );
	}

	/**
	 * Roll back all ran migrations in reverse order.
	 */
	public function rollback(): void {
		$ran   = $this->getRanMigrations();
		$files = array_reverse( $this->discoverMigrationFiles() );

		/** Fires before rolling back migrations. */
		$this->doAction( 'allcoach:migrations:before_rollback', $ran );

		foreach ( $files as $file ) {
			$name = $this->migrationName( $file );

			if ( ! in_array( $name, $ran, true ) ) {
				continue;
			}

			/** Fires before a single migration is rolled back. */
			$this->doAction( 'allcoach:migration:before_rollback', $name );

			$migration = $this->resolve( $file );
			$migration->down();

			$ran = array_values( array_diff( $ran, [ $name ] ) );
			$this->saveRanMigrations( $ran );

			/** Fires after a single migration is rolled back. */
			$this->doAction( 'allcoach:migration:after_rollback', $name );
		}

		delete_option( self::OPTION_KEY );

		/** Fires after all migrations have been rolled back. */
		$this->doAction( 'allcoach:migrations:after_rollback' );
	}

	/**
	 * Whether there are migrations that haven't been run yet.
	 */
	public function hasPending(): bool {
		$ran   = $this->getRanMigrations();
		$files = $this->discoverMigrationFiles();

		return ! empty( $this->filterPending( $files, $ran ) );
	}

	/**
	 * @return string[]
	 */
	public function getRanMigrations(): array {
		$value = get_option( self::OPTION_KEY, '[]' );

		return json_decode( (string) $value, true ) ?: [];
	}

	/**
	 * Return only files whose name is not in the $ran list.
	 *
	 * @param string[] $files
	 * @param string[] $ran
	 * @return string[]
	 */
	private function filterPending( array $files, array $ran ): array {
		return array_values(
			array_filter(
				$files,
				fn( string $file ) => ! in_array( $this->migrationName( $file ), $ran, true )
			)
		);
	}

	/**
	 * @return string[] Sorted file paths.
	 */
	private function discoverMigrationFiles(): array {
		/**
		 * Filters the path where migration files are discovered.
		 *
		 * @param string $migrationsPath Absolute path to the migrations directory.
		 */
		$path = $this->applyFilters( 'allcoach:migrations:path', $this->migrationsPath );

		$files = glob( $path . '*.php' ) ?: [];
		sort( $files );

		return $files;
	}

	private function migrationName( string $filePath ): string {
		return pathinfo( $filePath, PATHINFO_FILENAME );
	}

	private function resolve( string $filePath ): Migration {
		require_once $filePath;

		$filename  = pathinfo( $filePath, PATHINFO_FILENAME );
		$className = preg_replace( '/^\d+_/', '', $filename );

		/** @var class-string<Migration> */
		$fqcn = 'AllCoach\\Database\\Migrations\\' . $className;

		return new $fqcn();
	}

	/**
	 * @param string[] $migrations
	 */
	private function saveRanMigrations( array $migrations ): void {
		update_option( self::OPTION_KEY, wp_json_encode( array_values( $migrations ) ), false );
	}
}
