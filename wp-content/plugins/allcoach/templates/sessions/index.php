<?php
/**
 * Sessions listing page template.
 *
 * @var string                                          $pageTitle    The page title.
 * @var callable                                        $routerUrl    URL generator.
 * @var \AllCoach\Domain\Booking\SessionBooking[]       $bookings     User bookings.
 * @var \AllCoach\Application\Order\OrderView[]         $enrollments  User enrollments.
 * @var string                                          $today        Today's date (Y-m-d).
 * @var int                                             $currentPage  Current page number.
 * @var int                                             $perPage      Items per page.
 * @var array<int, array{scheduled: int, completed: int, total: int, remaining: int}> $progressMap Progress per enrollment ID.
 * @var array<int, int>                                 $durationMap  Duration in minutes per enrollment ID.
 */

use Carbon\CarbonImmutable;

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- template variables

$templateLoader = \AllCoach\Plugin::getInstance()->getContainer()->get( \AllCoach\Support\TemplateLoader::class );

$programTitles = [];
foreach ( $enrollments as $enrollment ) {
	if ( $enrollment->programTitle ) {
		$programTitles[ $enrollment->programId ] = $enrollment->programTitle;
	}
}

// Split bookings by status.
$upcomingBookings  = [];
$completedBookings = [];
$cancelledBookings = [];
foreach ( $bookings as $booking ) {
	if ( $booking->getStatus()->isCancelled() ) {
		$cancelledBookings[] = $booking;
	} elseif ( $booking->getStatus()->isCompleted() || $booking->getScheduledDate() < $today ) {
		$completedBookings[] = $booking;
	} else {
		$upcomingBookings[] = $booking;
	}
}
usort( $upcomingBookings, fn( $a, $b ) => $a->getScheduledDate() <=> $b->getScheduledDate() ?: $a->getStartTime() <=> $b->getStartTime() );
usort( $completedBookings, fn( $a, $b ) => $b->getScheduledDate() <=> $a->getScheduledDate() ?: $b->getStartTime() <=> $a->getStartTime() );
usort( $cancelledBookings, fn( $a, $b ) => $b->getScheduledDate() <=> $a->getScheduledDate() ?: $b->getStartTime() <=> $a->getStartTime() );

$sortedBookings = array_merge( $upcomingBookings, $completedBookings, $cancelledBookings );

$activeFilter  = isset( $_GET['filter'] ) ? sanitize_text_field( wp_unslash( $_GET['filter'] ) ) : 'all'; // phpcs:ignore WordPress.Security.NonceVerification
$filteredItems = match ( $activeFilter ) {
	'upcoming'  => $upcomingBookings,
	'completed' => $completedBookings,
	'cancelled' => $cancelledBookings,
	default     => $sortedBookings,
};

$totalItems    = count( $filteredItems );
$filteredItems = array_slice( $filteredItems, ( $currentPage - 1 ) * $perPage, $perPage );

$confirmedEnrollments = array_filter( $enrollments, fn( $e ) => $e->status === 'confirmed' );
$hasPrograms          = ! empty( $confirmedEnrollments );
$bookableEnrollments  = array_filter( $confirmedEnrollments, fn( $e ) => ( $progressMap[ $e->enrollmentId ]['remaining'] ?? 1 ) > 0 );
$allFullyBooked       = $hasPrograms && empty( $bookableEnrollments );
?>

<div class="ac-page-header" id="ac-appointments-header">
	<span class="ac-page-header__icon">
		<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/></svg>
	</span>
	<h2><?php esc_html_e( 'My Appointments', 'allcoach' ); ?></h2>
	<?php if ( $hasPrograms ) : ?>
		<button type="button" class="ac-btn ac-btn--primary ac-btn--sm" id="ac-book-appointment-btn" <?php echo $allFullyBooked ? 'disabled' : ''; ?>>
			<?php esc_html_e( 'Book appointment', 'allcoach' ); ?>
		</button>
	<?php endif; ?>
</div>

