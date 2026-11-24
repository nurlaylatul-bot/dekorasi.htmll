<?php

declare(strict_types=1);

namespace AllCoach\API\Controllers\V1;

defined( 'ABSPATH' ) || exit;

use AllCoach\API\BulkActionsTrait;
use AllCoach\API\RestController;
use AllCoach\Application\Booking\BookSessionService;
use AllCoach\Application\Booking\CancelSessionBookingService;
use AllCoach\Application\Booking\CompleteSessionService;
use AllCoach\Application\Booking\SessionBookingDTO;
use AllCoach\Domain\Booking\MeetingType;
use AllCoach\Domain\Booking\SessionBooking;
use AllCoach\Domain\Booking\SessionBookingRepository;
use AllCoach\Domain\Booking\SessionBookingStatus;
use AllCoach\Domain\Booking\SessionBookingFilter;
use AllCoach\Domain\Client\ClientRepository;
use AllCoach\Domain\Coach\CoachRepository;
use AllCoach\Domain\Program\ProgramRepository;

class SessionBookingsController extends RestController {

	use BulkActionsTrait;

	protected string $restBase = 'session-bookings';

	public function __construct(
		private readonly SessionBookingRepository $repository,
		private readonly BookSessionService $bookService,
		private readonly CompleteSessionService $completeService,
		private readonly CancelSessionBookingService $cancelService,
		private readonly ClientRepository $clientRepository,
		private readonly CoachRepository $coachRepository,
		private readonly ProgramRepository $programRepository
	) {}

