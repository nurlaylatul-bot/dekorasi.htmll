<?php

declare(strict_types=1);

namespace AllCoach\Database\Migrations;

defined( 'ABSPATH' ) || exit;

use AllCoach\Infrastructure\Database\Migration;

/**
 * Move module enabled-state from the old enabled-list to the new states map.
 *
 * The legacy `_allcoach_enabled_modules` was a list of enabled ids, which made
 * an empty option mean "everything off". The new model uses:
 *   - `_allcoach_addon_states`  : id => bool (explicit on/off decisions)
 *   - `_allcoach_known_addons`  : ids whose default has already been applied
 *
 * This converts any previously-enabled ids to `true` and marks them known, so
 * existing choices survive the upgrade. Idempotent; the legacy option is left
 * in place for one release as a rollback safety net.
 */
class MigrateModuleStates extends Migration {

	private const LEGACY_OPTION = '_allcoach_enabled_modules';
	private const STATES_OPTION = '_allcoach_addon_states';
	private const KNOWN_OPTION  = '_allcoach_known_addons';

	public function up(): void {
		$legacy = get_option( self::LEGACY_OPTION, null );

		// Nothing to migrate on a fresh install.
		if ( ! is_array( $legacy ) ) {
			return;
		}

		$states = get_option( self::STATES_OPTION, [] );
		$states = is_array( $states ) ? $states : [];
		$known  = get_option( self::KNOWN_OPTION, [] );
		$known  = is_array( $known ) ? $known : [];

		foreach ( $legacy as $id ) {
			if ( ! is_string( $id ) ) {
				continue;
			}
			// Don't clobber a decision already recorded in the new model.
			if ( ! array_key_exists( $id, $states ) ) {
				$states[ $id ] = true;
			}
			if ( ! in_array( $id, $known, true ) ) {
				$known[] = $id;
			}
		}

		update_option( self::STATES_OPTION, $states );
		update_option( self::KNOWN_OPTION, array_values( $known ) );
	}

	public function down(): void {
		// Non-destructive migration; the legacy option was never removed.
		delete_option( self::STATES_OPTION );
		delete_option( self::KNOWN_OPTION );
	}
}
