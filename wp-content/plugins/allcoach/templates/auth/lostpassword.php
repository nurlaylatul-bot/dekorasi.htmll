<?php
/**
 * Lost password page template.
 *
 * @var string $pageTitle The page title.
 * @var string $error     Error message, if any.
 * @var bool   $sent      Whether the reset email was just sent successfully.
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- template variables

$accountPageUrl = get_permalink();
$loginUrl       = $accountPageUrl ?: home_url( '/' );
?>
<div class="ac-root">
	<div class="ac-auth">
		<div class="ac-auth__card">
			<div class="ac-auth__header">
				<h1 class="ac-auth__title"><?php echo esc_html( $pageTitle ); ?></h1>
				<p class="ac-auth__subtitle"><?php esc_html_e( 'Enter your email or username and we will send you a link to reset your password.', 'allcoach' ); ?></p>
			</div>

			<?php if ( ! empty( $error ) ) : ?>
				<div class="ac-alert ac-alert--error">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
					<span><?php echo wp_kses_post( $error ); ?></span>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $sent ) ) : ?>
				<div class="ac-alert ac-alert--success">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="8 12 11 15 16 9"/></svg>
					<span><?php esc_html_e( 'A password reset link has been sent to your email.', 'allcoach' ); ?></span>
				</div>
			<?php else : ?>
				<form method="post">
					<?php wp_nonce_field( 'allcoach_lostpassword', '_allcoach_nonce' ); ?>

					<div class="ac-form-group">
						<label class="ac-label" for="ac-login"><?php esc_html_e( 'Email or username', 'allcoach' ); ?> <span class="ac-req">*</span></label>
						<input
							class="ac-input"
							type="text"
							id="ac-login"
							name="login"
							value="<?php echo esc_attr( sanitize_text_field( wp_unslash( $_POST['login'] ?? '' ) ) ); ?>"<?php // phpcs:ignore WordPress.Security.NonceVerification.Missing -- value repopulation only. ?>
							placeholder="<?php esc_attr_e( 'you@example.com', 'allcoach' ); ?>"
							autocomplete="username"
							required
						>
					</div>

					<button type="submit" class="ac-btn ac-btn--primary ac-btn--full ac-btn--lg">
						<?php esc_html_e( 'Send Reset Link', 'allcoach' ); ?>
					</button>
				</form>
			<?php endif; ?>

			<p class="ac-auth__footer">
				<a href="<?php echo esc_url( $loginUrl ); ?>"><?php esc_html_e( 'Back to login', 'allcoach' ); ?></a>
			</p>
		</div>
	</div>
</div>
