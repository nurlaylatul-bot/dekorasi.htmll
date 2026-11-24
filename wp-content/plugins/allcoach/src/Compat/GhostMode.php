<?php
/**
 * GhostMode class.
 *
 * @package AllCoach
 */

namespace AllCoach\Compat;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

class GhostMode {

	/**
	 * Whether the free plugin should stay dormant because Pro is active.
	 *
	 * @return bool
	 */
	public static function shouldStayDormant(): bool {
		$proPluginFile = 'allcoach-pro/allcoach.php';
		$activePlugins = (array) get_option( 'active_plugins', [] );

		if ( is_multisite() ) {
			$activePlugins = array_merge( $activePlugins, array_keys( (array) get_site_option( 'active_sitewide_plugins', [] ) ) );
		}

		return in_array( $proPluginFile, $activePlugins, true );
	}
}
