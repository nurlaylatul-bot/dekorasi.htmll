<?php
/**
 * Single activity view template.
 *
 * @var string                                        $pageTitle        Program title (used for back label).
 * @var callable                                      $routerUrl        URL generator.
 * @var \AllCoach\Domain\Program\Program              $program          The program.
 * @var \AllCoach\Domain\Activity\Activity             $activity         The activity.
 * @var \AllCoach\Application\Order\OrderView|null    $enrollment       User enrollment (null if not enrolled).
 * @var bool                                          $isActive         Whether the enrollment's order is confirmed.
 * @var bool                                          $isCompleted      Whether this activity is completed.
 * @var array{completed: int, total: int, percentage: float, completed_activity_ids: int[]}|null $activityProgress Activity progress.
 * @var \AllCoach\Domain\Activity\Activity|null        $prevActivity     Previous activity in sequence, or null.
 * @var \AllCoach\Domain\Activity\Activity|null        $nextActivity     Next activity in sequence, or null.
 * @var \AllCoach\Domain\Activity\Activity[]           $allActivities    All activities in this program.
 * @var array                                         $responseData     Submitted field values, keyed by field ID.
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound, WordPress.WP.GlobalVariablesOverride.Prohibited -- template variables

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
$actCompleted = $activityProgress['completed'] ?? 0;
$actTotal     = $activityProgress['total'] ?? count( $allActivities );
$actPercent   = $actTotal > 0 ? round( ( $actCompleted / $actTotal ) * 100 ) : 0;

$programUrl = $routerUrl( 'programs', $program->getId() );
$backUrl    = add_query_arg( 'tab', 'activities', $programUrl );

$prevUrl = $prevActivity ? add_query_arg( 'activity', $prevActivity->getId(), $programUrl ) : null;
$nextUrl = $nextActivity ? add_query_arg( 'activity', $nextActivity->getId(), $programUrl ) : null;

$prevTitle = null;
$nextTitle = null;
if ( $prevActivity ) {
	$prevTitle = $prevActivity->getSettings()['title'] ?? sprintf(
		/* translators: %1$d: week number, %2$d: day number */
		__( 'Week %1$d – Day %2$d', 'allcoach' ),
		$prevActivity->getWeek(),
		$prevActivity->getDay()
	);
}
if ( $nextActivity ) {
	$nextTitle = $nextActivity->getSettings()['title'] ?? sprintf(
		/* translators: %1$d: week number, %2$d: day number */
		__( 'Week %1$d – Day %2$d', 'allcoach' ),
		$nextActivity->getWeek(),
		$nextActivity->getDay()
	);
}

$schema         = $activity->getSchema();
$hasInputFields = $schema->hasInteractiveFields();
$canSubmit      = $isActive && ! $isCompleted && $hasInputFields;

$activityBase = 'allcoach/v1/programs/' . $program->getId() . '/activities/' . $activity->getId();
$enrollmentId = $enrollment ? $enrollment->enrollmentId : 0;
?>

<a href="<?php echo esc_url( $backUrl ); ?>" class="ac-back">
	<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5"/><path d="M12 5l-7 7 7 7"/></svg>
	<?php esc_html_e( 'Back to activities', 'allcoach' ); ?>
</a>

<div class="ac-page-header" style="align-items:flex-start">
	<div style="flex:1;min-width:0">
		<h2 style="margin-bottom:3px"><?php echo esc_html( $activityTitle ); ?></h2>
		<p style="font-size:var(--text-sm);color:var(--ac-text-muted);font-weight:400;margin-bottom:var(--space-2)">
			<?php echo esc_html( $program->getTitle() ); ?> &middot; <?php echo esc_html( $weekLabel ); ?>
		</p>
		<div style="display:flex;align-items:center;gap:var(--space-2)">
			<div class="ac-progress" style="width:180px;margin:0">
				<div class="ac-progress__fill" id="ac-act-prog-bar" style="width:<?php echo esc_attr( (string) $actPercent ); ?>%"></div>
			</div>
			<span id="ac-act-prog-text" style="font-size:var(--text-xs);color:var(--ac-text-muted);white-space:nowrap">
				<?php
				printf(
					/* translators: 1: completed count, 2: total count */
					esc_html__( '%1$d of %2$d completed', 'allcoach' ),
					(int) $actCompleted,
					(int) $actTotal
				);
				?>
			</span>
		</div>
	</div>

	<div class="ac-status-wrap">
		<span
			id="ac-act-status-pill"
			class="ac-pill <?php echo $isCompleted ? 'ac-pill--completed' : 'ac-pill--inactive'; ?>"
			style="cursor:pointer;user-select:none"
			role="button"
			aria-haspopup="true"
			aria-expanded="false"
		>
			<?php echo $isCompleted ? esc_html__( 'Completed', 'allcoach' ) : esc_html__( 'Not started', 'allcoach' ); ?>
			<svg width="9" height="9" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" style="margin-inline-start:3px"><path d="M6 9l6 6 6-6"/></svg>
		</span>
		<div class="ac-status-menu" id="ac-status-menu" role="menu">
			<button type="button" class="ac-status-menu__item" data-status="not_started" role="menuitem">
				<span class="ac-status-menu__dot" style="background:var(--ac-border)"></span>
				<?php esc_html_e( 'Not started', 'allcoach' ); ?>
			</button>
			<button type="button" class="ac-status-menu__item" data-status="completed" role="menuitem">
				<span class="ac-status-menu__dot" style="background:#166534"></span>
				<?php esc_html_e( 'Completed', 'allcoach' ); ?>
			</button>
		</div>
	</div>
