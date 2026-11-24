<?php
/**
 * Booking flow template — date → time → confirm → success.
 *
 * @var string                                     $pageTitle         The page title.
 * @var callable                                   $routerUrl         URL generator.
 * @var \AllCoach\Application\Order\OrderView      $enrollment        The enrollment.
 * @var string                                     $programTitle      Program name.
 * @var string                                     $backUrl           URL to go back to.
 * @var int[]                                      $availableDays     Available day indices (0=Sun .. 6=Sat).
 * @var int                                        $durationMinutes   Session duration in minutes.
 * @var int                                        $remainingSessions Remaining bookable sessions.
 */

defined( 'ABSPATH' ) || exit;
?>

<a href="<?php echo esc_url( $backUrl ); ?>" class="ac-back" id="ac-book-back">
	<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5"/><path d="M12 5l-7 7 7 7"/></svg>
	<?php echo esc_html( $programTitle ); ?>
</a>

<div class="ac-page-header">
	<span class="ac-page-header__icon">
		<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/></svg>
	</span>
	<h2><?php echo esc_html( $pageTitle ); ?></h2>
</div>

<?php if ( $remainingSessions <= 0 ) : ?>
	<div class="ac-card ac-card--padded" style="text-align:center;padding:2rem">
		<p style="font-size:14px;color:var(--ac-text);margin:0 0 .5rem"><?php esc_html_e( 'All sessions for this program have already been scheduled.', 'allcoach' ); ?></p>
		<a href="<?php echo esc_url( $backUrl ); ?>" class="ac-btn ac-btn--primary ac-btn--sm"><?php esc_html_e( 'Go back', 'allcoach' ); ?></a>
	</div>
	<?php
	return;
endif;
?>

<div class="ac-steps" id="ac-book-steps">
	<div class="ac-step ac-step--active" data-step="1">
		<div class="ac-step__num">1</div>
		<span class="ac-step__label"><?php esc_html_e( 'Pick a date', 'allcoach' ); ?></span>
	</div>
	<div class="ac-step__line"></div>
	<div class="ac-step" data-step="2">
		<div class="ac-step__num">2</div>
		<span class="ac-step__label"><?php esc_html_e( 'Pick a time', 'allcoach' ); ?></span>
	</div>
	<div class="ac-step__line"></div>
	<div class="ac-step" data-step="3">
		<div class="ac-step__num">3</div>
		<span class="ac-step__label"><?php esc_html_e( 'Confirm', 'allcoach' ); ?></span>
	</div>
</div>

<div class="ac-book-step" id="ac-book-step-1">
	<p class="ac-book-hint"><?php esc_html_e( "Select an available date. Greyed out days are outside your coach's availability.", 'allcoach' ); ?></p>
	<div class="ac-card ac-card--padded">
		<div class="ac-cal-header">
			<button type="button" class="ac-cal-nav" id="ac-cal-prev">
				<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg>
			</button>
			<span id="ac-cal-month"></span>
			<button type="button" class="ac-cal-nav" id="ac-cal-next">
				<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg>
			</button>
		</div>
		<div class="ac-cal-grid ac-cal-grid--dow" id="ac-cal-dow"></div>
		<div class="ac-cal-grid" id="ac-cal-days"></div>
	</div>
	<div class="ac-book-actions">
		<button type="button" class="ac-btn ac-btn--primary ac-btn--sm" id="ac-book-next-date" disabled>
			<?php esc_html_e( 'Next — pick a time', 'allcoach' ); ?>
		</button>
	</div>
</div>

<div class="ac-book-step" id="ac-book-step-2" style="display:none">
	<p class="ac-book-date-label" id="ac-slot-date-label"></p>
	<p class="ac-book-hint"><?php esc_html_e( 'Select a slot. Strikethrough slots are already booked.', 'allcoach' ); ?></p>
	<div class="ac-card ac-card--padded">
		<div class="ac-slots" id="ac-slots-grid"></div>
		<div id="ac-slots-empty" style="display:none;text-align:center;padding:18px 0">
			<span style="font-size:13px;color:var(--ac-text-muted)"><?php esc_html_e( 'No slots available for this date.', 'allcoach' ); ?></span>
		</div>
		<div id="ac-slots-loading" style="display:none;text-align:center;padding:18px 0">
			<span style="font-size:13px;color:var(--ac-text-muted)"><?php esc_html_e( 'Loading slots…', 'allcoach' ); ?></span>
		</div>
	</div>
	<div class="ac-book-actions">
		<button type="button" class="ac-btn ac-btn--ghost ac-btn--sm" id="ac-book-back-date">
			<?php esc_html_e( 'Back', 'allcoach' ); ?>
		</button>
		<button type="button" class="ac-btn ac-btn--primary ac-btn--sm" id="ac-book-next-time" disabled>
			<?php esc_html_e( 'Next — review & confirm', 'allcoach' ); ?>
		</button>
	</div>
