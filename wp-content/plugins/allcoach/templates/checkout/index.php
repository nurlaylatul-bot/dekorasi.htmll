<?php
/**
 * Checkout page template.
 *
 * @var bool                                    $isLoggedIn     Whether the user is logged in.
 * @var \WP_User|null                           $user           Current user (if logged in).
 * @var \AllCoach\Domain\Cart\CartItem|null     $cartItem       The cart item.
 * @var \AllCoach\Domain\Program\Program|null   $program        The program in cart.
 * @var int                                     $sessionCount   Number of sessions.
 * @var int                                     $sessionMinutes Duration per session in minutes.
 * @var string                                  $currency       Currency code.
 * @var array                                   $paymentMethods Enabled payment methods [{id, title, description}].
 * @var string                                  $dashboardUrl   URL to the dashboard.
 * @var string                                  $profileUrl     URL to the profile page.
 * @var string                                  $nonce          REST API nonce.
 * @var string                                  $checkoutEndpoint REST API checkout endpoint.
 * @var string                                  $guestEndpoint  REST API guest checkout endpoint.
 * @var string                                  $programsUrl    URL to browse programs.
 * @var bool                                    $consentRequired Whether the privacy consent checkbox is required.
 * @var string                                  $consentLabel   Consent checkbox label HTML (with privacy policy link).
 * @var \AllCoach\Application\Order\OrderView|null $pendingOrder Client's own pending PayPal order for this program, if any.
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- template variables
?>
<div class="ac-root">

	<?php if ( ! $program ) : ?>
		<div class="ac-empty-state">
			<div class="ac-empty-state__icon">
				<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"/></svg>
			</div>
			<h2 style="margin-bottom: var(--space-4);"><?php esc_html_e( 'Your cart is empty', 'allcoach' ); ?></h2>
			<a href="<?php echo esc_url( home_url() ); ?>" class="ac-btn ac-btn--primary"><?php esc_html_e( 'Back to home', 'allcoach' ); ?></a>
		</div>
	<?php else : ?>

		<div id="ac-view-checkout">
			<div class="ac-layout">
				<div class="ac-alert ac-alert--error" id="ac-checkout-error" style="display:none;margin-bottom: var(--space-2);">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
					<span id="ac-checkout-error-text"></span>
				</div>
				<?php if ( $pendingOrder ) : ?>
					<div class="ac-alert ac-alert--warning ac-alert--action" id="ac-pending-order-banner" data-order-id="<?php echo esc_attr( (string) $pendingOrder->id ); ?>" style="margin-bottom: var(--space-2);">
						<div class="ac-alert__content">
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
							<span><?php esc_html_e( 'You have a payment in progress for this program.', 'allcoach' ); ?></span>
						</div>
						<button type="button" class="ac-alert__btn" id="ac-discard-pending-order"><?php esc_html_e( 'Discard and start over', 'allcoach' ); ?></button>
					</div>
				<?php endif; ?>
				<form id="ac-checkout-form" novalidate>
					<div class="ac-main">
						<?php if ( ! $isLoggedIn ) : ?>
							<div class="ac-card">
								<div class="ac-card__head">
									<div class="ac-card__head-left">
										<span class="ac-badge">1</span>
										<h2><?php esc_html_e( 'Your information', 'allcoach' ); ?></h2>
									</div>
								</div>
								<div class="ac-card__body">
									<div class="ac-form-row">
										<div class="ac-form-group">
											<label class="ac-label" for="ac-first-name"><?php esc_html_e( 'First name', 'allcoach' ); ?> <span class="ac-req">*</span></label>
											<input class="ac-input" type="text" id="ac-first-name" name="first_name" placeholder="<?php esc_attr_e( 'John', 'allcoach' ); ?>" required>
											<span class="ac-field-error" data-field="first_name"></span>
										</div>
										<div class="ac-form-group">
											<label class="ac-label" for="ac-last-name"><?php esc_html_e( 'Last name', 'allcoach' ); ?> <span class="ac-req">*</span></label>
											<input class="ac-input" type="text" id="ac-last-name" name="last_name" placeholder="<?php esc_attr_e( 'Doe', 'allcoach' ); ?>" required>
											<span class="ac-field-error" data-field="last_name"></span>
										</div>
									</div>
									<div class="ac-form-group">
										<label class="ac-label" for="ac-email"><?php esc_html_e( 'Email address', 'allcoach' ); ?> <span class="ac-req">*</span></label>
										<input class="ac-input" type="email" id="ac-email" name="email" placeholder="<?php esc_attr_e( 'john@example.com', 'allcoach' ); ?>" required>
										<span class="ac-field-error" data-field="email"></span>
									</div>
									<div class="ac-form-group">
										<label class="ac-label" for="ac-phone"><?php esc_html_e( 'Phone number', 'allcoach' ); ?></label>
										<input class="ac-input" type="tel" id="ac-phone" name="phone" placeholder="<?php esc_attr_e( '+1 (555) 000-0000', 'allcoach' ); ?>">
									</div>
									<div class="ac-form-group">
										<label class="ac-label" for="ac-notes-guest">
											<?php esc_html_e( 'Note', 'allcoach' ); ?>
											<span class="ac-label-hint"><?php esc_html_e( 'optional', 'allcoach' ); ?></span>
										</label>
										<textarea class="ac-input ac-textarea" id="ac-notes-guest" name="notes" rows="3" placeholder="<?php esc_attr_e( 'Any message for your coach...', 'allcoach' ); ?>"></textarea>
									</div>
								</div>
							</div>
						<?php else : ?>
							<div class="ac-card">
								<div class="ac-card__head">
									<div class="ac-card__head-left">
										<span class="ac-badge">1</span>
										<h2><?php esc_html_e( 'Your information', 'allcoach' ); ?></h2>
									</div>
									<a href="<?php echo esc_url( $profileUrl ); ?>" class="ac-edit-link"><?php esc_html_e( 'Edit profile', 'allcoach' ); ?> &rarr;</a>
								</div>
								<div class="ac-card__body">
									<div class="ac-info-grid">
										<div class="ac-info-item">
											<label><?php esc_html_e( 'First name', 'allcoach' ); ?></label>
											<span><?php echo esc_html( $user->first_name ?: $user->display_name ); ?></span>
										</div>
										<div class="ac-info-item">
											<label><?php esc_html_e( 'Last name', 'allcoach' ); ?></label>
											<span><?php echo esc_html( $user->last_name ); ?></span>
										</div>
										<div class="ac-info-item">
											<label><?php esc_html_e( 'Email address', 'allcoach' ); ?></label>
											<span><?php echo esc_html( $user->user_email ); ?></span>
										</div>
										<?php
										$phone = get_user_meta( $user->ID, '_allcoach_client_phone', true );
										if ( $phone ) :
											?>
											<div class="ac-info-item">
												<label><?php esc_html_e( 'Phone number', 'allcoach' ); ?></label>
												<span><?php echo esc_html( $phone ); ?></span>
											</div>
										<?php endif; ?>
									</div>
									<div class="ac-form-group">
										<label class="ac-label" for="ac-notes-user">
											<?php esc_html_e( 'Note', 'allcoach' ); ?>
											<span class="ac-label-hint"><?php esc_html_e( 'optional', 'allcoach' ); ?></span>
										</label>
										<textarea class="ac-input ac-textarea" id="ac-notes-user" name="notes" rows="3" placeholder="<?php esc_attr_e( 'Any message for your coach...', 'allcoach' ); ?>"></textarea>
									</div>
								</div>
							</div>
						<?php endif; ?>

						<?php
						/**
						 * Fires between the "Your information" and "Payment method"
						 * cards on checkout. Addons (e.g. Pro Contracts) render an
						 * extra required step here, such as a contract-signing card.
						 *
						 * @param \AllCoach\Domain\Program\Program $program    Program being purchased.
						 * @param bool                             $isLoggedIn Whether the buyer is logged in.
						 */
						do_action( 'allcoach:checkout:before_payment', $program, $isLoggedIn );
						?>

						<?php if ( ! $program->isFree() ) : ?>
						<!-- PAYMENT METHOD -->
						<?php $noPaymentMethods = empty( $paymentMethods ); ?>
						<div class="ac-card ac-section-gap">
							<div class="ac-card__head">
								<div class="ac-card__head-left">
									<span class="ac-badge">2</span>
									<h2><?php esc_html_e( 'Payment method', 'allcoach' ); ?></h2>
								</div>
							</div>
							<div class="ac-card__body">
								<?php if ( $noPaymentMethods ) : ?>
									<div class="ac-alert ac-alert--warning">
										<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
										<span><?php esc_html_e( 'No payment methods are enabled. Enable a payment method in Settings to accept paid orders.', 'allcoach' ); ?></span>
									</div>
								<?php else : ?>
									<?php foreach ( $paymentMethods as $index => $method ) : ?>
										<label class="ac-payment">
											<div class="ac-payment__radio-row">
												<input class="ac-payment__radio" type="radio" name="payment_method" value="<?php echo esc_attr( $method['id'] ); ?>" <?php checked( $index, 0 ); ?>>
												<span class="ac-payment__title"><?php echo esc_html( $method['title'] ); ?></span>
											</div>
											<?php if ( $method['description'] ) : ?>
												<p class="ac-payment__desc"><?php echo esc_html( $method['description'] ); ?></p>
											<?php endif; ?>
										</label>
									<?php endforeach; ?>
								<?php endif; ?>
							</div>
						</div>
						<?php else : ?>
							<?php $noPaymentMethods = false; ?>
						<?php endif; ?>

					</div>
					<div class="ac-sidebar">
						<div class="ac-card ac-card--sticky">
							<div class="ac-card__head">
								<h2><?php esc_html_e( 'Order summary', 'allcoach' ); ?></h2>
							</div>
							<div class="ac-card__body">
								<div class="ac-summary__program">
									<div class="ac-summary__info">
										<strong><?php echo esc_html( $program->getTitle() ); ?></strong>
										<?php if ( $sessionCount > 0 ) : ?>
											<span>
												<?php
												printf(
													/* translators: 1: number of sessions, 2: duration in minutes */
													esc_html__( '%1$d x %2$d min appointments', 'allcoach' ),
													absint( $sessionCount ),
													absint( $sessionMinutes )
												);
												?>
											</span>
										<?php endif; ?>
									</div>
								</div>
								<!-- <div class="ac-coupon">
									<input class="ac-input" type="text" placeholder="<?php // esc_attr_e( 'Coupon code', 'allcoach' ); ?>" disabled style="opacity:0.5;cursor:not-allowed;">
									<button type="button" class="ac-coupon__btn" disabled><?php // esc_html_e( 'Apply', 'allcoach' ); ?></button>
								</div> -->
								<?php $amount = $program->getPrice(); ?>
								<div class="ac-summary__row">
									<span><?php esc_html_e( 'Program price', 'allcoach' ); ?></span>
									<span><?php echo esc_html( $currency . ' ' . number_format( $amount, 2 ) ); ?></span>
								</div>
								<div class="ac-summary__row ac-summary__row--total">
									<span><?php esc_html_e( 'Total', 'allcoach' ); ?></span>
									<span><?php echo esc_html( $currency . ' ' . number_format( $amount, 2 ) ); ?></span>
								</div>

								<?php if ( ! empty( $consentRequired ) ) : ?>
									<div class="ac-consent" style="margin: var(--space-4) 0;">
										<label class="ac-consent__label" for="ac-consent" style="display:flex;align-items:center;gap:var(--space-2);font-size:var(--text-sm);font-weight:var(--weight-semibold);line-height:1.5;color:var(--ac-text);cursor:pointer;">
											<input type="checkbox" id="ac-consent" name="consent" value="1" style="margin:0;width:16px;height:16px;flex-shrink:0;cursor:pointer;accent-color:var(--ac-primary);">
											<span class="ac-consent__text">
												<?php
												echo wp_kses(
													$consentLabel ?? '',
													[ 'a' => [ 'href' => [], 'target' => [], 'rel' => [] ] ]
												);
												?>
											</span>
										</label>
										<span class="ac-field-error" data-field="consent" style="display:block;margin-left:24px;"></span>
									</div>
								<?php endif; ?>

								<button type="submit" class="ac-btn-submit" id="ac-place-order" <?php disabled( $noPaymentMethods ); ?>>
									<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
									<span id="ac-btn-text"><?php esc_html_e( 'Place Order', 'allcoach' ); ?></span>
								</button>
								<div class="ac-paypal-buttons" id="ac-paypal-buttons" style="display:none;"></div>
								<p class="ac-paypal-note" id="ac-paypal-note" style="display:none;">
									<?php esc_html_e( 'You will complete payment securely with PayPal.', 'allcoach' ); ?>
								</p>

								<?php if ( ! $isLoggedIn ) : ?>
									<p class="ac-register-note">
										<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
										<?php esc_html_e( 'A client account will be created and a password setup email will be sent to your email.', 'allcoach' ); ?>
									</p>
								<?php endif; ?>

							</div>
						</div>
					</div>
					<input type="hidden" name="program_id" value="<?php echo esc_attr( (string) $program->getId() ); ?>">
				</form>
			</div>
		</div>
		<div id="ac-view-thankyou" style="display:none;">
			<div class="ac-thankyou">
				<div class="ac-thankyou__icon">
					<svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
				</div>
				<h1><?php esc_html_e( 'Order placed!', 'allcoach' ); ?></h1>
				<p id="ac-ty-message"></p>
				<div class="ac-thankyou__order" id="ac-ty-details"></div>
				<a href="<?php echo esc_url( $dashboardUrl ); ?>" class="ac-btn ac-btn--primary ac-btn--full">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
					<?php esc_html_e( 'Go to My Dashboard', 'allcoach' ); ?>
				</a>
				<p class="ac-thankyou__note" id="ac-thankyou__note"></p>
			</div>
		</div>
	<?php endif; ?>
</div>
