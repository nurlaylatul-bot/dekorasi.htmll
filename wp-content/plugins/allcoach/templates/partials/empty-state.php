<?php
/**
 * Empty state partial.
 *
 * @var string      $icon   SVG inner path markup (rendered inside a 14×14 viewBox).
 * @var string      $title  Primary heading text.
 * @var string      $sub    Secondary description text.
 * @var array|null  $button Optional CTA. Keys: 'label' (string), and either 'url' (string) for a link or 'id' (string) for a JS-triggered button.
 * @var bool        $wrap   Whether to wrap in an ac-card. Default true.
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- template variables

$wrap   = $wrap ?? true;
$button = $button ?? null;

$inner = function () use ( $icon, $title, $sub, $button ): void {
	?>
	<div class="ac-empty-state">
		<div class="ac-empty-state__icon">
			<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?php echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></svg>
		</div>
		<div class="ac-empty-state__title"><?php echo esc_html( $title ); ?></div>
		<div class="ac-empty-state__sub"><?php echo esc_html( $sub ); ?></div>
		<?php if ( ! empty( $button ) ) : ?>
			<?php if ( ! empty( $button['id'] ) ) : ?>
				<button type="button" id="<?php echo esc_attr( $button['id'] ); ?>" class="ac-btn ac-btn--primary ac-btn--sm" style="margin-top:4px">
					<?php echo esc_html( $button['label'] ); ?>
				</button>
			<?php else : ?>
				<a href="<?php echo esc_url( $button['url'] ); ?>" class="ac-btn ac-btn--primary ac-btn--sm" style="margin-top:4px">
					<?php echo esc_html( $button['label'] ); ?>
				</a>
			<?php endif; ?>
		<?php endif; ?>
	</div>
	<?php
};

if ( $wrap ) :
	?>
	<div class="ac-card ac-card--padded" style="padding:0">
		<?php $inner(); ?>
	</div>
	<?php
else :
	$inner();
endif;