</div>

<div class="ac-book-step" id="ac-book-step-3" style="display:none">
	<p class="ac-book-hint"><?php esc_html_e( 'Review the details before confirming.', 'allcoach' ); ?></p>
	<div class="ac-confirm-box">
		<div class="ac-confirm-row">
			<span><?php esc_html_e( 'Program', 'allcoach' ); ?></span>
			<span id="ac-confirm-program"></span>
		</div>
		<div class="ac-confirm-row">
			<span><?php esc_html_e( 'Date', 'allcoach' ); ?></span>
			<span id="ac-confirm-date"></span>
		</div>
		<div class="ac-confirm-row">
			<span><?php esc_html_e( 'Time', 'allcoach' ); ?></span>
			<span id="ac-confirm-time"></span>
		</div>
		<div class="ac-confirm-row">
			<span><?php esc_html_e( 'Duration', 'allcoach' ); ?></span>
			<span id="ac-confirm-duration"></span>
		</div>
	</div>
	<div class="ac-form-group" style="margin-top:14px">
		<label class="ac-label" for="ac-book-message"><?php esc_html_e( 'Message for your coach (optional)', 'allcoach' ); ?></label>
		<textarea class="ac-input ac-textarea" id="ac-book-message" name="notes" rows="2" placeholder="<?php esc_attr_e( 'Any message about this session…', 'allcoach' ); ?>"></textarea>
	</div>
	<div class="ac-book-actions">
		<button type="button" class="ac-btn ac-btn--ghost ac-btn--sm" id="ac-book-back-time">
			<?php esc_html_e( 'Back', 'allcoach' ); ?>
		</button>
		<button type="button" class="ac-btn ac-btn--primary ac-btn--sm" id="ac-book-confirm">
			<?php esc_html_e( 'Confirm appointment', 'allcoach' ); ?>
		</button>
	</div>
	<div id="ac-book-error" class="ac-alert ac-alert--error" style="display:none;margin-top:10px"></div>
</div>

<!-- STEP 4: Success -->
<div class="ac-book-step" id="ac-book-step-success" style="display:none">
	<div class="ac-book-success">
		<div class="ac-book-success__icon">
			<svg width="22" height="22" fill="none" stroke="var(--ac-primary)" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
		</div>
		<h3><?php esc_html_e( 'Appointment confirmed!', 'allcoach' ); ?></h3>
		<p id="ac-success-summary"></p>
		<div class="ac-confirm-box" style="display:inline-block;text-align:left;min-width:260px;margin-bottom:1.5rem">
			<div class="ac-confirm-row">
				<span><?php esc_html_e( 'Program', 'allcoach' ); ?></span>
				<span id="ac-success-program"></span>
			</div>
			<div class="ac-confirm-row">
				<span><?php esc_html_e( 'Date & time', 'allcoach' ); ?></span>
				<span id="ac-success-datetime"></span>
			</div>
		</div>
		<div class="ac-book-success__actions">
			<a href="<?php echo esc_url( $routerUrl( 'appointments' ) ); ?>" class="ac-btn ac-btn--ghost ac-btn--sm">
				<?php esc_html_e( 'View appointments', 'allcoach' ); ?>
			</a>
			<a href="<?php echo esc_url( $backUrl ); ?>" class="ac-btn ac-btn--primary ac-btn--sm">
				<?php esc_html_e( 'Back to program', 'allcoach' ); ?>
			</a>
		</div>
	</div>
</div>

<div
	id="ac-booking-data"
	data-enrollment-id="<?php echo esc_attr( (string) $enrollment->enrollmentId ); ?>"
	data-program-title="<?php echo esc_attr( $programTitle ); ?>"
	data-available-days="<?php echo esc_attr( wp_json_encode( $availableDays ) ); ?>"
	data-duration-minutes="<?php echo esc_attr( (string) $durationMinutes ); ?>"
	data-remaining-sessions="<?php echo esc_attr( (string) $remainingSessions ); ?>"
	style="display:none"
></div>
