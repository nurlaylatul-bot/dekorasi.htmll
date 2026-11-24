<?php

declare(strict_types=1);

namespace AllCoach\CLI;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\Constants;
use AllCoach\Infrastructure\Database\Migrator;
use AllCoach\Infrastructure\Seeders\SampleDataSeeder;
use AllCoach\Plugin;

/**
 * Manage AllCoach database and sample data.
 *
 * ## EXAMPLES
 *
 *   # Run all pending migrations.
 *   $ wp allcoach migrate
 *
 *   # Show migration status.
 *   $ wp allcoach migrate-status
 *
 *   # Seed sample program data.
 *   $ wp allcoach seed
 *
 *   # Force re-seed even if already seeded.
 *   $ wp allcoach seed --force
 *
 *   # Reset seed flag so the next admin_init or `seed` can run again.
 *   $ wp allcoach seed-reset
 *
 *   # Scaffold a new migration file.
 *   $ wp allcoach make-migration AddLogsTable
 */
class AllCoachCommand {

	/**
	 * Run all pending database migrations.
	 *
	 * ## EXAMPLES
	 *
	 *   $ wp allcoach migrate
	 *   Success: 2 migration(s) ran.
	 *
	 * @when after_wp_load
	 */
	public function migrate(): void {
		$migrator = $this->migrator();
		$before   = count( $migrator->getRanMigrations() );

		$migrator->run();

		$after = count( $migrator->getRanMigrations() );
		$ran   = $after - $before;

		if ( $ran === 0 ) {
			/** @disregard P1009 Undefined type */
			\WP_CLI::success( 'No pending migrations. Database is up to date.' );
		} else {
			/** @disregard P1009 Undefined type */
			\WP_CLI::success( sprintf( '%d migration(s) ran.', $ran ) );
		}
	}

	/**
	 * Show migration status: which have run and which are pending.
	 *
	 * ## EXAMPLES
	 *
	 *   $ wp allcoach migrate-status
	 *
	 * @subcommand migrate-status
	 * @when after_wp_load
	 */
	public function migrationStatus(): void {
		$migrator = $this->migrator();
		$ran      = $migrator->getRanMigrations();
		$pending  = $migrator->hasPending();

		if ( empty( $ran ) ) {
			/** @disregard P1009 Undefined type */
			\WP_CLI::line( 'No migrations have been run yet.' );
		} else {
			/** @disregard P1009 Undefined type */
			\WP_CLI::line( 'Ran migrations:' );
			foreach ( $ran as $name ) {
				/** @disregard P1009 Undefined type */
				\WP_CLI::line( '  ✓ ' . $name );
			}
		}

		if ( $pending ) {
			/** @disregard P1009 Undefined type */
			\WP_CLI::warning( 'There are pending migrations. Run `wp allcoach migrate` to apply them.' );
		} else {
			/** @disregard P1009 Undefined type */
			\WP_CLI::line( 'Database is up to date.' );
		}
	}

	/**
	 * Seed the sample "8-Week Transformation Coaching" program.
	 *
	 * Creates a draft program with 3 sections, 6 interactive activities, and
	 * a session template (8 × 60-min sessions). Skipped if already seeded.
	 *
	 * ## OPTIONS
	 *
	 * [--force]
	 * : Delete the seeded flag and re-seed even if sample data was already created.
	 *
	 * ## EXAMPLES
	 *
	 *   $ wp allcoach seed
	 *   Success: Sample data seeded.
	 *
	 *   $ wp allcoach seed --force
	 *   Success: Sample data re-seeded.
	 *
	 * @when after_wp_load
	 *
	 * @param array $_args      Positional args (unused).
	 * @param array $assoc_args Associative args.
	 */
	public function seed( array $_args, array $assoc_args ): void {
		/** @disregard P1010 Undefined function */
		$force = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'force', false );

