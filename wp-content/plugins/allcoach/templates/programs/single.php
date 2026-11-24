<?php
/**
 * Single program page template.
 *
 * @var string                                        $pageTitle        The page title.
 * @var callable                                      $routerUrl        URL generator.
 * @var \AllCoach\Domain\Program\Program              $program          The program.
 * @var \AllCoach\Application\Order\OrderView|null    $enrollment       User enrollment.
 * @var bool                                          $isActive         Whether the enrollment's order is confirmed.
 * @var \AllCoach\Domain\Booking\SessionBooking[]     $bookings         Program bookings.
 * @var array{scheduled: int, completed: int, total: int, remaining: int}|null $progress Session progress data.
 * @var \AllCoach\Domain\Activity\Activity[]           $activities       Program activities.
 * @var array{completed: int, total: int, percentage: float, completed_activity_ids: int[]}|null $activityProgress Activity progress data.
 * @var string                                        $today            Today's date (Y-m-d).
 */

use Carbon\CarbonImmutable;

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- template variables

$templateLoader = \AllCoach\Plugin::getInstance()->getContainer()->get( \AllCoach\Support\TemplateLoader::class );

$completed    = $progress['completed'] ?? 0;
$total        = $progress['total'] ?? 0;
$percent      = $total > 0 ? round( ( $completed / $total ) * 100 ) : 0;
$hasRemaining = ( $progress['remaining'] ?? 0 ) > 0;

$actCompleted     = $activityProgress['completed'] ?? 0;
$actTotal         = $activityProgress['total'] ?? count( $activities );
$completedActIds  = $activityProgress['completed_activity_ids'] ?? [];

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab state
$activeTab = isset( $_GET['tab'] ) && $_GET['tab'] === 'activities' ? 'activities' : 'sessions';

$programUrl = $routerUrl( 'programs', $program->getId() );
?>

<a href="<?php echo esc_url( $routerUrl( 'programs' ) ); ?>" class="ac-back">
	<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5"/><path d="M12 5l-7 7 7 7"/></svg>
	<?php esc_html_e( 'My Programs', 'allcoach' ); ?>
</a>

<div class="ac-page-header">
	<span class="ac-page-header__icon">
		<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="14" x="2" y="3" rx="2"/><line x1="8" x2="16" y1="21" y2="21"/><line x1="12" x2="12" y1="17" y2="21"/></svg>
	</span>
	<h2><?php echo esc_html( $program->getTitle() ); ?></h2>
</div>

<?php if ( $enrollment ) : ?>
	<div class="ac-detail-header">
		<div>
			<?php if ( $activeTab === 'sessions' ) : ?>
				<div class="ac-detail-header__text">
					<?php
					printf(
						wp_kses(
							/* translators: 1: completed count, 2: total count */
							__( '<strong>%1$d of %2$d</strong> appointments completed', 'allcoach' ),
							[ 'strong' => [] ]
						),
						(int) $completed,
						(int) $total
					);
					?>
				</div>
				<div class="ac-progress" style="width:180px;margin-top:6px">
					<div class="ac-progress__fill" style="width:<?php echo esc_attr( (string) $percent ); ?>%"></div>
				</div>
			<?php else : ?>
				<div class="ac-detail-header__text">
					<?php
					printf(
						wp_kses(
							/* translators: 1: completed count, 2: total count */
							__( '<strong>%1$d of %2$d</strong> activities completed', 'allcoach' ),
							[ 'strong' => [] ]
						),
						(int) $actCompleted,
						(int) $actTotal
					);
					?>
				</div>
				<div class="ac-progress" style="width:180px;margin-top:6px">
					<?php $actPercent = $actTotal > 0 ? round( ( $actCompleted / $actTotal ) * 100 ) : 0; ?>
					<div class="ac-progress__fill" style="width:<?php echo esc_attr( (string) $actPercent ); ?>%"></div>
				</div>
			<?php endif; ?>
		</div>
		<?php if ( $isActive && $hasRemaining && $activeTab === 'sessions' ) : ?>
			<a href="<?php echo esc_url( add_query_arg( 'book', $program->getId(), $programUrl ) ); ?>" class="ac-btn ac-btn--primary ac-btn--sm">
				<?php esc_html_e( 'Book next appointment', 'allcoach' ); ?>
			</a>
		<?php elseif ( $activeTab === 'sessions' ) : ?>
			<button type="button" class="ac-btn ac-btn--primary ac-btn--sm" disabled>
				<?php esc_html_e( 'Book appointment', 'allcoach' ); ?>
			</button>
		<?php endif; ?>
	</div>
