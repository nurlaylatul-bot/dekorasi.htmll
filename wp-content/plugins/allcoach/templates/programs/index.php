<?php
/**
 * Programs listing page template.
 *
 * @var string                                  $pageTitle    The page title.
 * @var callable                                $routerUrl    URL generator.
 * @var \AllCoach\Application\Order\OrderView[] $enrollments  User enrollments.
 * @var array<int, array{scheduled: int, completed: int, total: int, remaining: int}> $progressMap Progress per program ID.
 * @var int                                     $currentPage  Current page number.
 * @var int                                     $totalItems   Total enrollment count.
 * @var int                                     $perPage      Items per page.
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound, WordPress.WP.GlobalVariablesOverride.Prohibited -- template variables

$templateLoader = \AllCoach\Plugin::getInstance()->getContainer()->get( \AllCoach\Support\TemplateLoader::class );
?>

<div class="ac-page-header">
	<span class="ac-page-header__icon">
		<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="14" x="2" y="3" rx="2"/><line x1="8" x2="16" y1="21" y2="21"/><line x1="12" x2="12" y1="17" y2="21"/></svg>
	</span>
	<h2><?php echo esc_html( $pageTitle ); ?></h2>
</div>

<?php if ( empty( $enrollments ) ) : ?>
	<?php
	$templateLoader->loadTemplate(
		'partials/empty-state.php',
		[
			'icon'  => '<rect width="20" height="14" x="2" y="3" rx="2"/><line x1="8" x2="16" y1="21" y2="21"/><line x1="12" x2="12" y1="17" y2="21"/>',
			'title' => __( 'No programs yet', 'allcoach' ),
			'sub'   => __( 'Your coach will enroll you in a program to get started.', 'allcoach' ),
		]
	);
	?>
<?php else : ?>
	<div class="ac-card ac-card--padded" style="padding:0 1.1rem">
		<?php foreach ( $enrollments as $enrollment ) : ?>
			<?php
			$status      = $enrollment->status;
			$isActive    = $status === 'confirmed';
			$isPending   = $status === 'pending';
			$isCancelled = $status === 'cancelled';
			$progress    = $progressMap[ $enrollment->programId ] ?? null;
			$completed = $progress['completed'] ?? 0;
			$total     = $progress['total'] ?? 0;
			?>
			<div class="ac-prog-row">
				<div class="ac-prog-row__body">
					<div class="ac-prog-row__title <?php echo ! $isActive ? 'ac-prog-row__title--muted' : ''; ?>">
						<?php echo esc_html( $enrollment->programTitle ?? __( 'Program', 'allcoach' ) ); ?>
					</div>
					<div class="ac-prog-row__meta">
						<?php if ( $isPending ) : ?>
							<?php esc_html_e( 'Awaiting payment confirmation', 'allcoach' ); ?>
						<?php elseif ( $isCancelled ) : ?>
							<?php esc_html_e( 'Order cancelled', 'allcoach' ); ?>
						<?php else : ?>
							<?php
							printf(
								/* translators: 1: completed count, 2: total count */
								esc_html__( '%1$d of %2$d completed', 'allcoach' ),
								(int) $completed,
								(int) $total
							);
							?>
						<?php endif; ?>
					</div>
				</div>
				<?php if ( $isPending ) : ?>
					<span class="ac-pill ac-pill--warning"><?php esc_html_e( 'Pending', 'allcoach' ); ?></span>
				<?php elseif ( $isCancelled ) : ?>
					<span class="ac-pill ac-pill--cancelled"><?php esc_html_e( 'Cancelled', 'allcoach' ); ?></span>
				<?php else : ?>
					<span class="ac-pill ac-pill--active"><?php esc_html_e( 'Active', 'allcoach' ); ?></span>
				<?php endif; ?>
				<a href="<?php echo esc_url( $routerUrl( 'programs', $enrollment->programId ) ); ?>" class="ac-btn ac-btn--ghost ac-btn--sm">
					<?php esc_html_e( 'View', 'allcoach' ); ?>
				</a>
			</div>
		<?php endforeach; ?>
	</div>

	<?php
	$templateLoader->loadTemplate(
		'partials/pagination.php',
		[
			'currentPage' => $currentPage,
			'totalItems'  => $totalItems,
			'perPage'     => $perPage,
			'baseUrl'     => $routerUrl( 'programs' ),
		]
	);
	?>
<?php endif; ?>
