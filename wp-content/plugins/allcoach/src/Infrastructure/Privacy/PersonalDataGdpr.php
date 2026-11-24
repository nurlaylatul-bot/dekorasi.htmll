<?php

declare(strict_types=1);

namespace AllCoach\Infrastructure\Privacy;

defined( 'ABSPATH' ) || exit;

use AllCoach\Domain\Activity\ActivityCompletionRepository;
use AllCoach\Domain\Booking\SessionBookingFilter;
use AllCoach\Domain\Booking\SessionBookingRepository;
use AllCoach\Domain\Enrollment\EnrollmentRepository;
use AllCoach\Domain\Order\OrderItemRepository;
use AllCoach\Domain\Order\OrderRepository;
use AllCoach\Domain\Program\ProgramRepository;
use AllCoach\Infrastructure\Database\ConsentRepository;

/**
 * WordPress personal-data exporter + eraser for orders, enrollments, session
 * bookings, activity completions, consent records and client profile meta.
 */
class PersonalDataGdpr {

	public function __construct(
		private readonly OrderRepository $orders,
		private readonly OrderItemRepository $orderItems,
		private readonly EnrollmentRepository $enrollments,
		private readonly SessionBookingRepository $bookings,
		private readonly ActivityCompletionRepository $activityCompletions,
		private readonly ConsentRepository $consents,
		private readonly ProgramRepository $programs,
		private readonly UserDataEraser $eraser,
	) {}

	public function register(): void {
		add_filter( 'wp_privacy_personal_data_exporters', [ $this, 'registerExporters' ] );
		add_filter( 'wp_privacy_personal_data_erasers', [ $this, 'registerEraser' ] );
	}

	/**
	 * @param array<string, mixed> $exporters
	 * @return array<string, mixed>
	 */
	public function registerExporters( array $exporters ): array {
		$exporters['allcoach-orders']               = [
			'exporter_friendly_name' => __( 'AllCoach Orders', 'allcoach' ),
			'callback'               => [ $this, 'exportOrders' ],
		];
		$exporters['allcoach-enrollments']           = [
			'exporter_friendly_name' => __( 'AllCoach Enrollments', 'allcoach' ),
			'callback'               => [ $this, 'exportEnrollments' ],
		];
		$exporters['allcoach-bookings']              = [
			'exporter_friendly_name' => __( 'AllCoach Appointments', 'allcoach' ),
			'callback'               => [ $this, 'exportBookings' ],
		];
		$exporters['allcoach-activity-completions']  = [
			'exporter_friendly_name' => __( 'AllCoach Activity Responses', 'allcoach' ),
			'callback'               => [ $this, 'exportActivityCompletions' ],
		];
		$exporters['allcoach-consents']              = [
			'exporter_friendly_name' => __( 'AllCoach Consent Records', 'allcoach' ),
			'callback'               => [ $this, 'exportConsents' ],
		];
		$exporters['allcoach-client-profile']        = [
			'exporter_friendly_name' => __( 'AllCoach Client Profile', 'allcoach' ),
			'callback'               => [ $this, 'exportClientProfile' ],
		];
		return $exporters;
	}

	/**
	 * @param array<string, mixed> $erasers
	 * @return array<string, mixed>
	 */
	public function registerEraser( array $erasers ): array {
		$erasers['allcoach-data'] = [
			'eraser_friendly_name' => __( 'AllCoach Data', 'allcoach' ),
			'callback'             => [ $this, 'erase' ],
		];
		return $erasers;
	}

	/** @return array{data: array<int, array<string, mixed>>, done: bool} */
	public function exportOrders( string $email, int $page = 1 ): array {
		$userId = $this->userIdFor( $email );
		if ( ! $userId ) {
			return $this->emptyExport();
		}

		$export = [];
		foreach ( $this->orders->findByUser( $userId, 1000, 0 ) as $order ) {
			$programTitles = array_filter( array_map(
				fn( $item ) => $this->programs->findById( $item->getProgramId() )?->getTitle(),
				$this->orderItems->findByOrderId( $order->getId() )
			) );

			$export[] = [
				'group_id'    => 'allcoach-orders',
				'group_label' => __( 'AllCoach Orders', 'allcoach' ),
				'item_id'     => 'allcoach-order-' . $order->getId(),
				'data'        => [
					[ 'name' => __( 'Program', 'allcoach' ), 'value' => implode( ', ', $programTitles ) ],
					[ 'name' => __( 'Status', 'allcoach' ), 'value' => $order->getStatus()->getValue() ],
					[ 'name' => __( 'Payment Method', 'allcoach' ), 'value' => $order->getPaymentMethod() ],
					[ 'name' => __( 'Total', 'allcoach' ), 'value' => $order->getCurrency() . ' ' . number_format( $order->getTotal(), 2 ) ],
					[ 'name' => __( 'Notes', 'allcoach' ), 'value' => (string) $order->getNotes() ],
					[ 'name' => __( 'Created', 'allcoach' ), 'value' => $order->getCreatedAt()->format( 'Y-m-d H:i:s' ) ],
				],
			];
		}

		return [ 'data' => $export, 'done' => true ];
	}

