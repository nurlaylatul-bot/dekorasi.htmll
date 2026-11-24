<?php

declare(strict_types=1);

namespace AllCoach\Admin;

defined( 'ABSPATH' ) || exit;

use AllCoach\Application\Client\ClientQueryService;
use AllCoach\Application\Order\OrderQueryService;
use AllCoach\Core\Constants;
use AllCoach\Core\Container;
use AllCoach\Core\ServiceProvider;
use AllCoach\Infrastructure\Google\GoogleOAuthClient;
use AllCoach\Infrastructure\Google\GoogleTokenManager;
use AllCoach\Modules\AddonInterface;
use AllCoach\Modules\ModuleRegistry;
use AllCoach\Support\AssetManager;
use AllCoach\Traits\Hooks;
use DI\ContainerBuilder;

use function AllCoach\Support\currencies;
use function AllCoach\Support\timezones;

class AdminServiceProvider implements ServiceProvider {

	use Hooks;

	public function __construct(
		private readonly Container $container,
		private readonly AssetManager $assetManager,
		private readonly GoogleOAuthClient $googleOAuthClient,
		private readonly GoogleTokenManager $googleTokenManager,
		private readonly ModuleRegistry $moduleRegistry,
		private readonly ClientQueryService $clientQueryService,
		private readonly OrderQueryService $orderQueryService,
	) {}

	public function register( ContainerBuilder $builder ): void {}

	public function boot(): void {
		$this->addAction( 'admin_menu', [ $this, 'registerMenus' ] );
		$this->addAction( 'allcoach:enqueue-assets:admin', [ $this, 'enqueueAssets' ] );
		$this->addAction( 'admin_footer', [ $this, 'inlineMenuHighlight' ] );
	}

	public function registerMenus(): void {
		$dashicon = 'data:image/svg+xml;base64,' . base64_encode( (string) file_get_contents( Constants::path( 'assets/images/dashicon-allcoach.svg' ) ) );

		$menuTitle = $this->applyFilters( 'allcoach:admin:menu_title', __( 'AllCoach', 'allcoach' ) );
		$menuIcon  = $this->applyFilters( 'allcoach:admin:menu_icon', $dashicon );

		add_menu_page(
			$menuTitle,
			$menuTitle,
			'manage_options',
			'allcoach',
			function () {
				echo '<div id="AllCoach-Admin-Root"></div>';
			},
			$menuIcon,
			30
		);

		add_submenu_page(
			parent_slug: 'allcoach',
			page_title: __( 'Analytics', 'allcoach' ),
			menu_title: __( 'Analytics', 'allcoach' ),
			capability: 'manage_options',
			menu_slug: 'allcoach#/analytics',
			callback: function () {
				echo '<div id="AllCoach-Admin-Root"></div>';
			},
		);

		add_submenu_page(
			parent_slug: 'allcoach',
			page_title: __( 'Programs', 'allcoach' ),
			menu_title: __( 'Programs', 'allcoach' ),
			capability: 'manage_options',
			menu_slug: 'allcoach#/programs',
			callback: function () {
				echo '<div id="AllCoach-Admin-Root"></div>';
			},
		);

		$newClientsCount = $this->clientQueryService->getNewCount();

		add_submenu_page(
			parent_slug: 'allcoach',
			page_title: __( 'Clients', 'allcoach' ),
			menu_title: $this->menuTitleWithCount(
				__( 'Clients', 'allcoach' ),
				$newClientsCount,
				sprintf(
					/* translators: %d: Number of new clients. */
					_n( '%d new client', '%d new clients', $newClientsCount, 'allcoach' ),
					$newClientsCount
				)
			),
			capability: 'manage_options',
			menu_slug: 'allcoach#/clients',
			callback: function () {
				echo '<div id="AllCoach-Admin-Root"></div>';
			},
		);

		add_submenu_page(
			parent_slug: 'allcoach',
			page_title: __( 'Appointments', 'allcoach' ),
			menu_title: __( 'Appointments', 'allcoach' ),
			capability: 'manage_options',
			menu_slug: 'allcoach#/appointments',
			callback: function () {
				echo '<div id="AllCoach-Admin-Root"></div>';
			},
		);

		$pendingOrdersCount = $this->orderQueryService->getPendingCount();

		add_submenu_page(
			parent_slug: 'allcoach',
			page_title: __( 'Orders', 'allcoach' ),
			menu_title: $this->menuTitleWithCount(
				__( 'Orders', 'allcoach' ),
				$pendingOrdersCount,
				sprintf(
					/* translators: %d: Number of pending orders. */
					_n( '%d pending order', '%d pending orders', $pendingOrdersCount, 'allcoach' ),
					$pendingOrdersCount
				)
			),
			capability: 'manage_options',
			menu_slug: 'allcoach#/orders',
			callback: function () {
				echo '<div id="AllCoach-Admin-Root"></div>';
			},
		);

		add_submenu_page(
			parent_slug: 'allcoach',
			page_title: __( 'Settings', 'allcoach' ),
			menu_title: __( 'Settings', 'allcoach' ),
			capability: 'manage_options',
			menu_slug: 'allcoach#/settings',
			callback: function () {
				echo '<div id="AllCoach-Admin-Root"></div>';
			},
		);

		// Submenus contributed by enabled addons (disabled addons contribute none).
		$root = function () {
			echo '<div id="AllCoach-Admin-Root"></div>';
		};
		foreach ( $this->collectAddonNav() as $nav ) {
			add_submenu_page(
				parent_slug: 'allcoach',
				page_title: $nav['label'],
				menu_title: $nav['label'],
				capability: $nav['capability'],
				menu_slug: 'allcoach#' . $nav['route'],
				callback: $root,
			);
		}

		// The Add-ons page itself is always available.
		add_submenu_page(
			parent_slug: 'allcoach',
			page_title: __( 'Add-ons', 'allcoach' ),
			menu_title: __( 'Add-ons', 'allcoach' ),
			capability: 'manage_options',
			menu_slug: 'allcoach#/addons',
			callback: $root,
		);

		remove_submenu_page( 'allcoach', 'allcoach' );
	}