<?php if ( $hasPrograms ) : ?>
<div id="ac-pick-program" style="display:none">
	<button type="button" class="ac-back" id="ac-picker-back-btn">
		<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 12H5"/><path d="M12 5l-7 7 7 7"/></svg>
		<?php esc_html_e( 'Back', 'allcoach' ); ?>
	</button>
	<div class="ac-page-header">
		<span class="ac-page-header__icon">
			<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
		</span>
		<h2><?php esc_html_e( 'Select a program', 'allcoach' ); ?></h2>
	</div>
	<p style="font-size:var(--text-base);color:var(--ac-text-muted);margin-bottom:1rem"><?php esc_html_e( 'Which program would you like to book an appointment for?', 'allcoach' ); ?></p>
	<div class="ac-card" style="padding:0 1.1rem">
		<?php
		foreach ( $confirmedEnrollments as $enrollment ) :
			$bookUrl       = add_query_arg( 'book', $enrollment->programId, $routerUrl( 'appointments' ) );
			$progress      = $progressMap[ $enrollment->enrollmentId ] ?? null;
			$duration      = $durationMap[ $enrollment->enrollmentId ] ?? 60;
			$isFullyBooked = $progress && $progress['remaining'] <= 0;
			$meta          = $progress
				? sprintf(
					/* translators: 1: completed count, 2: total count, 3: duration in minutes */
					__( '%1$d of %2$d completed · %3$d min each', 'allcoach' ),
					$progress['completed'],
					$progress['total'],
					$duration
				)
				: sprintf(
					/* translators: %d: duration in minutes */
					__( '%d min each', 'allcoach' ),
					$duration
				);
			?>
			<?php if ( $isFullyBooked ) : ?>
				<div class="ac-appt-row" style="opacity:.5;cursor:default">
					<div class="ac-appt-info">
						<div class="ac-appt-info__title"><?php echo esc_html( $enrollment->programTitle ?? __( 'Program', 'allcoach' ) ); ?></div>
						<div class="ac-appt-info__meta"><?php echo esc_html( $meta ); ?></div>
					</div>
					<span class="ac-pill ac-pill--completed"><?php esc_html_e( 'All booked', 'allcoach' ); ?></span>
				</div>
			<?php else : ?>
				<a href="<?php echo esc_url( $bookUrl ); ?>" class="ac-appt-row" style="text-decoration:none;color:inherit;cursor:pointer">
					<div class="ac-appt-info">
						<div class="ac-appt-info__title"><?php echo esc_html( $enrollment->programTitle ?? __( 'Program', 'allcoach' ) ); ?></div>
						<div class="ac-appt-info__meta"><?php echo esc_html( $meta ); ?></div>
					</div>
					<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink:0;opacity:.4"><path d="M9 18l6-6-6-6"/></svg>
				</a>
			<?php endif; ?>
		<?php endforeach; ?>
	</div>
</div>
<?php endif; ?>

<div id="ac-appointments-content">
<?php
$filterTabs = [
	[ __( 'All', 'allcoach' ), 'all', count( $sortedBookings ) ],
	[ __( 'Upcoming', 'allcoach' ), 'upcoming', count( $upcomingBookings ) ],
	[ __( 'Completed', 'allcoach' ), 'completed', count( $completedBookings ) ],
	[ __( 'Cancelled', 'allcoach' ), 'cancelled', count( $cancelledBookings ) ],
];
$templateLoader->loadTemplate(
	'partials/filter-tabs.php',
	[
		'tabs'        => $filterTabs,
		'activeValue' => $activeFilter,
	]
);
?>

<?php if ( empty( $filteredItems ) ) : ?>
	<?php
	$calendarIcon = '<path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/>';
	switch ( $activeFilter ) {
		case 'upcoming':
			$emptyIcon  = $calendarIcon;
			$emptyTitle = __( 'No upcoming appointments', 'allcoach' );
			$emptySub   = __( 'Your upcoming sessions will appear here.', 'allcoach' );
			break;
		case 'completed':
			$emptyIcon  = '<polyline points="20 6 9 17 4 12"/>';
			$emptyTitle = __( 'No completed appointments', 'allcoach' );
			$emptySub   = __( 'Your completed sessions will appear here.', 'allcoach' );
			break;
		case 'cancelled':
			$emptyIcon  = $calendarIcon;
			$emptyTitle = __( 'No cancelled appointments', 'allcoach' );
			$emptySub   = __( 'Any cancelled sessions will appear here.', 'allcoach' );
			break;
		default:
			$emptyIcon  = $calendarIcon;
			$emptyTitle = __( 'No appointments yet', 'allcoach' );
			$emptySub   = __( 'Book your first session to get started.', 'allcoach' );
			break;
	}

	$templateLoader->loadTemplate(
		'partials/empty-state.php',
		[
			'icon'   => $emptyIcon,
			'title'  => $emptyTitle,
			'sub'    => $emptySub,
			'button' => ( $activeFilter === 'all' && $hasPrograms && ! $allFullyBooked ) ? [
				'label' => __( 'Book appointment', 'allcoach' ),
				'id'    => 'ac-book-appointment-btn-empty',
			] : null,
		]
	);
	?>