	/** @return array{data: array<int, array<string, mixed>>, done: bool} */
	public function exportEnrollments( string $email, int $page = 1 ): array {
		$userId = $this->userIdFor( $email );
		if ( ! $userId ) {
			return $this->emptyExport();
		}

		$export = [];
		foreach ( $this->enrollments->findByUser( $userId, 1000, 0 ) as $enrollment ) {
			$program = $this->programs->findById( $enrollment->getProgramId() );

			$export[] = [
				'group_id'    => 'allcoach-enrollments',
				'group_label' => __( 'AllCoach Enrollments', 'allcoach' ),
				'item_id'     => 'allcoach-enrollment-' . $enrollment->getId(),
				'data'        => [
					[ 'name' => __( 'Program', 'allcoach' ), 'value' => $program?->getTitle() ?? '' ],
					[ 'name' => __( 'Status', 'allcoach' ), 'value' => $enrollment->getStatus()->getValue() ],
					[ 'name' => __( 'Enrolled', 'allcoach' ), 'value' => $enrollment->getEnrolledAt()->format( 'Y-m-d H:i:s' ) ],
					[ 'name' => __( 'Expires', 'allcoach' ), 'value' => $enrollment->getExpiresAt()?->format( 'Y-m-d H:i:s' ) ?? '' ],
				],
			];
		}

		return [ 'data' => $export, 'done' => true ];
	}

	/** @return array{data: array<int, array<string, mixed>>, done: bool} */
	public function exportBookings( string $email, int $page = 1 ): array {
		$userId = $this->userIdFor( $email );
		if ( ! $userId ) {
			return $this->emptyExport();
		}

		$asClient = $this->bookings->findWithFilter( new SessionBookingFilter( clientId: $userId ), 1000, 0 );
		$asCoach  = $this->bookings->findWithFilter( new SessionBookingFilter( coachId: $userId ), 1000, 0 );

		$merged = [];
		foreach ( array_merge( $asClient, $asCoach ) as $booking ) {
			$merged[ $booking->getId() ] = $booking;
		}

		$export = [];
		foreach ( $merged as $booking ) {
			$program = $this->programs->findById( $booking->getProgramId() );
			$role    = $booking->getClientId() === $userId ? __( 'Client', 'allcoach' ) : __( 'Coach', 'allcoach' );

			$export[] = [
				'group_id'    => 'allcoach-bookings',
				'group_label' => __( 'AllCoach Appointments', 'allcoach' ),
				'item_id'     => 'allcoach-booking-' . $booking->getId(),
				'data'        => [
					[ 'name' => __( 'Role', 'allcoach' ), 'value' => $role ],
					[ 'name' => __( 'Program', 'allcoach' ), 'value' => $program?->getTitle() ?? '' ],
					[ 'name' => __( 'Date', 'allcoach' ), 'value' => $booking->getScheduledDate() . ' ' . $booking->getStartTime() . '-' . $booking->getEndTime() ],
					[ 'name' => __( 'Status', 'allcoach' ), 'value' => $booking->getStatus()->getValue() ],
					[ 'name' => __( 'Meeting Type', 'allcoach' ), 'value' => $booking->getMeetingType()->getValue() ],
					[ 'name' => __( 'Location', 'allcoach' ), 'value' => (string) $booking->getLocation() ],
					[ 'name' => __( 'Notes', 'allcoach' ), 'value' => (string) $booking->getNotes() ],
				],
			];
		}

		return [ 'data' => $export, 'done' => true ];
	}

