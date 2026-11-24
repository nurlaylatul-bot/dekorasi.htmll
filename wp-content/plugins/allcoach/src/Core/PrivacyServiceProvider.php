<?php

declare(strict_types=1);

namespace AllCoach\Core;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\Contracts\ServiceProviderInterface;
use AllCoach\Domain\Enrollment\EnrollmentRepository;
use AllCoach\Domain\Order\OrderRepository;
use AllCoach\Infrastructure\Privacy\PersonalDataGdpr;
use AllCoach\Infrastructure\Privacy\UserDataEraser;
use AllCoach\Traits\Hooks;

/**
 * Wires GDPR cascade deletion into WordPress user removal, registers AllCoach
 * data with WordPress's core Export/Erase Personal Data tools, and warns
 * admins before they delete a user who has AllCoach data.
 */
class PrivacyServiceProvider implements ServiceProviderInterface {

	use Hooks;

	public function __construct(
		private UserDataEraser $eraser,
		private OrderRepository $orders,
		private EnrollmentRepository $enrollments,
		private PersonalDataGdpr $personalDataGdpr,
	) {}

	public function boot(): void {
		$this->addAction( 'deleted_user', [ $this, 'onUserDeleted' ] );
		$this->addAction( 'wpmu_delete_user', [ $this, 'onUserDeleted' ] );
		$this->addAction( 'delete_user_form', [ $this, 'renderDeleteWarning' ], 10, 2 );

		$this->personalDataGdpr->register();
	}

	/**
	 * Erase AllCoach data after a user is removed. Custom-table rows are not
	 * reassignable, so we always purge regardless of the reassign choice.
	 */
	public function onUserDeleted( int $userId ): void {
		$this->eraser->erase( $userId );
	}

	/**
	 * Show an irreversible-action warning on the wp-admin Delete Users screen,
	 * but only when at least one of the targeted users has AllCoach data.
	 *
	 * @param \WP_User            $currentUser The user performing the deletion.
	 * @param array<int>|int|null $userIds     The user ids being deleted.
	 */
	public function renderDeleteWarning( $currentUser, $userIds = null ): void {
		$ids = array_map( 'intval', (array) $userIds );

		$hasData = false;
		foreach ( $ids as $id ) {
			if ( $this->orders->countByUser( $id ) > 0 || $this->enrollments->countByUser( $id ) > 0 ) {
				$hasData = true;
				break;
			}
		}

		if ( ! $hasData ) {
			return;
		}

		printf(
			'<div class="notice notice-warning inline"><p><strong>%s</strong> %s</p></div>',
			esc_html__( 'AllCoach:', 'allcoach' ),
			esc_html__(
				'This will permanently delete their program enrollments, bookings, and activity data — this cannot be undone.',
				'allcoach'
			)
		);
	}
}
