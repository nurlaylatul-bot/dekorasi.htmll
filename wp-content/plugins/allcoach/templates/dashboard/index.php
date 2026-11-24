<?php
/**
 * Dashboard page template.
 *
 * @var string                                   $pageTitle      The page title.
 * @var \WP_User                                  $currentUser    The current user.
 * @var bool                                     $isCoach        Whether user is a coach.
 * @var bool                                     $isClient       Whether user is a client.
 * @var callable                                 $routerUrl      URL generator.
 * @var \AllCoach\Application\Order\OrderView[]  $enrollments    Confirmed enrollments.
 * @var \AllCoach\Domain\Booking\SessionBooking[] $todayBookings  Today's scheduled bookings.
 * @var array<int, array{scheduled: int, completed: int, total: int, remaining: int}> $progressMap Progress per program ID.
 * @var array<int, array{activity: \AllCoach\Domain\Activity\Activity, programId: int, programTitle: string}> $pendingActivities Up to 5 not-yet-completed activities across enrollments.
 * @var string                                   $today          Today's date (Y-m-d).
 */

use Carbon\CarbonImmutable;

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- template variables

$templateLoader = \AllCoach\Plugin::getInstance()->getContainer()->get( \AllCoach\Support\TemplateLoader::class );
$firstName      = $currentUser->first_name ?: $currentUser->display_name;
?>

<div class="ac-page-header">
	<span class="ac-page-header__icon">
		<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/></svg>
	</span>
	<h2><?php echo esc_html( $pageTitle ); ?></h2>
</div>

<p style="font-size:var(--text-base);color:var(--ac-text-muted);margin-bottom:1.25rem">
	<?php
	printf(
		/* translators: %s: user first name */
		esc_html__( 'Welcome back, %s.', 'allcoach' ),
		'<strong style="color:var(--ac-text);font-weight:var(--weight-medium)">' . esc_html( $firstName ) . '</strong>'
	);
	?>
</p>

<div class="ac-card__title"><?php esc_html_e( "Today's Appointments", 'allcoach' ); ?></div>
<div class="ac-card ac-card--padded" style="margin-bottom: calc(var(--space-2) + 2px); padding-block: 0px">
	<?php if ( ! empty( $todayBookings ) ) : ?>
		<?php foreach ( $todayBookings as $booking ) : ?>
			<?php
			$scheduledDate = CarbonImmutable::parse( $booking->getScheduledDate() );
			$startTime     = CarbonImmutable::parse( $booking->getStartTime() );
			$endTime       = CarbonImmutable::parse( $booking->getEndTime() );
			$durationMins  = ( (int) $endTime->format( 'H' ) * 60 + (int) $endTime->format( 'i' ) )
				- ( (int) $startTime->format( 'H' ) * 60 + (int) $startTime->format( 'i' ) );

			$programTitle = __( 'Session', 'allcoach' );
			foreach ( $enrollments as $enrollment ) {
				if ( $enrollment->programId === $booking->getProgramId() && $enrollment->programTitle ) {
					$programTitle = $enrollment->programTitle;
					break;
				}
			}

			$timeLabel = $startTime->format( 'g:i A' ) . ' · ' . $durationMins . ' min';
			if ( $booking->getMeetingType()->isGoogleMeet() && $booking->getMeetingUrl() ) {
				$timeLabel .= ' · ' . __( 'Google Meet', 'allcoach' );
			} elseif ( $booking->getMeetingType()->isFaceToFace() ) {
				$timeLabel .= ' · ' . __( 'In-person', 'allcoach' );
			}
			?>
			<div class="ac-appt-row ac-appt-row--dash">
				<div class="ac-appt-date ac-appt-date--today">
					<div class="ac-appt-date__month"><?php echo esc_html( strtoupper( $scheduledDate->format( 'M' ) ) ); ?></div>
					<div class="ac-appt-date__day"><?php echo esc_html( $scheduledDate->format( 'j' ) ); ?></div>
				</div>
				<div class="ac-appt-info">
					<div class="ac-appt-info__title">
						<?php echo esc_html( $programTitle ); ?>
						<span class="ac-pill ac-pill--today"><?php esc_html_e( 'TODAY', 'allcoach' ); ?></span>
					</div>
					<span class="ac-appt-info__meta"><?php echo esc_html( $timeLabel ); ?></span>
				</div>
				<?php if ( $booking->getMeetingType()->isFaceToFace() ) : ?>
					<span class="ac-appt-info__meta ac-appt-info__meta--location"><?php echo esc_html( $booking->getLocation() ?? '' ); ?></span>
				<?php elseif ( $booking->getMeetingUrl() ) : ?>
					<a href="<?php echo esc_url( $booking->getMeetingUrl() ); ?>" class="ac-btn ac-btn--primary ac-btn--sm" target="_blank" rel="noopener">
						<?php esc_html_e( 'Join call', 'allcoach' ); ?>
					</a>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	<?php else : ?>
		<?php
		$templateLoader->loadTemplate(
			'partials/empty-state.php',
			[
				'icon'  => '<path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/>',
				'title' => __( 'No appointments today', 'allcoach' ),
				'sub'   => __( 'You have nothing scheduled for today.', 'allcoach' ),
				'wrap'  => false,
			]
		);
		?>
	<?php endif; ?>
