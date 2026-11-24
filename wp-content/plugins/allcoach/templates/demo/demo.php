<?php
/**
 * Demo mode banner template.
 *
 * @var \AllCoach\Modules\Demo\DemoClientManager $demoClientManager Demo client manager instance.
 */

defined( 'ABSPATH' ) || exit;

use AllCoach\Frontend\Router;

if ( ! $demoClientManager::isDemoSession() ) {
	return;
}

$demoUser = $demoClientManager->getOrCreateDemoUser();
$exitUrl  = add_query_arg( 'exit-demo', '1', Router::url() );
?>
<div class="ac-demo-bar">
	<span class="ac-demo-bar__dot"></span>
	<span class="ac-demo-bar__label">
		<?php esc_html_e( 'Viewing as Demo Client', 'allcoach' ); ?>
	</span>
	<span class="ac-demo-bar__sep"></span>
	<a href="<?php echo esc_url( $exitUrl ); ?>" class="ac-demo-bar__exit"><?php esc_html_e( 'Back to Admin', 'allcoach' ); ?> ×</a>
</div>
