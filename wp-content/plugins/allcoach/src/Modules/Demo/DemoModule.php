<?php

declare(strict_types=1);

namespace AllCoach\Modules\Demo;

defined( 'ABSPATH' ) || exit;

use AllCoach\Application\Order\OrderView;
use AllCoach\Frontend\Router;
use AllCoach\Modules\CoreModule;

class DemoModule extends CoreModule {

	protected string $id          = 'demo';
	protected string $name        = 'Demo Mode';
	protected string $description = 'Let coaches preview the client experience without real data being affected.';
	protected string $version     = '1.0.0';

	public function boot(): void {
		$this->addAction( 'template_redirect', [ $this, 'handleMagicLink' ], 5 );
		$this->addAction( 'template_redirect', [ $this, 'handleExit' ], 5 );
		$this->addFilter( 'allcoach:account:script_data', [ $this, 'injectScriptData' ] );
		$this->addFilter( 'allcoach:order:checkout_payload', [ $this, 'filterCheckoutPayload' ] );
		$this->addAction( 'allcoach:order:checkout_created', [ $this, 'onCheckoutOrderCreated' ] );
	}

	public function handleMagicLink(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$token = isset( $_GET['allcoach-demo'] ) ? sanitize_text_field( wp_unslash( $_GET['allcoach-demo'] ) ) : '';
		if ( $token === '' ) {
			return;
		}

		/** @var DemoSessionService $sessionService */
		$sessionService = $this->get( DemoSessionService::class );
		$originalUserId = $sessionService->consumeToken( $token );

		if ( ! $originalUserId ) {
			wp_die( esc_html__( 'Demo link has expired or is invalid. Please generate a new one from the admin panel.', 'allcoach' ) );
		}

		/** @var DemoClientManager $clientManager */
		$clientManager = $this->get( DemoClientManager::class );

		try {
			$demoUser = $clientManager->getOrCreateDemoUser();
		} catch ( \Throwable ) {
			wp_die( esc_html__( 'Failed to start demo session. Please try again.', 'allcoach' ) );
			return;
		}

		/** @var DemoSessionStore $sessionStore */
		$sessionStore = $this->get( DemoSessionStore::class );
		$sessionStore->create( $demoUser->ID, $originalUserId );

		wp_set_current_user( $demoUser->ID );
		wp_set_auth_cookie( $demoUser->ID, false );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$redirect = isset( $_GET['allcoach-demo-redirect'] ) ? rawurldecode( sanitize_text_field( wp_unslash( $_GET['allcoach-demo-redirect'] ) ) ) : '';

		$target = ( $redirect !== '' && str_starts_with( $redirect, Router::url() ) )
			? $redirect
			: Router::url();

		wp_safe_redirect( $target );
		exit;
	}

	public function handleExit(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['exit-demo'] ) || ! DemoClientManager::isDemoSession() ) {
			return;
		}

		/** @var DemoSessionStore $sessionStore */
		$sessionStore   = $this->get( DemoSessionStore::class );
		$demoUserId     = get_current_user_id();
		$originalUserId = $sessionStore->findOriginalUserId( $demoUserId );

		$sessionStore->delete( $demoUserId );
		wp_logout();

		if ( $originalUserId > 0 ) {
			wp_set_current_user( $originalUserId );
			wp_set_auth_cookie( $originalUserId, false );
		}

		wp_safe_redirect( admin_url() );
		exit;
	}

	/**
	 * @param array<string, mixed> $data
	 * @return array<string, mixed>
	 */
	public function injectScriptData( array $data ): array {
		$data['isDemoSession'] = DemoClientManager::isDemoSession();
		$data['exitDemoUrl']   = add_query_arg( 'exit-demo', '1', Router::url() );
		return $data;
	}

	/**
	 * @param array<string, mixed> $payload
	 * @return array<string, mixed>
	 */
	public function filterCheckoutPayload( array $payload ): array {
		if ( ! DemoClientManager::isDemoSession() ) {
			return $payload;
		}

		$payload['status']  = 'confirmed';
		$payload['is_demo'] = true;

		return $payload;
	}

	public function onCheckoutOrderCreated( OrderView $view ): void {
		if ( ! DemoClientManager::isDemoSession() ) {
			return;
		}

		/** @var DemoOrderService $orderService */
		$orderService = $this->get( DemoOrderService::class );
		$orderService->stampCreatedOrder( $view->id, $view->enrollmentId );
	}
}
