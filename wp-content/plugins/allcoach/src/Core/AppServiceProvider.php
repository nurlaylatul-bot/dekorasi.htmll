<?php

declare(strict_types=1);

namespace AllCoach\Core;

defined( 'ABSPATH' ) || exit;

use AllCoach\Admin\AdminServiceProvider;
use AllCoach\API\ApiServiceProvider;
use AllCoach\Core\Contracts\ServiceProviderInterface;
use AllCoach\Core\Events\EventDispatcher;
use AllCoach\Core\Events\EventHookBridge;
use AllCoach\Core\Jobs\JobServiceProvider;
use AllCoach\Core\Settings\SettingsManager;
use AllCoach\Formbricks;
use AllCoach\Frontend\AccountPages;
use AllCoach\Frontend\FrontendServiceProvider;
use AllCoach\Infrastructure\Google\GoogleIntegrationProvider;
use AllCoach\Infrastructure\Mail\NotificationServiceProvider;
use AllCoach\SDK;
use AllCoach\Traits\Hooks;
use AllCoach\UsageTracking;

class AppServiceProvider implements ServiceProviderInterface {

	use Hooks;

	private Container $container;

	public function __construct( Container $container ) {
		$this->container = $container;
	}

	public function boot(): void {
		$this->registerEventBridge();
		$this->bootCore();
		$this->registerProviders();
		$this->registerHooks();
	}

	private function registerEventBridge(): void {
		$dispatcher = $this->container->get( EventDispatcher::class );
		$bridge     = $this->container->get( EventHookBridge::class );
		$dispatcher->subscribe( $bridge );
	}

	private function registerProviders(): void {
		if ( $this->isAdminContext() ) {
			$this->container
				->get( AdminServiceProvider::class )
				->boot();
		}

		if ( ! $this->isAdminContext() ) {
			$this->container
				->get( FrontendServiceProvider::class )
				->boot();
		}

		$this->container
			->get( ApiServiceProvider::class )
			->boot();
	}

	/**
	 * Determine if we're in an admin context.
	 *
	 * is_admin() returns true for AJAX and REST requests made through wp-admin,
	 * so we exclude those to ensure the frontend provider boots for API consumers.
	 */
	private function isAdminContext(): bool {
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}

		if ( wp_doing_ajax() ) {
			return false;
		}

		return is_admin();
	}

	private function registerHooks(): void {
		$this->addAction( 'admin_enqueue_scripts', [ $this, 'enqueueAdminAssets' ] );
		$this->addAction( 'wp_enqueue_scripts', [ $this, 'enqueueFrontendAssets' ] );
		$this->addAction( 'switch_theme', fn() => delete_option( 'allcoach_rewrite_version' ) );
		$this->container->get( AccountPages::class )->registerPostStates();
	}

	public function enqueueAdminAssets( string $hook ): void {
		$this->doAction( 'allcoach:enqueue-assets:admin', $hook );
	}

	public function enqueueFrontendAssets(): void {
		$this->doAction( 'allcoach:enqueue-assets:frontend' );
	}

	private function bootCore(): void {
		new SDK();
		new Formbricks( $this->container->get( SettingsManager::class ) );
		new UsageTracking( $this->container->get( SettingsManager::class ) );

		$this->container
			->get( CoreServiceProvider::class )
			->boot();

		$this->container
			->get( PrivacyServiceProvider::class )
			->boot();

		$this->container
			->get( JobServiceProvider::class )
			->boot();

		$this->container
			->get( NotificationServiceProvider::class )
			->boot();

		$this->container
			->get( GoogleIntegrationProvider::class )
			->boot();
	}
}
