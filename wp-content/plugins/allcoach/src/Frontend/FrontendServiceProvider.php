<?php

declare(strict_types=1);

namespace AllCoach\Frontend;

defined( 'ABSPATH' ) || exit;

use AllCoach\Application\Order\OrderQueryService;
use AllCoach\Core\Container;
use AllCoach\Core\ServiceProvider;
use AllCoach\Core\Settings\SettingsManager;
use AllCoach\Domain\Order\OrderFilter;
use AllCoach\Domain\Program\ProgramRepository;
use AllCoach\Domain\Session\SessionRepository;
use AllCoach\Frontend\Controllers\AuthController;
use AllCoach\Frontend\Controllers\BookingController;
use AllCoach\Frontend\Controllers\DashboardController;
use AllCoach\Frontend\Controllers\ProfileController;
use AllCoach\Frontend\Controllers\ProgramController;
use AllCoach\Infrastructure\Cart\CartManager;
use AllCoach\Infrastructure\Privacy\ConsentPolicy;
use AllCoach\Modules\Demo\DemoClientManager;
use AllCoach\Support\AssetManager;
use AllCoach\Support\TemplateLoader;
use AllCoach\Traits\Hooks;
use AllCoach\Frontend\Router\Router as CoreRouter;
use AllCoach\Frontend\Router\RouteGroup;
use DI\ContainerBuilder;

class FrontendServiceProvider implements ServiceProvider {

	use Hooks;

	private const ENDPOINT_MAP = [
		''             => [ DashboardController::class, 'index' ],
		'programs'     => [ ProgramController::class, 'index' ],
		'appointments' => [ BookingController::class, 'index' ],
		'profile'      => [ ProfileController::class, 'index' ],
	];

	public function __construct(
		private readonly Container $container,
		private readonly AssetManager $assetManager,
		private readonly TemplateLoader $templateLoader,
		private readonly CartManager $cartManager,
		private readonly SettingsManager $settingsManager,
		private readonly ProgramRepository $programRepository,
		private readonly SessionRepository $sessionRepository,
		private readonly DemoClientManager $demoClientManager,
		private readonly ConsentPolicy $consentPolicy,
		private readonly OrderQueryService $orderQueryService
	) {}

	public function register( ContainerBuilder $builder ): void {}

	public function boot(): void {
		$this->registerRoutes();
		CoreRouter::instance()->boot( dispatch: false );

		$this->addAction( 'init', [ $this, 'registerShortcodes' ] );
		$this->addAction( 'template_redirect', [ $this, 'handleAddToCart' ] );
		$this->addAction( 'allcoach:enqueue-assets:frontend', [ $this, 'enqueueAssets' ] );
		$this->addAction( 'wp_head', [ $this, 'noIndex' ] );
		$this->addAction( 'wp_head', [ $this, 'printDynamicColorStyles' ], 100 );
		$this->addAction(
			'wp_footer',
			fn() => $this->templateLoader->loadTemplate(
				'demo/demo.php',
				[
					'demoClientManager' => $this->demoClientManager,
				]
			),
			9999
		);
		$this->addFilter(
			'language_attributes',
			fn ( $output ) => $output . ' class="AllCoach"'
		);
	}

	private function registerRoutes(): void {
		CoreRouter::instance()->group(
			'account',
			function ( RouteGroup $group ): void {
				$group->index( fn() => null )
					->name( 'account' )
					->title( fn() => __( 'My Account', 'allcoach' ) );

				$group->route( 'programs', fn() => null )
					->name( 'account.programs' )
					->params( 'resource_id', 'page', 'category', 'search', 'status' )
					->title( fn() => __( 'My Programs', 'allcoach' ) );

				$group->route( 'appointments', fn() => null )
					->name( 'account.appointments' )
					->params( 'page', 'book', 'date', 'provider', 'status' )
					->title( fn() => __( 'My Appointments', 'allcoach' ) );

				$group->route( 'profile', fn() => null )
					->name( 'account.profile' )
					->params( 'tab', 'saved' )
					->title( fn() => __( 'My Profile', 'allcoach' ) );
			}
		);
	}

