<?php
/**
 * Filter tabs partial.
 *
 * @var array  $tabs        Array of [ label, value, count ] arrays.
 * @var string $activeValue The currently active tab value.
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound, WordPress.WP.GlobalVariablesOverride.Prohibited -- template variables

?>
<div class="ac-filter-tabs" data-filter-tabs>
	<?php foreach ( $tabs as $tab ) : ?>
		<?php
		[ $label, $value, $count ] = array_pad( (array) $tab, 3, null );
		$isActive                  = ( $value === $activeValue );
		?>
		<?php
		$tabUrl = remove_query_arg( 'paged' );
		$tabUrl = $value === 'all' ? remove_query_arg( 'filter', $tabUrl ) : add_query_arg( 'filter', $value, $tabUrl );
		?>
		<a
			href="<?php echo esc_url( $tabUrl ); ?>"
			class="ac-filter-tab <?php echo $isActive ? 'is-active' : ''; ?>"
		>
			<?php echo esc_html( (string) $label ); ?>
			<?php if ( $count !== null ) : ?>
				<span class="ac-filter-tab__count"><?php echo esc_html( (string) $count ); ?></span>
			<?php endif; ?>
		</a>
	<?php endforeach; ?>
</div>
