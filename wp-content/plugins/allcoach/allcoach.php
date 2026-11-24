<?php
/**
 * Plugin Name: AllCoach - Coaching Management & Scheduling
 * Plugin URI: https://allcoach.io
 * Description: The all-in-one coaching management plugin. Manage programs, clients, appointments, and orders — all from your WordPress dashboard.
 * Author: ThemeGrill
 * Author URI: https://themegrill.com
 * Version: 1.1.1
 * Requires at least: 6.5
 * Requires PHP: 8.2
 * Text Domain: allcoach
 * Domain Path: /languages
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

declare(strict_types=1);

namespace WPEverest\AllCoach;

use AllCoach\Core\Constants;
use AllCoach\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$autoloader = __DIR__ . '/vendor/autoload.php'; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

if ( ! file_exists( $autoloader ) ) {
	add_action(
		'admin_notices',
		function () {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html( 'All Coach: Composer dependencies not found. Please run "composer install".' )
			);
		}
	);
	return;
}

// Ghost mode: if Pro is active, stay listed as active but do nothing.
$ghostMode = __DIR__ . '/src/Compat/GhostMode.php'; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
if ( file_exists( $ghostMode ) ) {
	require_once $ghostMode;
	if ( \AllCoach\Compat\GhostMode::shouldStayDormant() ) {
		return;
	}
}

require_once $autoloader;

$actionSchedulerBootstrap = __DIR__ . '/vendor/woocommerce/action-scheduler/action-scheduler.php'; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
if ( file_exists( $actionSchedulerBootstrap ) ) {
	require_once $actionSchedulerBootstrap;
}

Constants::init( __FILE__ );

// Register promotional placeholders for Pro-only addons before the plugin
// boots, so they're discovered during Plugin::boot()'s loadModules() pass.
$promoBootstrap = __DIR__ . '/src/Modules/Promo/bootstrap.php';
if ( file_exists( $promoBootstrap ) ) {
	require_once $promoBootstrap;
}

Plugin::getInstance()->boot();
register_activation_hook( __FILE__, fn() => Plugin::getInstance()->activate() );
register_deactivation_hook( __FILE__, fn() => Plugin::getInstance()->deactivate() );

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	\WP_CLI::add_command( 'allcoach', \AllCoach\CLI\AllCoachCommand::class );
}
