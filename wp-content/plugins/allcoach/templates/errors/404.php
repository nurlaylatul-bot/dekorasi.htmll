<?php
/**
 * 404 error page template.
 *
 * @var string   $pageTitle The page title.
 * @var string   $message   Error message.
 * @var callable $routerUrl URL generator.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="ac-empty">
	<div class="ac-empty__icon-wrap">
	<svg width="151px" height="140px" viewBox="0 0 151 140">
		<g  stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
			<g transform="translate(-31.000000, -40.000000)">
				<g transform="translate(31.000000, 40.000000)">
					<rect fill="var(--ac-primary)" x="22" y="0" width="2" height="14" rx="1"></rect>
					<rect fill="var(--ac-primary)" transform="translate(7.000000, 23.000000) rotate(90.000000) translate(-7.000000, -23.000000) " x="6" y="16" width="2" height="14" rx="1"></rect>
					<rect fill="var(--ac-primary)" transform="translate(12.000000, 12.000000) rotate(-45.000000) translate(-12.000000, -12.000000) " x="11" y="5" width="2" height="14" rx="1"></rect>
					<ellipse fill="#000000" opacity="0.08" cx="79" cy="129.5" rx="72" ry="10.5"></ellipse>
					<rect fill="#FFFFFF" x="22" y="22" width="114" height="106" rx="9"></rect>
					<polygon fill="var(--ac-primary-mid)" points="23 47 79 47 135 47 135 32 135 29 132 25 128 23 29.9948981 23 26 25 24 27 23 30"></polygon>
					<circle stroke="var(--ac-primary)" stroke-width="2" fill="#FFFFFF" cx="33.5" cy="34.5" r="2.5"></circle>
					<circle stroke="var(--ac-primary)" stroke-width="2" fill="#FFFFFF" cx="43.5" cy="34.5" r="2.5"></circle>
					<circle stroke="var(--ac-primary)" stroke-width="2" fill="#FFFFFF" cx="53.5" cy="34.5" r="2.5"></circle>
					<rect fill="var(--ac-primary)" x="22" y="45" width="114" height="2"></rect>
					<rect fill="#000000" opacity="0.08" x="24" y="47" width="110" height="2"></rect>
					<rect stroke="var(--ac-primary)" stroke-width="2" x="23" y="23" width="112" height="104" rx="9"></rect>
					<g>
						<ellipse fill="var(--ac-primary)" cx="56.5" cy="72.5" rx="3.5" ry="3.5">
							<animate attributeName="ry" repeatCount="indefinite" fill="freeze" values="3.5;3.5;3.5;3.5;3.5;3.5;3.5;3.5;3.5;3.5;3.5;3.5;3.5;3.5;3.5;3.5;3.5;3.5;3.5;3.5;3.5;3.5;0.5;2" from="3.5" to="0.5" dur="3s" id="leftEyeAnim" d="leftEyeAnim"></animate>
						</ellipse>
						<ellipse fill="var(--ac-primary)" cx="101.5" cy="72.5" rx="3.5" ry="3.5">
							<animate attributeName="ry" repeatCount="indefinite" fill="remove" values="3.5;3.5;3.5;3.5;3.5;3.5;3.5;3.5;3.5;3.5;3.5;3.5;3.5;3.5;3.5;3.5;3.5;3.5;3.5;3.5;3.5;3.5;0.5;2" from="3.5" to="0.5" dur="3s"></animate>
						</ellipse>
						<animateMotion dur="6s" fill="freeze" calcMode="linear" repeatCount="indefinite" keyPoints="0; 0; 0; 0; 0; 0; 0.25; 0.25; 0.25; 0.75; 0.75; 0.75; 0.75; 1; 1" keyTimes="0; 0.1; 0.2; 0.3; 0.4; 0.5; 0.6; 0.65; 0.7; 0.75; 0.8; 0.825; 0.85; 0.9; 1" path="M 0 0 C 5 2, 5 2, 12 0 C 0 3, 0 3, -10 0 H 0"></animateMotion>
						<rect fill="var(--ac-primary)" x="65" y="94" width="28" height="2" rx="1"></rect>
					</g>
				</g>
			</g>
		</g>
	</svg>
	</div>

	<div class="ac-empty__title"><?php esc_html_e( "We couldn't find that page", 'allcoach' ); ?></div>
	<div class="ac-empty__sub"><?php esc_html_e( "The page you're looking for may have moved or no longer exists. Try navigating from your dashboard.", 'allcoach' ); ?></div>
	<div class="ac-empty__actions">
		<a href="<?php echo esc_url( $routerUrl() ); ?>" class="ac-btn ac-btn--primary">← Back to dashboard</a>
	</div>
</div>
