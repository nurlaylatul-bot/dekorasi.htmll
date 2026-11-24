<?php
/**
 * Pagination partial.
 *
 * @var int    $currentPage Current page number (1-based).
 * @var int    $totalItems  Total number of items.
 * @var int    $perPage     Items per page.
 * @var string $baseUrl     Base URL for page links.
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- template variables

$totalPages = (int) ceil( $totalItems / max( 1, $perPage ) );

$start      = ( $currentPage - 1 ) * $perPage + 1;
$end        = min( $currentPage * $perPage, $totalItems );
$prettyUrls = (bool) get_option( 'permalink_structure' );
$pageUrl    = function ( int $p ) use ( $baseUrl, $prettyUrls ): string {
	if ( $p <= 1 ) {
		return esc_url( remove_query_arg( 'paged', $baseUrl ) );
	}
	if ( $prettyUrls ) {
		$clean = preg_replace( '#/page/\d+/?#', '/', $baseUrl );
		$parts = explode( '?', $clean, 2 );
		$url   = trailingslashit( $parts[0] ) . 'page/' . $p . '/';
		if ( ! empty( $parts[1] ) ) {
			$url .= '?' . $parts[1];
		}
		return esc_url( $url );
	}
	return esc_url( add_query_arg( 'paged', $p, $baseUrl ) );
};
$hasPrev    = $currentPage > 1;
$hasNext    = $currentPage < $totalPages;
?>
<nav class="ac-pagination" aria-label="<?php esc_attr_e( 'Pagination', 'allcoach' ); ?>">
	<span class="ac-pagination__info">
		<?php
		printf(
			/* translators: 1: start, 2: end, 3: total */
			esc_html__( 'Showing %1$s–%2$s of %3$s', 'allcoach' ),
			esc_html( (string) $start ),
			esc_html( (string) $end ),
			esc_html( (string) $totalItems )
		);
		?>
	</span>
	<div class="ac-pagination__buttons">
		<?php if ( $hasPrev ) : ?>
			<a class="ac-pagination__btn" href="<?php echo esc_url( $pageUrl( $currentPage - 1 ) ); ?>" aria-label="<?php esc_attr_e( 'Previous page', 'allcoach' ); ?>">&#8249;</a>
		<?php else : ?>
			<span class="ac-pagination__btn is-disabled" aria-disabled="true">&#8249;</span>
		<?php endif; ?>

		<?php for ( $i = 1; $i <= $totalPages; $i++ ) : ?>
			<?php if ( $i === $currentPage ) : ?>
				<span class="ac-pagination__btn is-active"><?php echo esc_html( (string) $i ); ?></span>
			<?php else : ?>
				<a class="ac-pagination__btn" href="<?php echo esc_url( $pageUrl( $i ) ); ?>"><?php echo esc_html( (string) $i ); ?></a>
			<?php endif; ?>
		<?php endfor; ?>

		<?php if ( $hasNext ) : ?>
			<a class="ac-pagination__btn" href="<?php echo esc_url( $pageUrl( $currentPage + 1 ) ); ?>" aria-label="<?php esc_attr_e( 'Next page', 'allcoach' ); ?>">&#8250;</a>
		<?php else : ?>
			<span class="ac-pagination__btn is-disabled" aria-disabled="true">&#8250;</span>
		<?php endif; ?>
	</div>
</nav>