</div>

<div class="ac-card__title"><?php esc_html_e( 'Pending Activities', 'allcoach' ); ?></div>
<div class="ac-card ac-card--padded" style="margin-bottom: calc(var(--space-2) + 2px); padding:0 1.1rem">
	<?php if ( ! empty( $pendingActivities ) ) : ?>
		<?php foreach ( $pendingActivities as $item ) : ?>
			<?php
			$activity      = $item['activity'];
			$activityTitle = $activity->getSettings()['title'] ?? sprintf(
				/* translators: 1: week number, 2: day number */
				__( 'Week %1$d – Day %2$d', 'allcoach' ),
				$activity->getWeek(),
				$activity->getDay()
			);
			$metaLabel     = sprintf(
				/* translators: 1: program title, 2: week number */
				__( '%1$s · Week %2$d', 'allcoach' ),
				$item['programTitle'],
				$activity->getWeek()
			);
			$programUrl = $routerUrl( 'programs', $item['programId'] );
			?>
			<div class="ac-prog-row">
				<div class="ac-prog-row__body">
					<div class="ac-prog-row__title"><?php echo esc_html( $activityTitle ); ?></div>
					<div class="ac-prog-row__meta"><?php echo esc_html( $metaLabel ); ?></div>
				</div>
				<span class="ac-pill ac-pill--inactive"><?php esc_html_e( 'Not started', 'allcoach' ); ?></span>
				<a href="<?php echo esc_url( add_query_arg( 'activity', $activity->getId(), $programUrl ) ); ?>" class="ac-btn ac-btn--primary ac-btn--sm">
					<?php esc_html_e( 'View', 'allcoach' ); ?>
				</a>
			</div>
		<?php endforeach; ?>
	<?php else : ?>
		<?php
		$templateLoader->loadTemplate(
			'partials/empty-state.php',
			[
				'icon'  => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>',
				'title' => __( 'All caught up!', 'allcoach' ),
				'sub'   => __( 'You have no pending activities.', 'allcoach' ),
				'wrap'  => false,
			]
		);
		?>
	<?php endif; ?>
</div>

<div class="ac-card ac-card--padded">
	<div class="ac-card__title"><?php esc_html_e( 'Program Progress', 'allcoach' ); ?></div>
	<?php
	$confirmedEnrollments = array_filter( $enrollments, fn( $e ) => $e->status === 'confirmed' );
	if ( ! empty( $confirmedEnrollments ) ) :
		foreach ( $confirmedEnrollments as $enrollment ) :
			$progress  = $progressMap[ $enrollment->programId ] ?? null;
			$completed = $progress['completed'] ?? 0;
			$total     = $progress['total'] ?? 0;
			$percent   = $total > 0 ? round( ( $completed / $total ) * 100 ) : 0;
			?>
			<div class="ac-prog-progress">
				<div class="ac-prog-progress__header">
					<a href="<?php echo esc_url( $routerUrl( 'programs', $enrollment->programId ) ); ?>" class="ac-prog-progress__title">
						<?php echo esc_html( $enrollment->programTitle ?? __( 'Program', 'allcoach' ) ); ?>
					</a>
					<span class="ac-prog-progress__count">
						<?php
						printf(
							/* translators: 1: completed count, 2: total count */
							esc_html__( '%1$d/%2$d', 'allcoach' ),
							(int) $completed,
							(int) $total
						);
						?>
					</span>
				</div>
				<div class="ac-progress">
					<div class="ac-progress__fill" style="width:<?php echo esc_attr( (string) $percent ); ?>%"></div>
				</div>
			</div>
		<?php endforeach; ?>
	<?php else : ?>
		<?php
		$templateLoader->loadTemplate(
			'partials/empty-state.php',
			[
				'icon'  => '<rect width="20" height="14" x="2" y="3" rx="2"/><line x1="8" x2="16" y1="21" y2="21"/><line x1="12" x2="12" y1="17" y2="21"/>',
				'title' => __( 'No active programs', 'allcoach' ),
				'sub'   => __( 'Enroll in a program to track your progress here.', 'allcoach' ),
				'wrap'  => false,
			]
		);
		?>
	<?php endif; ?>
</div>