	/** @return array{data: array<int, array<string, mixed>>, done: bool} */
	public function exportActivityCompletions( string $email, int $page = 1 ): array {
		$userId = $this->userIdFor( $email );
		if ( ! $userId ) {
			return $this->emptyExport();
		}

		$export = [];
		foreach ( $this->activityCompletions->findByClient( $userId, 1000, 0 ) as $completion ) {
			$program = $this->programs->findById( $completion->getProgramId() );

			$export[] = [
				'group_id'    => 'allcoach-activity-completions',
				'group_label' => __( 'AllCoach Activity Responses', 'allcoach' ),
				'item_id'     => 'allcoach-activity-completion-' . $completion->getId(),
				'data'        => [
					[ 'name' => __( 'Program', 'allcoach' ), 'value' => $program?->getTitle() ?? '' ],
					[ 'name' => __( 'Responses', 'allcoach' ), 'value' => (string) wp_json_encode( $completion->getResponseData() ) ],
					[ 'name' => __( 'Completed', 'allcoach' ), 'value' => $completion->getCompletedAt()->format( 'Y-m-d H:i:s' ) ],
				],
			];
		}

		return [ 'data' => $export, 'done' => true ];
	}

	/** @return array{data: array<int, array<string, mixed>>, done: bool} */
	public function exportConsents( string $email, int $page = 1 ): array {
		$userId = $this->userIdFor( $email );
		if ( ! $userId ) {
			return $this->emptyExport();
		}

		$export = [];
		foreach ( $this->consents->findByUser( $userId ) as $row ) {
			$export[] = [
				'group_id'    => 'allcoach-consents',
				'group_label' => __( 'AllCoach Consent Records', 'allcoach' ),
				'item_id'     => 'allcoach-consent-' . $row['id'],
				'data'        => [
					[ 'name' => __( 'Consent Type', 'allcoach' ), 'value' => $row['consent_type'] ],
					[ 'name' => __( 'Policy Version', 'allcoach' ), 'value' => $row['policy_version'] ],
					[ 'name' => __( 'Consented At', 'allcoach' ), 'value' => $row['consented_at'] ],
					[ 'name' => __( 'IP Address', 'allcoach' ), 'value' => (string) ( $row['ip_address'] ?? '' ) ],
				],
			];
		}

		return [ 'data' => $export, 'done' => true ];
	}

	/** @return array{data: array<int, array<string, mixed>>, done: bool} */
	public function exportClientProfile( string $email, int $page = 1 ): array {
		$userId = $this->userIdFor( $email );
		if ( ! $userId ) {
			return $this->emptyExport();
		}

		$phone    = get_user_meta( $userId, '_allcoach_client_phone', true );
		$notes    = get_user_meta( $userId, '_allcoach_client_notes', true );
		$timezone = get_user_meta( $userId, '_allcoach_client_timezone', true );
		$status   = get_user_meta( $userId, '_allcoach_client_status', true );

		if ( $phone === '' && $notes === '' && $timezone === '' && $status === '' ) {
			return $this->emptyExport();
		}

		return [
			'data' => [
				[
					'group_id'    => 'allcoach-client-profile',
					'group_label' => __( 'AllCoach Client Profile', 'allcoach' ),
					'item_id'     => 'allcoach-client-profile-' . $userId,
					'data'        => [
						[ 'name' => __( 'Phone', 'allcoach' ), 'value' => (string) $phone ],
						[ 'name' => __( 'Notes', 'allcoach' ), 'value' => (string) $notes ],
						[ 'name' => __( 'Timezone', 'allcoach' ), 'value' => (string) $timezone ],
						[ 'name' => __( 'Status', 'allcoach' ), 'value' => (string) $status ],
					],
				],
			],
			'done' => true,
		];
	}

	/** @return array{items_removed: bool|int, items_retained: bool, messages: array<string>, done: bool} */
	public function erase( string $email, int $page = 1 ): array {
		$userId = $this->userIdFor( $email );

		$removed = 0;
		if ( $userId ) {
			$removed = $this->eraser->erase( $userId );
		}

		return [
			'items_removed'  => $removed > 0 ? $removed : false,
			'items_retained' => false,
			'messages'       => [],
			'done'           => true,
		];
	}

	private function userIdFor( string $email ): ?int {
		$user = get_user_by( 'email', $email );
		return $user ? (int) $user->ID : null;
	}

	/** @return array{data: array<int, array<string, mixed>>, done: bool} */
	private function emptyExport(): array {
		return [ 'data' => [], 'done' => true ];
	}
}
