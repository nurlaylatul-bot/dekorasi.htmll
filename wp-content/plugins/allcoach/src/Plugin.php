<?php

declare(strict_types=1);

namespace AllCoach;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\AppServiceProvider;
use AllCoach\Core\Constants;
use AllCoach\Core\Container;
use AllCoach\Core\RoleManager;
use AllCoach\Frontend\AccountPages;
use AllCoach\Modules\ModuleLoader;
use AllCoach\Traits\Hooks;
use AllCoach\Traits\Singleton;

final class Plugin {

	use Singleton;
	use Hooks;

	private Container $container;
	private ModuleLoader $moduleLoader;
	private AppServiceProvider $appProvider;
	private bool $booted = false;

	private function __construct() {
		$this->container = new Container();
	}

	public function boot(): void {
		if ( $this->booted ) {
			return;
		}

		$this->loadConfiguration();

		$this->appProvider = $this->container->get( AppServiceProvider::class );
		$this->appProvider->boot();

		$this->moduleLoader = $this->container->get( ModuleLoader::class );
		$this->moduleLoader->loadModules();

		$this->booted = true;

		$this->maybeFlushRewriteRules();

		$this->doAction( 'allcoach:booted' );
	}

	public function activate(): void {
		if ( ! Constants::meetsPHPRequirement() ) {
			wp_die(
				sprintf(
					/* translators: 1: Minimum PHP version 2: Current PHP version */
					esc_html__( 'All Coach requires PHP %1$s or higher. You are running PHP %2$s.', 'allcoach' ),
					esc_html( Constants::MIN_PHP_VERSION ),
					esc_html( PHP_VERSION )
				),
				esc_html__( 'Activation Error', 'allcoach' ),
				[ 'back_link' => true ]
			);
		}

		if ( ! Constants::meetsWPRequirement() ) {
			global $wp_version;
			wp_die(
				sprintf(
					/* translators: 1: Minimum WP version 2: Current WP version */
					esc_html__( 'All Coach requires WordPress %1$s or higher. You are running WordPress %2$s.', 'allcoach' ),
					esc_html( Constants::MIN_WP_VERSION ),
					esc_html( $wp_version )
				),
				esc_html__( 'Activation Error', 'allcoach' ),
				[ 'back_link' => true ]
			);
		}

		$this->container->get( AccountPages::class )->createPages();
		flush_rewrite_rules();

		$this->doAction( 'allcoach:activated' );
	}

	public function deactivate(): void {
		$this->container->get( RoleManager::class )->removeRoles();
		flush_rewrite_rules();
		$this->doAction( 'allcoach:deactivated' );
	}


	public function getContainer(): Container {
		return $this->container;
	}

	private function maybeFlushRewriteRules(): void {
		$key = 'allcoach_rewrite_version';
		// Bump this value whenever endpoints change.
		$expected = $this->applyFilters(
			'allcoach:rewrite:expected_version',
			'2'
		);
		if ( get_option( $key ) !== $expected ) {
			$this->addAction(
				'init',
				static function () use ( $key, $expected ): void {
					flush_rewrite_rules();
					update_option( $key, $expected );
				},
				99
			);
		}
	}

	private function loadConfiguration(): void {
		$configPath = Constants::path( 'config/app.php' );
		if ( file_exists( $configPath ) ) {
			$appConfig = require $configPath;
			$this->container->set( 'config.app', $appConfig );
		}
	}
}
