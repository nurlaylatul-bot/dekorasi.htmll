<?php
/**
 * Sidebar navigation partial template.
 *
 * @var \WP_User $currentUser The current user.
 * @var bool     $isCoach     Whether user is a coach.
 * @var bool     $isClient    Whether user is a client.
 * @var callable $routerUrl   URL generator.
 */

use function AllCoach\Support\getAllowedSvgTags;

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- template variables

$currentEndpoint = \AllCoach\Frontend\Router::getCurrentEndpoint();

$navItems = [
	[
		'label'    => __( 'Dashboard', 'allcoach' ),
		'url'      => $routerUrl(),
		'endpoint' => '',
		'icon'     => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/></svg>',
	],
	[
		'label'    => __( 'My Appointments', 'allcoach' ),
		'url'      => $routerUrl( 'appointments' ),
		'endpoint' => 'appointments',
		'icon'     => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/></svg>',
	],
	[
		'label'    => __( 'Programs', 'allcoach' ),
		'url'      => $routerUrl( 'programs' ),
		'endpoint' => 'programs',
		'also'     => [ 'activities' ],
		'icon'     => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="14" x="2" y="3" rx="2"/><line x1="8" x2="16" y1="21" y2="21"/><line x1="12" x2="12" y1="17" y2="21"/></svg>',
	],
	[
		'label'    => __( 'Profile', 'allcoach' ),
		'url'      => $routerUrl( 'profile' ),
		'endpoint' => 'profile',
		'icon'     => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
	],
];

/**
 * Filters the client account navigation items. Addons (e.g. Pro) append their
 * own tabs here; each item is [ 'label', 'url', 'endpoint', 'icon' (SVG),
 * optional 'also' => string[] of endpoints that should keep it active ].
 *
 * @param array    $navItems  The nav items.
 * @param callable $routerUrl URL generator: $routerUrl( 'endpoint' ).
 */
$navItems = apply_filters( 'allcoach:account:nav_items', $navItems, $routerUrl );

$firstName = $currentUser->first_name;
$lastName  = $currentUser->last_name;
$fullName  = trim( "$firstName $lastName" ) ?: $currentUser->display_name;
$initials  = mb_strtoupper( mb_substr( $firstName ?: $currentUser->display_name, 0, 1 ) . mb_substr( $lastName, 0, 1 ) );
$avatarUrl = get_avatar_url( $currentUser->ID, [ 'size' => 104 ] );

$logoutUrl = wp_logout_url( home_url() );

?>

<aside class="ac-sidebar">
	<div class="ac-sidebar__profile">
		<div class="ac-sidebar__avatar">
			<?php if ( $avatarUrl ) : ?>
				<img src="<?php echo esc_url( $avatarUrl ); ?>" alt="<?php echo esc_attr( $fullName ); ?>">
			<?php else : ?>
				<?php echo esc_html( $initials ); ?>
			<?php endif; ?>
		</div>
		<div class="ac-sidebar__name"><?php echo esc_html( $fullName ); ?></div>
	</div>

	<nav class="ac-sidebar__nav" aria-label="<?php esc_attr_e( 'Account navigation', 'allcoach' ); ?>">
		<ul class="ac-sidebar__list">
			<?php foreach ( $navItems as $item ) : ?>
				<?php
				$isActive = ( $currentEndpoint === $item['endpoint'] );
				if ( ! $isActive && ! empty( $item['also'] ) ) {
					$isActive = in_array( $currentEndpoint, $item['also'], true );
				}
				?>
				<li class="ac-sidebar__item">
					<a
						href="<?php echo esc_url( $item['url'] ); ?>"
						class="ac-sidebar__link <?php echo $isActive ? 'ac-sidebar__link--active' : ''; ?>"
					>
						<span class="ac-sidebar__icon">
							<?php echo( wp_kses( $item['icon'] ?? '', getAllowedSvgTags() ) ); ?>
						</span>
						<span class="ac-sidebar__label">
							<?php echo esc_html( $item['label'] ); ?>
						</span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	</nav>

	<div class="ac-sidebar__divider"></div>
	<a href="<?php echo esc_url( $logoutUrl ); ?>" class="ac-sidebar__link ac-sidebar__link--danger">
		<span class="ac-sidebar__icon">
			<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></svg>
		</span>
		<span class="ac-sidebar__label"><?php esc_html_e( 'Log out', 'allcoach' ); ?></span>
	</a>
</aside>

<nav class="ac-mobile-nav" aria-label="<?php esc_attr_e( 'Account navigation', 'allcoach' ); ?>">
	<ul class="ac-mobile-nav__list">
		<?php foreach ( $navItems as $item ) : ?>
			<?php
			$isActive = ( $currentEndpoint === $item['endpoint'] );
			if ( ! $isActive && ! empty( $item['also'] ) ) {
				$isActive = in_array( $currentEndpoint, $item['also'], true );
			}
			?>
			<li class="ac-mobile-nav__item">
				<a
					href="<?php echo esc_url( $item['url'] ); ?>"
					class="ac-mobile-nav__link <?php echo $isActive ? 'ac-mobile-nav__link--active' : ''; ?>"
				>
					<span class="ac-mobile-nav__icon">
						<?php echo( wp_kses( $item['icon'] ?? '', getAllowedSvgTags() ) ); ?>
					</span>
					<span class="ac-mobile-nav__label">
						<?php echo esc_html( $item['label'] ); ?>
					</span>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</nav>
