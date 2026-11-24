<?php
/**
 * Account layout — sidebar navigation + page content.
 *
 * The active theme provides the outer page chrome (header, footer, <head>).
 * This template only renders the account-specific wrapper.
 *
 * @var callable  $content     The page content callback.
 * @var string    $pageTitle   The page title.
 * @var \WP_User  $currentUser The current user.
 * @var bool      $isCoach     Whether user is a coach.
 * @var bool      $isClient    Whether user is a client.
 * @var bool      $isAdmin     Whether user is an admin.
 * @var callable  $routerUrl   URL generator.
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- template variables

$templateLoader = \AllCoach\Plugin::getInstance()->getContainer()->get( \AllCoach\Support\TemplateLoader::class );

?>
<div class="ac-root">
	<div class="ac-layout">
		<?php
		$templateLoader->loadTemplate(
			'partials/sidebar-nav.php',
			compact( 'currentUser', 'isCoach', 'isClient', 'isAdmin', 'routerUrl' )
		);
		?>
		<main class="ac-main">
			<?php $content(); ?>
		</main>
	</div>
	<div id="ac-toast" class="ac-toast" role="status" aria-live="polite">
		<span class="ac-toast__message"></span>
	</div>
</div>