	/**
	 * Appends a WP-core-style count bubble (as seen on Comments/Plugins) to a menu label.
	 * Returns the plain, escaped label when $count is zero.
	 */
	private function menuTitleWithCount( string $label, int $count, string $screenReaderText ): string {
		if ( $count <= 0 ) {
			return esc_html( $label );
		}

		return sprintf(
			'%1$s <span class="awaiting-mod count-%2$d"><span class="pending-count" aria-hidden="true">%2$d</span><span class="screen-reader-text">%3$s</span></span>',
			esc_html( $label ),
			$count,
			esc_html( $screenReaderText )
		);
	}

	/**
	 * Admin nav contributed by currently-enabled addons, sorted by order.
	 *
	 * @return array<int, array{slug: string, label: string, route: string, capability: string, order: int}>
	 */
	private function collectAddonNav(): array {
		$items = [];
		foreach ( $this->moduleRegistry->getEnabledModules() as $module ) {
			if ( ! $module instanceof AddonInterface ) {
				continue;
			}
			$nav = $module->getNav();
			if ( $nav === null ) {
				continue;
			}
			$items[] = [
				'slug'       => $nav->slug,
				'label'      => $nav->label,
				'route'      => $nav->route,
				'capability' => $nav->capability,
				'order'      => $nav->order,
			];
		}
		usort( $items, static fn( array $a, array $b ): int => $a['order'] <=> $b['order'] );
		return $items;
	}

	public function inlineMenuHighlight(): void {
		$screen = get_current_screen();
		if ( ! $screen || ! str_contains( $screen->id, 'allcoach' ) ) {
			return;
		}
		$script = <<<'JS'
(function () {
	function syncHighlight() {
		const rawHash = window.location.hash || '#/programs';
		// Strip search params from hash (e.g. #/bookings/?page=1 → #/bookings/)
		const hashPath = rawHash.split('?')[0];
		// Normalise trailing slash (e.g. #/bookings/ → #/bookings)
		const normalised = hashPath.replace(/\/$/, '');
		const submenu = document.querySelector(
			'#toplevel_page_allcoach .wp-submenu',
		);
		if (!submenu) return;
		submenu.querySelectorAll('li').forEach(function (li) {
			const a = li.querySelector('a');
			if (!a) return;
			const href = a.getAttribute('href') || '';
			// Normalise the link's hash the same way
			const linkHash = href.includes('#') ? '#' + href.split('#')[1] : '';
			const linkNormalised = linkHash.replace(/\/$/, '');

			if (linkNormalised && normalised.startsWith(linkNormalised)) {
				li.classList.add('current');
			} else {
				li.classList.remove('current');
			}
		});
	}
	syncHighlight();
	window.addEventListener('allcoach:navigate', syncHighlight);
})();
JS;
		wp_print_inline_script_tag(
			$script,
			[
				'type' => 'text/javascript',
				'id'   => 'AllCoach-Menu-Highlight',
			]
		);
	}

	public function enqueueAssets( string $hook ): void {
		if ( ! str_contains( $hook, 'allcoach' ) ) {
			return;
		}

		$adminData = $this->applyFilters(
			'allcoach:admin:script_data',
			[
				'adminUrl'          => admin_url( 'admin.php' ),
				'pluginUrl'         => Constants::url(),
				'currentUserId'     => get_current_user_id(),
				'isAdmin'           => current_user_can( 'manage_options' ),
				'nonce'             => wp_create_nonce( 'wp_rest' ),
				'version'           => Constants::VERSION,
				// Marketing URL for the "Upgrade to Pro" affordances in the Free
				// analytics showcase. The actual Free/Pro switch is the JS seam
				// (allcoach.analytics.page), which the Pro bundle fills when active.
				'upgradeUrl'        => apply_filters( 'allcoach:analytics:upgrade_url', 'https://allcoach.io/pricing' ),
				'currencies'        => currencies()->getAll(),
				'timezones'         => timezones()->getAll(),
				'googleCallbackUrl' => rest_url( 'allcoach/v1/google/callback' ),
				'paypalWebhookUrl'  => rest_url( 'allcoach/v1/payment/paypal/webhook' ),
				'homeUrl'           => home_url( '/' ),
				'googleIntegration' => [
					'isConfigured' => $this->googleOAuthClient->isConfigured(),
					'isConnected'  => $this->googleTokenManager->isConnected( get_current_user_id() ),
				],
				// Enabled addons' nav entries, so the SPA top-nav can render them
				// (and drop them when an addon is disabled). Sorted by order.
				'addonNav'          => $this->collectAddonNav(),
			]
		);

		wp_enqueue_media();

		$this->assetManager->enqueueScript(
			handle: 'admin',
			src: 'admin.js',
			localize:[
				'object_name' => '__ALLCOACH_ADMIN__',
				'data'        => $adminData,
			]
		);
		$this->assetManager->enqueueStyle(
			handle: 'admin',
			src: 'admin.css'
		);
		if ( is_rtl() ) {
			$this->assetManager->enqueueStyle(
				handle: 'admin-rtl',
				src: 'admin.rtl.css'
			);
		}

		$this->doAction( 'allcoach:admin:enqueue_assets', $hook );
	}
}