<?php endif; ?>

<div class="ac-filter-tabs">
	<a href="<?php echo esc_url( $programUrl ); ?>" class="ac-filter-tab <?php echo $activeTab === 'sessions' ? 'is-active' : ''; ?>">
		<?php esc_html_e( 'Sessions', 'allcoach' ); ?>
		<span class="ac-filter-tab__count">(<?php echo (int) $total; ?>)</span>
	</a>
	<a href="<?php echo esc_url( add_query_arg( 'tab', 'activities', $programUrl ) ); ?>" class="ac-filter-tab <?php echo $activeTab === 'activities' ? 'is-active' : ''; ?>">
		<?php esc_html_e( 'Activities', 'allcoach' ); ?>
		<span class="ac-filter-tab__count">(<?php echo (int) $actTotal; ?>)</span>
	</a>
</div>

<?php if ( $activeTab === 'sessions' ) : ?>

	<?php
	$upcomingBookings = [];
	$pastBookings     = [];
	foreach ( $bookings as $booking ) {
		if ( $booking->getScheduledDate() >= $today ) {
			$upcomingBookings[] = $booking;
		} else {
			$pastBookings[] = $booking;
		}
	}
	usort( $upcomingBookings, fn( $a, $b ) => $a->getScheduledDate() <=> $b->getScheduledDate() ?: $a->getStartTime() <=> $b->getStartTime() );
	usort( $pastBookings, fn( $a, $b ) => $b->getScheduledDate() <=> $a->getScheduledDate() ?: $b->getStartTime() <=> $a->getStartTime() );

	$sortedBookings = array_merge( $upcomingBookings, $pastBookings );
	?>

	<?php if ( empty( $sortedBookings ) ) : ?>
		<?php
		$templateLoader->loadTemplate(
			'partials/empty-state.php',
			[
				'icon'   => '<path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/>',
				'title'  => __( 'No appointments booked', 'allcoach' ),
				'sub'    => __( 'Book your first session for this program.', 'allcoach' ),
				'button' => ( $isActive && $hasRemaining ) ? [
					'label' => __( 'Book appointment', 'allcoach' ),
					'url'   => add_query_arg( 'book', $program->getId(), $programUrl ),
				] : null,
			]
		);
		?>
	<?php else : ?>
		<div class="ac-card ac-card--padded" style="padding:0 1.1rem">
			<?php foreach ( $sortedBookings as $booking ) : ?>
				<?php
				$scheduledDate = CarbonImmutable::parse( $booking->getScheduledDate() );
				$startTime     = CarbonImmutable::parse( $booking->getStartTime() );
				$endTime       = CarbonImmutable::parse( $booking->getEndTime() );
				$durationMins  = ( (int) $endTime->format( 'H' ) * 60 + (int) $endTime->format( 'i' ) )
					- ( (int) $startTime->format( 'H' ) * 60 + (int) $startTime->format( 'i' ) );
				$isToday       = $booking->getScheduledDate() === $today;
				$isPast        = $booking->getScheduledDate() < $today;
				$isCompleted   = $booking->getStatus()->getValue() === 'completed';
				$isCancelled   = $booking->getStatus()->getValue() === 'cancelled';
				$isExpired     = $isPast && ! $isCompleted && ! $isCancelled;

				$timeLabel = $scheduledDate->format( 'D' ) . ', ' . $startTime->format( 'g:i A' ) . ' · ' . $durationMins . ' min';
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
						<span class="ac-appt-info__meta"><?php echo esc_html( $timeLabel ); ?></span>
					</div>
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
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