		if ( $force ) {
			delete_option( '_allcoach_sample_seeded' );
			/** @disregard P1009 Undefined type */
			\WP_CLI::line( 'Seed flag cleared. Re-seeding…' );
		}

		$seeder  = new SampleDataSeeder();
		$already = (bool) get_option( '_allcoach_sample_seeded' );

		if ( $already ) {
			/** @disregard P1009 Undefined type */
			\WP_CLI::warning( 'Sample data already seeded. Use --force to re-seed.' );
			return;
		}

		$seeder->maybeRun();
		/** @disregard P1009 Undefined type */
		\WP_CLI::success( $force ? 'Sample data re-seeded.' : 'Sample data seeded.' );
	}

	/**
	 * Reset the seed flag so sample data can be seeded again.
	 *
	 * Does not delete any existing posts — only clears the flag that prevents
	 * re-seeding. Run `wp allcoach seed` afterward to create new sample data.
	 *
	 * ## EXAMPLES
	 *
	 *   $ wp allcoach seed-reset
	 *   Success: Seed flag reset. Run `wp allcoach seed` to seed again.
	 *
	 * @subcommand seed-reset
	 * @when after_wp_load
	 */
	public function seedReset(): void {
		delete_option( '_allcoach_sample_seeded' );
		/** @disregard P1009 Undefined type */
		\WP_CLI::success( 'Seed flag reset. Run `wp allcoach seed` to seed again.' );
	}

	/**
	 * Scaffold a new migration file.
	 *
	 * ## OPTIONS
	 *
	 * <name>
	 * : Migration class name in PascalCase. e.g. AddLogsTable
	 *
	 * ## EXAMPLES
	 *
	 *   $ wp allcoach make-migration AddLogsTable
	 *   Success: Created: database/migrations/0004_AddLogsTable.php
	 *
	 * @subcommand make-migration
	 * @when after_wp_load
	 *
	 * @param array $args Positional args. $args[0] = migration name.
	 */
	public function makeMigration( array $args ): void {
		if ( empty( $args[0] ) ) {
			/** @disregard P1009 Undefined type */
			\WP_CLI::error( 'Migration name required. Example: wp allcoach make-migration AddLogsTable' );
		}

		$className     = $this->toPascalCase( $args[0] );
		$migrationsDir = Constants::path( 'database/migrations/' );
		$files         = glob( $migrationsDir . '*.php' ) ?: [];
		$maxNum        = 0;

		foreach ( $files as $file ) {
			if ( preg_match( '/^(\d+)_/', basename( $file, '.php' ), $m ) ) {
				$maxNum = max( $maxNum, (int) $m[1] );
			}
		}

		$prefix   = str_pad( (string) ( $maxNum + 1 ), 4, '0', STR_PAD_LEFT );
		$filename = $migrationsDir . $prefix . '_' . $className . '.php';

		if ( file_exists( $filename ) ) {
			/** @disregard P1009 Undefined type */
			\WP_CLI::error( "File already exists: {$filename}" );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $filename, $this->migrationStub( $className ) );

		/** @disregard P1009 Undefined type */
		\WP_CLI::success( "Created: database/migrations/{$prefix}_{$className}.php" );
	}

	private function migrationStub( string $className ): string {
		return <<<PHP
		<?php

		declare(strict_types=1);

		namespace AllCoach\\Database\\Migrations;

		defined( 'ABSPATH' ) || exit;

		use AllCoach\\Infrastructure\\Database\\Migration;

		class {$className} extends Migration {

			public function up(): void {
				// TODO: implement migration.
			}

			public function down(): void {
				// TODO: implement rollback.
			}
		}
		PHP;
	}

	private function toPascalCase( string $name ): string {
		$words = preg_split( '/[\s_\-]+/', $name ) ?: [ $name ];
		return implode( '', array_map( 'ucfirst', $words ) );
	}

	private function migrator(): Migrator {
		return Plugin::getInstance()->getContainer()->get( Migrator::class );
	}
}