	public function registerRoutes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->restBase,
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'getSessionBookings' ],
					'permission_callback' => fn() => $this->permCoachOrAdmin(),
					'args'                => array_merge(
						$this->paginationArgs(),
						[
							'enrollment_id' => $this->argInteger( description: __( 'Filter by enrollment ID.', 'allcoach' ), min: 1 ),
							'coach_id'      => $this->argInteger( description: __( 'Filter by coach ID.', 'allcoach' ), min: 1 ),
							'client_id'     => $this->argInteger( description: __( 'Filter by client ID.', 'allcoach' ), min: 1 ),
							'from_date'     => $this->argString( description: __( 'Filter from date (YYYY-MM-DD).', 'allcoach' ) ),
							'to_date'       => $this->argString( description: __( 'Filter to date (YYYY-MM-DD).', 'allcoach' ) ),
							'status'        => $this->argString( description: __( 'Filter by status.', 'allcoach' ) ),
							'search'        => $this->argString( description: __( 'Search client or program', 'allcoach' ) ),
						]
					),
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'bookSession' ],
					'permission_callback' => fn() => $this->permCoachOrAdmin(),
					'args'                => $this->createArgs(),
				],
				'schema' => [ $this, 'getItemSchema' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->restBase . '/(?P<id>\d+)',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'getSessionBooking' ],
					'permission_callback' => fn() => $this->permCoachOrAdmin(),
					'args'                => $this->idArg(),
				],
				'schema' => [ $this, 'getItemSchema' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->restBase . '/(?P<id>\d+)/complete',
			[
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'completeSession' ],
					'permission_callback' => fn() => $this->permCoachOrAdmin(),
					'args'                => $this->idArg(),
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->restBase . '/(?P<id>\d+)/cancel',
			[
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'cancelSession' ],
					'permission_callback' => fn() => $this->permCoachOrAdmin(),
					'args'                => $this->idArg(),
				],
			]
		);

		$this->registerBulkRoute();
	}

	protected function supportedBulkActions(): array {
		return [ 'complete', 'cancel' ];
	}

	protected function bulkPermissionCallback(): bool {
		return $this->permCoachOrAdmin();
	}

	protected function executeBulkAction( string $action, int $id ): array {
		try {
			match ( $action ) {
				'complete' => $this->completeService->execute( $id ),
				'cancel'   => $this->cancelService->execute( $id ),
			};

			return [
				'id'      => $id,
				'success' => true,
			];
		} catch ( \Throwable $e ) {
			return [
				'id'      => $id,
				'success' => false,
				'error'   => $e->getMessage(),
			];
		}
	}

	public function getSessionBookings( \WP_REST_Request $request ): \WP_REST_Response {
		$enrollmentId = $request->get_param( 'enrollment_id' ) ? (int) $request->get_param( 'enrollment_id' ) : null;
		$coachId      = $request->get_param( 'coach_id' ) ? (int) $request->get_param( 'coach_id' ) : null;
		$clientId     = $request->get_param( 'client_id' ) ? (int) $request->get_param( 'client_id' ) : null;
		$fromDate     = $request->get_param( 'from_date' ) ?: null;
		$toDate       = $request->get_param( 'to_date' ) ?: null;
		$page         = max( 1, (int) ( $request->get_param( 'page' ) ?? 1 ) );
		$perPage      = min( 100, max( 1, (int) ( $request->get_param( 'per_page' ) ?? 20 ) ) );
		$offset       = ( $page - 1 ) * $perPage;
		$status       = $request->get_param( 'status' ) ?: null;
		$search       = sanitize_text_field( (string) $request->get_param( 'search' ) );

		$filter   = $this->buildFilter( $enrollmentId, $coachId, $clientId, $fromDate, $toDate, $search, $status );
		$sessions = $this->repository->findWithFilter( $filter, $perPage, $offset );
		$total    = $this->repository->countWithFilter( $filter );

		$items = array_map( fn( SessionBooking $sb ) => $this->prepareItem( $sb ), $sessions );

		return $this->successResponse(
			[
				'session_bookings' => $items,
				'total'            => $total,
				'page'             => $page,
				'per_page'         => $perPage,
			]
		);
	}

	private function buildFilter(
		?int $enrollmentId,
		?int $coachId,
		?int $clientId,
		?string $fromDate,
		?string $toDate,
		?string $search,
		?string $status
	): SessionBookingFilter {
		$search           = empty( $search ) ? null : $search;
		$excludeCancelled = $status === null && ( $fromDate !== null || $toDate !== null );

		// Default: scope to current coach unless admin.
		if ( $enrollmentId === null && $coachId === null && $clientId === null && ! $this->isAdmin() ) {
			$coachId = get_current_user_id();
		}

		return new SessionBookingFilter(
			enrollmentId:     $enrollmentId,
			clientId:         $clientId,
			coachId:          $coachId,
			fromDate:         $fromDate,
			toDate:           $toDate,
			status:           $status,
			excludeCancelled: $excludeCancelled,
			excludeDemo:      true,
			search:           $search,
		);
	}


	public function getSessionBooking( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$id = (int) $request->get_param( 'id' );
		$sb = $this->repository->findById( $id );

		if ( ! $sb ) {
			return $this->notFoundResponse( __( 'Session booking', 'allcoach' ) );
		}

		return $this->successResponse( $this->prepareItem( $sb ) );
	}

	public function bookSession( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$data = $request->get_params();

		$validation = $this->validateRequired( $data, [ 'enrollment_id', 'scheduled_date', 'start_time', 'end_time' ] );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		try {
			$dto = SessionBookingDTO::fromArray( $data );
			$sb  = $this->bookService->execute( $dto );

			return $this->successResponse( $this->prepareItem( $sb ), 201 );
		} catch ( \Throwable $e ) {
			return $this->exceptionToResponse( $e );
		}
	}

	public function completeSession( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$id = (int) $request->get_param( 'id' );

		try {
			$sb = $this->completeService->execute( $id );
			return $this->successResponse( $this->prepareItem( $sb ) );
		} catch ( \Throwable $e ) {
			return $this->exceptionToResponse( $e );
		}
	}

	public function cancelSession( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$id = (int) $request->get_param( 'id' );

		try {
			$sb = $this->cancelService->execute( $id );

			return $this->successResponse( $this->prepareItem( $sb ) );
		} catch ( \Throwable $e ) {
			return $this->exceptionToResponse( $e );
		}
	}

	private function prepareItem( SessionBooking $sb ): array {
		return [
			'id'             => $sb->getId(),
			'enrollment_id'  => $sb->getEnrollmentId(),
			'scheduled_date' => $sb->getScheduledDate(),
			'start_time'     => $sb->getStartTime(),
			'end_time'       => $sb->getEndTime(),
			'session_number' => $sb->getSessionNumber(),
			'status'         => $sb->getStatus()->getValue(),
			'notes'          => $sb->getNotes(),
			'timezone'       => $sb->getTimezone(),
			'meeting_url'    => $sb->getMeetingUrl(),
			'meeting_type'   => $sb->getMeetingType()->getValue(),
			'location'       => $sb->getLocation(),
			'created_at'     => $sb->getCreatedAt()->format( 'c' ),
			'updated_at'     => $sb->getUpdatedAt()?->format( 'c' ),
			'client'         => $this->clientRepository->findById( $sb->getClientId() )?->toArray(),
			'coach'          => $this->coachRepository->findById( $sb->getCoachId() )?->toArray(),
			'program'        => $this->programRepository->findById( $sb->getProgramId() )?->toArray(),
		];
	}

	private function createArgs(): array {
		return [
			'enrollment_id'  => $this->argInteger(
				description: __( 'Enrollment to schedule a session for.', 'allcoach' ),
				required: true,
				min: 1,
			),
			'scheduled_date' => $this->argString(
				description: __( 'Session date (YYYY-MM-DD).', 'allcoach' ),
				required: true,
			),
			'start_time'     => $this->argString(
				description: __( 'Session start time (HH:MM, 24-hour).', 'allcoach' ),
				required: true,
			),
			'end_time'       => $this->argString(
				description: __( 'Session end time (HH:MM, 24-hour).', 'allcoach' ),
				required: true,
			),
			'notes'          => $this->argString(
				description: __( 'Notes for this session.', 'allcoach' ),
			),
			'timezone'       => $this->argString(
				description: __( 'Sender timezone (IANA identifier, e.g. America/New_York).', 'allcoach' ),
			),
		];
	}

	public function getItemSchema(): array {
		return [
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'allcoach_session_booking',
			'type'       => 'object',
			'properties' => [
				'id'             => [
					'description' => __( 'Unique identifier.', 'allcoach' ),
					'type'        => 'integer',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'enrollment_id'  => [
					'description' => __( 'Enrollment ID.', 'allcoach' ),
					'type'        => 'integer',
					'context'     => [ 'view', 'edit' ],
				],
				'client_id'      => [
					'description' => __( 'Client user ID.', 'allcoach' ),
					'type'        => 'integer',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'coach_id'       => [
					'description' => __( 'Coach user ID.', 'allcoach' ),
					'type'        => 'integer',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'program_id'     => [
					'description' => __( 'Program ID.', 'allcoach' ),
					'type'        => 'integer',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'scheduled_date' => [
					'description' => __( 'Scheduled date (YYYY-MM-DD).', 'allcoach' ),
					'type'        => 'string',
					'format'      => 'date',
					'context'     => [ 'view', 'edit' ],
				],
				'start_time'     => [
					'description' => __( 'Start time (HH:MM).', 'allcoach' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit' ],
				],
				'end_time'       => [
					'description' => __( 'End time (HH:MM).', 'allcoach' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit' ],
				],
				'session_number' => [
					'description' => __( 'Session number within the booking.', 'allcoach' ),
					'type'        => 'integer',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'status'         => [
					'description' => __( 'Session status.', 'allcoach' ),
					'type'        => 'string',
					'enum'        => SessionBookingStatus::all(),
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'notes'          => [
					'description' => __( 'Session notes.', 'allcoach' ),
					'type'        => [ 'string', 'null' ],
					'context'     => [ 'edit' ],
				],
				'meeting_url'    => [
					'description' => __( 'Meeting join link (Google Meet or a coach-provided custom URL).', 'allcoach' ),
					'type'        => [ 'string', 'null' ],
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'meeting_type'   => [
					'description' => __( 'How this session is conducted.', 'allcoach' ),
					'type'        => 'string',
					'enum'        => MeetingType::all(),
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'location'       => [
					'description' => __( 'In-person location/instructions (face_to_face meetings only).', 'allcoach' ),
					'type'        => [ 'string', 'null' ],
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'created_at'     => [
					'description' => __( 'Creation date (ISO 8601).', 'allcoach' ),
					'type'        => 'string',
					'format'      => 'date-time',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'updated_at'     => [
					'description' => __( 'Last update date (ISO 8601).', 'allcoach' ),
					'type'        => [ 'string', 'null' ],
					'format'      => 'date-time',
					'context'     => [ 'edit' ],
					'readonly'    => true,
				],
			],
		];
	}
}
