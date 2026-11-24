<?php
/**
 * Login page template.
 *
 * @var string $pageTitle     The page title.
 * @var string $error         Error message, if any.
 * @var bool   $passwordReset Whether the user just completed a password reset.
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- template variables

$accountPageUrl = get_permalink();
$registerUrl    = $accountPageUrl ? add_query_arg( 'action', 'register', $accountPageUrl ) : '';
$lostPasswordUrl = $accountPageUrl ? add_query_arg( 'allcoach_action', 'lostpassword', $accountPageUrl ) : '';
?>
<div class="ac-root">
	<div class="ac-auth">
		<div class="ac-auth__card">
			<div class="ac-auth__header">
				<!-- <h1 class="ac-auth__title"><?php // echo esc_html( $pageTitle ); ?></h1>
				<p class="ac-auth__subtitle"><?php // esc_html_e( 'Sign in to your account to continue.', 'allcoach' ); ?></p> -->
			</div>

			<?php if ( ! empty( $passwordReset ) ) : ?>
				<div class="ac-alert ac-alert--success">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="8 12 11 15 16 9"/></svg>
					<span><?php esc_html_e( 'Your password has been reset. Please log in.', 'allcoach' ); ?></span>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $error ) ) : ?>
				<div class="ac-alert ac-alert--error">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
					<span><?php echo wp_kses_post( $error ); ?></span>
				</div>
			<?php endif; ?>

			<form method="post">
				<?php wp_nonce_field( 'allcoach_login', '_allcoach_nonce' ); ?>

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

				<div class="ac-form-group">
					<div class="ac-auth__label-row">
						<label class="ac-label" for="ac-password"><?php esc_html_e( 'Password', 'allcoach' ); ?> <span class="ac-req">*</span></label>
						<a href="<?php echo esc_url( $lostPasswordUrl ); ?>" class="ac-auth__forgot">
							<?php esc_html_e( 'Forgot password?', 'allcoach' ); ?>
						</a>
					</div>
					<input
						class="ac-input"
						type="password"
						id="ac-password"
						name="password"
						placeholder="&#x2022;&#x2022;&#x2022;&#x2022;&#x2022;&#x2022;&#x2022;&#x2022;"
						autocomplete="current-password"
						required
					>
				</div>

				<div class="ac-auth__remember">
					<label for="ac-rememberme">
						<input type="checkbox" id="ac-rememberme" name="rememberme" value="forever">
						<?php esc_html_e( 'Remember Me', 'allcoach' ); ?>
					</label>
				</div>

				<button type="submit" class="ac-btn ac-btn--primary ac-btn--full ac-btn--lg">
					<?php esc_html_e( 'Log In', 'allcoach' ); ?>
				</button>
			</form>
		</div>
	</div>
</div>