	public function handleAddToCart(): void {
		if ( ! isset( $_GET['allcoach-add-to-cart'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$programId = absint( $_GET['allcoach-add-to-cart'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $programId <= 0 ) {
			return;
		}

		try {
			$this->cartManager->clear();
			$this->cartManager->addProgram( $programId );
		} catch ( \Throwable ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// Do noting - the checkout page will show the appropriate error.
		}

		wp_safe_redirect( self::getCheckoutUrl() );
		exit;
	}

	public static function addToCartUrl( int $programId ): string {
		return add_query_arg( 'allcoach-add-to-cart', $programId, home_url( '/' ) );
	}

	public static function getCheckoutUrl( ?int $programId = null ): string {
		$pageId = AccountPages::getCheckoutPageId();
		$url    = $pageId > 0 ? get_permalink( $pageId ) : home_url( '/' );

		if ( $programId ) {
			$url = add_query_arg( 'allcoach-add-to-cart', $programId, $url ?: home_url( '/' ) );
		}

		return $url ?: home_url( '/' );
	}

	public function registerShortcodes(): void {
		add_shortcode( 'allcoach_checkout', [ $this, 'renderCheckoutShortcode' ] );
		add_shortcode( 'allcoach_account', [ $this, 'renderAccountShortcode' ] );
	}

	public function renderCheckoutShortcode(): string {
		$isLoggedIn = is_user_logged_in();
		$user       = $isLoggedIn ? wp_get_current_user() : null;
		$cart       = $this->cartManager->getCart();
		$cartItems  = $cart ? $cart->all() : [];
		$firstItem  = $cartItems[0] ?? null;
		$program    = $firstItem ? $this->programRepository->findById( $firstItem->getProgramId() ) : null;

		$sessionCount   = 0;
		$sessionMinutes = 0;
		if ( $program ) {
			$session = $this->sessionRepository->findOneByProgramId( $program->getId() );
			if ( $session ) {
				$sessionCount   = $session->getRepetitionCount();
				$sessionMinutes = $session->getDurationMinutes();
			}
		}

		$pendingOrder = null;
		if ( $isLoggedIn && $program ) {
			$pendingOrders = $this->orderQueryService->findWithFilter(
				new OrderFilter( clientId: get_current_user_id(), programId: $program->getId(), status: 'pending' )
			);
			foreach ( $pendingOrders as $order ) {
				if ( $order->paymentMethod === 'paypal' ) {
					$pendingOrder = $order;
					break;
				}
			}
		}

		$currency       = (string) $this->settingsManager->get( 'currency' );
		$enabledMethods = $this->settingsManager->getEnabledPaymentMethods();
		$paymentMethods = array_map(
			fn( string $id ) => [
				'id'          => $id,
				'title'       => $this->settingsManager->getPaymentMethodTitle( $id ),
				'description' => $this->settingsManager->getPaymentMethodDescription( $id ),
			],
			$enabledMethods
		);

		return $this->templateLoader->getTemplateContent(
			'checkout/index.php',
			[
				'isLoggedIn'       => $isLoggedIn,
				'user'             => $user,
				'cartItem'         => $firstItem,
				'program'          => $program,
				'pendingOrder'     => $pendingOrder,
				'sessionCount'     => $sessionCount,
				'sessionMinutes'   => $sessionMinutes,
				'currency'         => $currency,
				'paymentMethods'   => $paymentMethods,
				'dashboardUrl'     => Router::url(),
				'profileUrl'       => Router::url( 'profile' ),
				'programsUrl'      => Router::url( 'programs' ),
				'nonce'            => wp_create_nonce( 'wp_rest' ),
				'checkoutEndpoint' => rest_url( 'allcoach/v1/orders/checkout' ),
				'guestEndpoint'    => rest_url( 'allcoach/v1/orders/guest-checkout' ),
				'consentRequired'  => $this->consentPolicy->isRequired(),
				'consentLabel'     => $this->consentPolicy->label(),
			]
		);
	}

	public function enqueueCheckoutAssets(): void {
		if ( ! AccountPages::isCheckoutPage() ) {
			return;
		}

		$checkoutData = $this->applyFilters(
			'allcoach:checkout:script_data',
			[
				'nonce'               => wp_create_nonce( 'wp_rest' ),
				'currentUserId'       => get_current_user_id(),
				'userEmail'           => is_user_logged_in() ? wp_get_current_user()->user_email : '',
				'checkoutEndpoint'    => rest_url( 'allcoach/v1/orders/checkout' ),
				'guestEndpoint'       => rest_url( 'allcoach/v1/orders/guest-checkout' ),
				'paypalClientId'      => (string) $this->settingsManager->get( 'payment_paypal_client_id' ),
				'paypalPaymentAction' => (string) $this->settingsManager->get( 'payment_paypal_payment_action' ),
				'paypalSandbox'       => (bool) $this->settingsManager->get( 'payment_paypal_sandbox' ),
				'currency'            => (string) $this->settingsManager->get( 'currency' ),
				'consent'             => $this->consentPolicy->toScriptData(),
				'i18n'                => [
					'firstNameRequired'  => __( 'First name is required.', 'allcoach' ),
					'lastNameRequired'   => __( 'Last name is required.', 'allcoach' ),
					'emailRequired'      => __( 'Email is required.', 'allcoach' ),
					'emailInvalid'       => __( 'Invalid email.', 'allcoach' ),
					'consentRequired'    => __( 'Please accept the privacy policy to continue.', 'allcoach' ),
					'genericError'       => __( 'Something went wrong. Please try again.', 'allcoach' ),
					'paypalUnavailable'  => __( 'PayPal is not configured. Please choose another payment method or contact the site administrator.', 'allcoach' ),
					'processing'         => __( 'Processing...', 'allcoach' ),
					'placeOrder'         => __( 'Place Order', 'allcoach' ),
					'orderReceived'      => __( 'Thanks! Your order is received.', 'allcoach' ),
					/* translators: 1: client first name, 2: program title */
					'tyMessagePending'   => __(
						'Thanks, %1$s! Your enrollment for %2$s is pending payment confirmation. Check your email for payment instructions.',
						'allcoach'
					),
					/* translators: 1: client first name, 2: program title */
					'tyMessageConfirmed' => __(
						'Thanks, %1$s! Your enrollment for %2$s is confirmed. You can now access your program from your dashboard.',
						'allcoach'
					),
					/* translators: %s: client email address */
					'tyNotePending'      => __(
						'Confirmation sent to %s. Your coach will verify payment and activate your program within 24 hours.',
						'allcoach'
					),
					/* translators: %s: client email address */
					'tyNoteConfirmed'    => __(
						'A confirmation email has been sent to %s.',
						'allcoach'
					),
					'orderId'            => __( 'Order ID', 'allcoach' ),
					'program'            => __( 'Program', 'allcoach' ),
					'payment'            => __( 'Payment', 'allcoach' ),
					'status'             => __( 'Status', 'allcoach' ),
					'total'              => __( 'Total', 'allcoach' ),
				],
			]
		);

		$this->assetManager->enqueueScript(
			handle: 'checkout',
			src: 'checkout.js',
			localize: [
				'object_name' => '__ALLCOACH_CHECKOUT__',
				'data'        => $checkoutData,
			]
		);
		$this->assetManager->enqueueStyle(
			handle: 'checkout',
			src: 'checkout.css'
		);

		$this->doAction( 'allcoach:checkout:enqueue_assets' );
	}

	public function renderAccountShortcode(): string {
		ob_start();

		if ( ! is_user_logged_in() ) {
			$this->doAction( 'allcoach:account:before_login' );

			$controller = $this->container->get( AuthController::class );
			// Namespaced param so it can't collide with wp-login.php / other login plugins.
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- routing only, no state change.
			$action = isset( $_GET['allcoach_action'] ) ? sanitize_key( wp_unslash( $_GET['allcoach_action'] ) ) : '';

			match ( $action ) {
				'lostpassword' => $controller->lostPassword(),
				'resetpass'    => $controller->resetPassword(),
				default        => $controller->login(),
			};

			return (string) ob_get_clean();
		}

		$endpoint = Router::getCurrentEndpoint();
		$value    = Router::getCurrentEndpointValue();

		$endpointMap = $this->applyFilters( 'allcoach:account:endpoint_map', self::ENDPOINT_MAP );

		if ( $endpoint === 'programs' && $value !== '' ) {
			$this->dispatch( ProgramController::class, 'show' );
		} elseif ( isset( $endpointMap[ $endpoint ] ) ) {
			[ $controllerClass, $method ] = $endpointMap[ $endpoint ];
			$this->dispatch( $controllerClass, $method );
		} else {
			$this->dispatch( DashboardController::class, 'index' );
		}

		return (string) ob_get_clean();
	}

	private function dispatch( string $controllerClass, string $method ): void {
		$controller = $this->container->get( $controllerClass );

		$templateData = $this->applyFilters(
			'allcoach:account:layout_data',
			[
				'content'     => fn() => $controller->{$method}(),
				'currentUser' => wp_get_current_user(),
				'isCoach'     => in_array( \AllCoach\Core\RoleManager::ROLE_COACH, wp_get_current_user()->roles, true ),
				'isClient'    => in_array( \AllCoach\Core\RoleManager::ROLE_CLIENT, wp_get_current_user()->roles, true ),
				'isAdmin'     => current_user_can( 'manage_options' ),
				'routerUrl'   => fn( string $endpoint = '', string|int $value = '' ) => Router::url( $endpoint, $value ),
			],
			$controllerClass,
			$method
		);
		$template = $this->applyFilters( 'allcoach:account:layout_template', 'layouts/account.php' );

		$this->templateLoader->loadTemplate( $template, $templateData );
	}

	public function enqueueAssets(): void {
		$this->enqueueAccountAssets();
		$this->enqueueCheckoutAssets();
		$this->enqueueDemoClientAssets();
	}

	private function enqueueDemoClientAssets(): void {
		if (
			! $this->demoClientManager::isDemoSession() ||
			AccountPages::isAccountPage() ||
			AccountPages::isCheckoutPage()
		) {
			return;
		}

		$this->assetManager->enqueueStyle(
			handle: 'demo',
			src: 'demo.css',
		);
	}

	private function enqueueAccountAssets(): void {
		if ( ! AccountPages::isAccountPage() ) {
			return;
		}

		$this->assetManager->enqueueStyle(
			handle: 'account',
			src: 'account.css',
		);
		$accountData = $this->applyFilters(
			'allcoach:account:script_data',
			[
				'accountUrl'             => Router::url(),
				'nonce'                  => wp_create_nonce( 'wp_rest' ),
				'bookingSlotsEndpoint'   => rest_url( 'allcoach/v1/client/booking-slots' ),
				'dateSlotsEndpoint'      => rest_url( 'allcoach/v1/client/date-slots' ),
				'bookSessionEndpoint'    => rest_url( 'allcoach/v1/client/book-session' ),
				'changePasswordEndpoint' => rest_url( 'allcoach/v1/client/change-password' ),
			]
		);

		$this->assetManager->enqueueScript(
			handle: 'account',
			src: 'account.js',
			localize: [
				'object_name' => '__ALLCOACH__',
				'data'        => $accountData,
			]
		);

		$this->doAction( 'allcoach:account:enqueue_assets' );
	}

	public function noIndex(): void {
		if ( ! AccountPages::isAccountPage() ) {
			return;
		}

		$endpoint = Router::getCurrentEndpoint();
		if ( $endpoint !== '' ) {
			echo '<meta name="robots" content="noindex, nofollow" />' . "\n";
		}
	}

	/**
	 * Overrides the --ac-primary/--ac-button custom properties, but only when
	 * an admin has actually changed them away from the defaults. Printed at a
	 * late wp_head priority so it wins the cascade regardless of which
	 * AllCoach stylesheet is loaded on the current page. The default palette
	 * in tokens/colors.pcss is hand-tuned in OKLCH space (light/mid shades
	 * aren't a uniform RGB blend of the base color), so leaving it untouched
	 * at defaults avoids a subtly-off approximation for every site that
	 * hasn't customized its colors.
	 */
	public function printDynamicColorStyles(): void {
		$defaults    = $this->settingsManager->getDefaults();
		$primary     = (string) $this->settingsManager->get( 'primary_color' );
		$button      = (string) $this->settingsManager->get( 'button_color' );
		$buttonHover = (string) $this->settingsManager->get( 'button_hover_color' );

		$declarations = [];

		if ( $primary !== '' && $primary !== $defaults['primary_color'] ) {
			$declarations['--ac-primary']       = $primary;
			$declarations['--ac-primary-hover'] = SettingsManager::colorLuminance( $primary, -0.15 );
			$declarations['--ac-primary-mid']   = SettingsManager::colorLuminance( $primary, 0.55 );
			$declarations['--ac-primary-light'] = SettingsManager::colorLuminance( $primary, 0.85 );
		}

		if (
			( $button !== '' && $button !== $defaults['button_color'] ) ||
			( $buttonHover !== '' && $buttonHover !== $defaults['button_hover_color'] )
		) {
			$declarations['--ac-button']       = $button;
			$declarations['--ac-button-hover'] = $buttonHover;
		}

		if ( $declarations === [] ) {
			return;
		}

		$css = '';
		foreach ( $declarations as $property => $value ) {
			$css .= sprintf( '%s:%s;', $property, esc_attr( $value ) );
		}

		printf( '<style id="allcoach-dynamic-colors">:root{%s}</style>' . "\n", $css );
	}
}