</div>

<div class="" id="ac-act-card">
<?php if ( ! $isActive ) : ?>
	<?php
	$templateLoader = \AllCoach\Plugin::getInstance()->getContainer()->get( \AllCoach\Support\TemplateLoader::class );
	$templateLoader->loadTemplate(
		'partials/empty-state.php',
		[
			'icon'  => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
			'title' => __( 'Awaiting confirmation', 'allcoach' ),
			'sub'   => __( 'Your order for this program is awaiting payment confirmation. This activity will unlock once it’s confirmed.', 'allcoach' ),
		]
	);
	?>
<?php else : ?>
	<form id="ac-act-form" novalidate>

		<?php if ( $schema->isEmpty() ) : ?>
			<p style="font-size:var(--text-base);color:var(--ac-text-muted);padding:var(--space-2) 0">
				<?php esc_html_e( 'No content yet.', 'allcoach' ); ?>
			</p>
		<?php else : ?>
			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- renderer handles all escaping
			echo \AllCoach\Frontend\ActivityContentRenderer::make( $responseData ?? [], $isCompleted )
				->render( $schema->getContent() );
			?>
		<?php endif; ?>

		<?php if ( $canSubmit ) : ?>
			<div class="ac-act-footer" id="ac-act-footer">
				<button type="submit" class="ac-btn ac-btn--primary ac-btn--sm" id="ac-act-submit">
					<?php esc_html_e( 'Submit', 'allcoach' ); ?>
				</button>
				<span id="ac-act-error" style="display:none;margin-inline-start:var(--space-3);font-size:var(--text-sm);color:var(--ac-error)"></span>
			</div>
		<?php endif; ?>

	</form>
<?php endif; ?>
</div>

<?php if ( $isActive ) : ?>
	<?php
	/**
	 * Fires after the activity content and before the previous/next navigation.
	 *
	 * @param \AllCoach\Domain\Activity\Activity     $activity   The activity.
	 * @param \AllCoach\Application\Order\OrderView  $enrollment The client's enrollment/order view.
	 */
	do_action( 'allcoach:activity:before_nav', $activity, $enrollment );
	?>
<?php endif; ?>

<div class="ac-act-nav">
	<?php if ( $prevUrl ) : ?>
		<div class="ac-act-nav__btn ac-act-nav__btn--prev">
			<a href="<?php echo esc_url( $prevUrl ); ?>" class="ac-btn ac-btn--ghost ac-btn--sm">
				<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg>
				<?php esc_html_e( 'Previous', 'allcoach' ); ?>
			</a>
			<?php if ( $prevTitle ) : ?>
				<span class="ac-act-nav__tooltip"><?php echo esc_html( $prevTitle ); ?></span>
			<?php endif; ?>
		</div>
	<?php else : ?>
		<span></span>
	<?php endif; ?>

	<?php if ( $nextUrl ) : ?>
		<div class="ac-act-nav__btn ac-act-nav__btn--next">
			<a href="<?php echo esc_url( $nextUrl ); ?>" class="ac-btn ac-btn--ghost ac-btn--sm">
				<?php esc_html_e( 'Next', 'allcoach' ); ?>
				<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg>
			</a>
			<?php if ( $nextTitle ) : ?>
				<span class="ac-act-nav__tooltip"><?php echo esc_html( $nextTitle ); ?></span>
			<?php endif; ?>
		</div>
	<?php else : ?>
		<span></span>
	<?php endif; ?>
</div>

<?php if ( $isActive ) : ?>
	<?php
	wp_print_inline_script_tag(
		'var __ALLCOACH_ACTIVITY__ = ' . wp_json_encode(
			[
				'baseEndpoint' => $activityBase,
				'enrollId'     => $enrollmentId,
				'totalActs'    => (int) $actTotal,
				'completedN'   => (int) $actCompleted,
				'canSubmit'    => $canSubmit,
				'i18n'         => [
					'completed'          => __( 'Completed', 'allcoach' ),
					'submitting'         => __( 'Submitting…', 'allcoach' ),
					'submit'             => __( 'Submit', 'allcoach' ),
					'uploadFailed'       => __( 'File upload failed.', 'allcoach' ),
					'somethingWentWrong' => __( 'Something went wrong.', 'allcoach' ),
					'of'                 => __( 'of', 'allcoach' ),
					'completedLabel'     => __( 'completed', 'allcoach' ),
					'required'           => __( 'This field is required.', 'allcoach' ),
					'invalidFileType'    => __( 'Invalid file type.', 'allcoach' ),
				],
			]
		)
	);
	?>
<?php endif; ?>
