<?php
/**
 * Reset password page template.
 *
 * @var string $pageTitle The page title.
 * @var string $error     Error message, if any.
 * @var bool   $validKey  Whether the reset key/login pair is valid.
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- template variables

$accountPageUrl  = get_permalink();
$loginUrl        = $accountPageUrl ?: home_url( '/' );
$lostPasswordUrl = $accountPageUrl ? add_query_arg( 'allcoach_action', 'lostpassword', $accountPageUrl ) : '';
?>
<div class="ac-root">
	<div class="ac-auth">
		<div class="ac-auth__card">
			<div class="ac-auth__header">
				<h1 class="ac-auth__title"><?php echo esc_html( $pageTitle ); ?></h1>
				<?php if ( ! empty( $validKey ) ) : ?>
					<p class="ac-auth__subtitle"><?php esc_html_e( 'Choose a new password for your account.', 'allcoach' ); ?></p>
				<?php endif; ?>
			</div>

			<?php if ( ! empty( $error ) ) : ?>
				<div class="ac-alert ac-alert--error">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
					<span><?php echo wp_kses_post( $error ); ?></span>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $validKey ) ) : ?>
				<form method="post">
					<?php wp_nonce_field( 'allcoach_resetpass', '_allcoach_nonce' ); ?>

					<div class="ac-form-group">
						<label class="ac-label" for="ac-password"><?php esc_html_e( 'New password', 'allcoach' ); ?> <span class="ac-req">*</span></label>
						<input
							class="ac-input"
							type="password"
							id="ac-password"
							name="password"
							placeholder="&#x2022;&#x2022;&#x2022;&#x2022;&#x2022;&#x2022;&#x2022;&#x2022;"
							autocomplete="new-password"
							required
						>
					</div>

					<div class="ac-form-group">
						<label class="ac-label" for="ac-confirm-password"><?php esc_html_e( 'Confirm new password', 'allcoach' ); ?> <span class="ac-req">*</span></label>
						<input
							class="ac-input"
							type="password"
							id="ac-confirm-password"
							name="confirm_password"
							placeholder="&#x2022;&#x2022;&#x2022;&#x2022;&#x2022;&#x2022;&#x2022;&#x2022;"
							autocomplete="new-password"
							required
						>
					</div>

					<button type="submit" class="ac-btn ac-btn--primary ac-btn--full ac-btn--lg">
						<?php esc_html_e( 'Reset Password', 'allcoach' ); ?>
					</button>
				</form>
			<?php else : ?>
				<p class="ac-auth__footer">
					<a href="<?php echo esc_url( $lostPasswordUrl ); ?>"><?php esc_html_e( 'Request a new reset link', 'allcoach' ); ?></a>
				</p>
			<?php endif; ?>

			<p class="ac-auth__footer">
				<a href="<?php echo esc_url( $loginUrl ); ?>"><?php esc_html_e( 'Back to login', 'allcoach' ); ?></a>
			</p>
		</div>
	</div>
</div>