<?php else : ?>
	<div class="ac-card ac-card--padded" style="padding:0 1.1rem">
		<?php foreach ( $filteredItems as $booking ) : ?>
			<?php
			$scheduledDate = CarbonImmutable::parse( $booking->getScheduledDate() );
			$startTime     = CarbonImmutable::parse( $booking->getStartTime() );
			$endTime       = CarbonImmutable::parse( $booking->getEndTime() );
			$durationMins  = ( (int) $endTime->format( 'H' ) * 60 + (int) $endTime->format( 'i' ) ) - ( (int) $startTime->format( 'H' ) * 60 + (int) $startTime->format( 'i' ) );
			$isToday       = $booking->getScheduledDate() === $today;
			$isPast        = $booking->getScheduledDate() < $today;
			$isCompleted   = $booking->getStatus()->isCompleted();
			$isCancelled   = $booking->getStatus()->isCancelled();
			$isExpired     = $isPast && ! $isCompleted && ! $isCancelled;
			$programTitle  = $programTitles[ $booking->getProgramId() ] ?? __( 'Session', 'allcoach' );
			$timeLabel     = $scheduledDate->format( 'D' ) . ', ' . $startTime->format( 'g:i A' ) . ' · ' . $durationMins . ' min';
			if ( $booking->getMeetingType()->isGoogleMeet() && $booking->getMeetingUrl() ) {
				$timeLabel .= ' · ' . __( 'Google Meet', 'allcoach' );
			} elseif ( $booking->getMeetingType()->isFaceToFace() ) {
				$timeLabel .= ' · ' . __( 'In-person', 'allcoach' );
			}
			?>
			<div class="ac-appt-row">
				<div class="ac-appt-date <?php echo $isToday ? 'ac-appt-date--today' : ''; ?>">
					<div class="ac-appt-date__month"><?php echo esc_html( strtoupper( $scheduledDate->format( 'M' ) ) ); ?></div>
					<div class="ac-appt-date__day"><?php echo esc_html( $scheduledDate->format( 'j' ) ); ?></div>
				</div>
				<div class="ac-appt-info">
					<div class="ac-appt-info__title">
						<?php echo esc_html( $programTitle ); ?>
						<?php if ( $isToday ) : ?>
							<span class="ac-pill ac-pill--today"><?php esc_html_e( 'TODAY', 'allcoach' ); ?></span>
						<?php endif; ?>
					</div>
					<span class="ac-appt-info__meta"><?php echo esc_html( $timeLabel ); ?></span>
				</div>
				<div class="ac-appt-row__action">
					<?php if ( $isCompleted ) : ?>
						<span class="ac-pill ac-pill--completed"><?php esc_html_e( 'Completed', 'allcoach' ); ?></span>
					<?php elseif ( $isCancelled ) : ?>
						<span class="ac-pill ac-pill--cancelled"><?php esc_html_e( 'Cancelled', 'allcoach' ); ?></span>
					<?php elseif ( $isExpired ) : ?>
						<span class="ac-pill ac-pill--expired"><?php esc_html_e( 'Expired', 'allcoach' ); ?></span>
					<?php elseif ( $isToday && $booking->getMeetingType()->isFaceToFace() ) : ?>
						<span class="ac-appt-info__meta ac-appt-info__meta--location"><?php echo esc_html( $booking->getLocation() ?? '' ); ?></span>
					<?php elseif ( $isToday && $booking->getMeetingUrl() ) : ?>
						<a href="<?php echo esc_url( $booking->getMeetingUrl() ); ?>" class="ac-btn ac-btn--primary ac-btn--sm" target="_blank" rel="noopener">
							<?php esc_html_e( 'Join call', 'allcoach' ); ?>
						</a>
					<?php else : ?>
						<span class="ac-pill ac-pill--upcoming"><?php esc_html_e( 'Upcoming', 'allcoach' ); ?></span>
					<?php endif; ?>
				</div>
			</div>
			<?php
			/**
			 * Fires after each appointment row on the sessions list, so add-ons
			 * can append per-appointment markup without editing this template.
			 *
			 * @param \AllCoach\Domain\Booking\SessionBooking $booking Current booking.
			 */
			do_action( 'allcoach:sessions:after_booking_row', $booking );
			?>
		<?php endforeach; ?>
	</div>

	<?php
	$paginationBaseUrl = $routerUrl( 'appointments' );
	if ( $activeFilter !== 'all' ) {
		$paginationBaseUrl = add_query_arg( 'filter', $activeFilter, $paginationBaseUrl );
	}
	$templateLoader->loadTemplate(
		'partials/pagination.php',
		[
			'currentPage' => $currentPage,
			'totalItems'  => $totalItems,
			'perPage'     => $perPage,
			'baseUrl'     => $paginationBaseUrl,
		]
	);
	?>
<?php endif; ?>
</div><!-- #ac-appointments-content -->