<?php else : ?>

	<?php if ( $enrollment && ! $isActive ) : ?>
		<?php
		$templateLoader->loadTemplate(
			'partials/empty-state.php',
			[
				'icon'  => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
				'title' => __( 'Awaiting confirmation', 'allcoach' ),
				'sub'   => __( 'Your order is awaiting payment confirmation. Activities will unlock once it’s confirmed.', 'allcoach' ),
			]
		);
		?>
	<?php elseif ( empty( $activities ) ) : ?>
		<?php
		$templateLoader->loadTemplate(
			'partials/empty-state.php',
			[
				'icon'  => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>',
				'title' => __( 'No activities yet', 'allcoach' ),
				'sub'   => __( 'Your coach will add activities to this program.', 'allcoach' ),
			]
		);
		?>
	<?php else : ?>
		<div class="ac-card ac-card--padded" style="padding:0 1.1rem">
			<?php foreach ( $activities as $activity ) : ?>
				<?php
				$isActivityCompleted = in_array( $activity->getId(), $completedActIds, true );

				if ( $isActivityCompleted && $enrollment ) {
					/**
					 * Filters whether an activity row is considered completed.
					 *
					 * @param bool                                  $isActivityCompleted Whether the activity is completed.
					 * @param \AllCoach\Domain\Activity\Activity    $activity            Activity domain object.
					 * @param \AllCoach\Application\Order\OrderView $enrollment          Enrollment/order view.
					 */
					$isActivityCompleted = apply_filters( 'allcoach:activity:is_completed', $isActivityCompleted, $activity, $enrollment );
				}

				$activityTitle = $activity->getSettings()['title'] ?? sprintf(
					/* translators: 1: week number, 2: day number */
					__( 'Week %1$d – Day %2$d', 'allcoach' ),
					$activity->getWeek(),
					$activity->getDay()
				);

				$weekLabel = sprintf(
					/* translators: %d: week number */
					__( 'Week %d', 'allcoach' ),
					$activity->getWeek()
				);
				?>
				<div class="ac-prog-row">
					<div class="ac-prog-row__body">
						<div class="ac-prog-row__title">
							<?php echo esc_html( $activityTitle ); ?>
						</div>
						<div class="ac-prog-row__meta">
							<?php echo esc_html( $weekLabel ); ?>
							<?php
							/**
							 * Fires after the week label in an activity row.
							 *
							 * @param \AllCoach\Domain\Activity\Activity     $activity   The activity.
							 * @param \AllCoach\Application\Order\OrderView  $enrollment The enrollment/order view.
							 */
							do_action( 'allcoach:activity:row_meta', $activity, $enrollment );
							?>
						</div>
					</div>
					<?php
					$statusPill = null;
					if ( ! $isActivityCompleted ) {
						/**
						 * Filters the status pill for an incomplete activity row.
						 *
						 * @param array{class: string, label: string}|null $statusPill Custom pill, or null for the default.
						 * @param \AllCoach\Domain\Activity\Activity        $activity   The activity.
						 * @param \AllCoach\Application\Order\OrderView     $enrollment The enrollment/order view.
						 */
						$statusPill = apply_filters( 'allcoach:activity:status_pill', null, $activity, $enrollment );
					}
					?>
					<?php if ( $isActivityCompleted ) : ?>
						<span class="ac-pill ac-pill--completed"><?php esc_html_e( 'Completed', 'allcoach' ); ?></span>
					<?php elseif ( $statusPill ) : ?>
						<span class="ac-pill <?php echo esc_attr( $statusPill['class'] ); ?>"><?php echo esc_html( $statusPill['label'] ); ?></span>
					<?php else : ?>
						<span class="ac-pill ac-pill--inactive"><?php esc_html_e( 'Not started', 'allcoach' ); ?></span>
					<?php endif; ?>
					<a href="<?php echo esc_url( add_query_arg( 'activity', $activity->getId(), $programUrl ) ); ?>" class="ac-btn ac-btn--ghost ac-btn--sm">
						<?php esc_html_e( 'View', 'allcoach' ); ?>
					</a>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

<?php endif; ?>
