<?php

declare(strict_types=1);

namespace AllCoach\API\Controllers\V1;

defined( 'ABSPATH' ) || exit;

use AllCoach\API\RestController;
use AllCoach\Application\Availability\AvailabilityDTO;
use AllCoach\Application\Availability\AvailabilityQueryService;
use AllCoach\Application\Availability\CreateAvailabilityService;
use AllCoach\Application\Availability\DeleteAvailabilityService;
use AllCoach\Application\Availability\UpdateAvailabilityService;
use AllCoach\Domain\Availability\Availability;
use AllCoach\Domain\Availability\AvailabilityRepository;
use AllCoach\Domain\Booking\SessionBookingFilter;
use AllCoach\Domain\Booking\SessionBookingRepository;
use AllCoach\Domain\Enrollment\EnrollmentRepository;
use AllCoach\Domain\Program\ProgramRepository;
use AllCoach\Domain\Session\SessionRepository;
use Carbon\CarbonImmutable;

class AvailabilitiesController extends RestController {

	protected string $restBase = 'availabilities';

	public function __construct(
		private readonly AvailabilityQueryService $queryService,
		private readonly CreateAvailabilityService $createService,
		private readonly UpdateAvailabilityService $updateService,
		private readonly DeleteAvailabilityService $deleteService,
		private readonly AvailabilityRepository $availabilityRepository,
		private readonly EnrollmentRepository $enrollmentRepository,
		private readonly ProgramRepository $programRepository,
		private readonly SessionRepository $sessionRepository,
		private readonly SessionBookingRepository $bookingRepository,
	) {}

	public function registerRoutes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->restBase,
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'getAvailabilities' ],
					'permission_callback' => [ $this, 'permRead' ],
					'args'                => array_merge(
						$this->userIdArg(),
						$this->paginationArgs( 50, 100 ),
						$this->contextArg()
					),
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'createAvailability' ],
					'permission_callback' => [ $this, 'permWrite' ],
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
					'callback'            => [ $this, 'getAvailability' ],
					'permission_callback' => [ $this, 'permRead' ],
					'args'                => array_merge( $this->idArg(), $this->contextArg() ),
				],
				[
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'updateAvailability' ],
					'permission_callback' => [ $this, 'permWrite' ],
					'args'                => array_merge( $this->idArg(), $this->updateArgs() ),
				],
				[
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'deleteAvailability' ],
					'permission_callback' => [ $this, 'permWrite' ],
					'args'                => $this->idArg(),
				],
				'schema' => [ $this, 'getItemSchema' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->restBase . '/available-days',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'getAvailableDays' ],
					'permission_callback' => fn() => $this->permCoachOrAdmin(),
					'args'                => [
						'enrollment_id' => array_merge(
							$this->argInteger( description: __( 'Enrollment ID.', 'allcoach' ), min: 1 ),
							[ 'required' => true ]
						),
					],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->restBase . '/slots',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'getSlots' ],
					'permission_callback' => fn() => $this->permCoachOrAdmin(),
					'args'                => [
						'enrollment_id' => array_merge(
							$this->argInteger( description: __( 'Enrollment ID.', 'allcoach' ), min: 1 ),
							[ 'required' => true ]
						),
						'date'          => array_merge(
							$this->argString( description: __( 'Date in Y-m-d format.', 'allcoach' ) ),
							[ 'required' => true ]
						),
					],
				],
			]
		);
	}

	public function permRead(): bool {
		return is_user_logged_in() && ( $this->isCoach() || $this->isAdmin() || current_user_can( 'manage_coaching_clients' ) );
	}

	public function permWrite( \WP_REST_Request $request ): bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		if ( $this->isAdmin() ) {
			return true;
		}
		$userId = $request->get_param( 'user_id' );
		if ( $userId === null && $request->get_method() !== \WP_REST_Server::CREATABLE ) {
			$id = $request->get_param( 'id' );
			if ( $id !== null ) {
				$availability = $this->queryService->findById( (int) $id );
				return $availability !== null && (int) $availability->getUserId() === (int) get_current_user_id();
			}
		}
		$targetUserId = $userId !== null ? (int) $userId : get_current_user_id();
		return $this->isCoach() && (int) get_current_user_id() === $targetUserId;
	}

	private function userIdArg(): array {
		return [
			'user_id' => array_merge(
				$this->argInteger(
					description: __( 'Filter by user (coach/admin) ID. Omit for current user.', 'allcoach' ),
					min: 1,
				),
				[
					'required' => false,
					'default'  => null,
				]
			),
		];
	}

	private function createArgs(): array {
		return [
			'user_id'              => $this->argInteger( description: __( 'User (coach/admin) ID. Omit for current user.', 'allcoach' ), min: 0 ),
			'start_date'           => $this->argString( description: __( 'Start date (Y-m-d).', 'allcoach' ), required: true ),
			'shift'                => [
				'description'       => __( 'Shift details (start_time, end_time, repeat options). Can be used instead of flat keys.', 'allcoach' ),
				'type'              => 'object',
				'required'          => false,
				'default'           => [],
				'sanitize_callback' => fn( $v ) => is_array( $v ) ? $v : [],
				'validate_callback' => 'rest_validate_request_arg',
			],
			'start_time'           => $this->argString( description: __( 'Start time (e.g. 9:00 AM or 09:00).', 'allcoach' ), required: false ),
			'end_time'             => $this->argString( description: __( 'End time.', 'allcoach' ), required: false ),
			'is_repeating'         => $this->argBoolean( description: __( 'Whether the shift repeats weekly.', 'allcoach' ), default: false ),
			'repeat_week_interval' => $this->argInteger( description: __( 'Repeat every N weeks.', 'allcoach' ), min: 1, default: 1 ),
			'repeat_sunday'        => $this->argBoolean( description: __( 'Repeat on Sunday.', 'allcoach' ), default: false ),
			'repeat_monday'        => $this->argBoolean( description: __( 'Repeat on Monday.', 'allcoach' ), default: false ),
			'repeat_tuesday'       => $this->argBoolean( description: __( 'Repeat on Tuesday.', 'allcoach' ), default: false ),
			'repeat_wednesday'     => $this->argBoolean( description: __( 'Repeat on Wednesday.', 'allcoach' ), default: false ),
			'repeat_thursday'      => $this->argBoolean( description: __( 'Repeat on Thursday.', 'allcoach' ), default: false ),
			'repeat_friday'        => $this->argBoolean( description: __( 'Repeat on Friday.', 'allcoach' ), default: false ),
			'repeat_saturday'      => $this->argBoolean( description: __( 'Repeat on Saturday.', 'allcoach' ), default: false ),
			'is_program_specific'  => $this->argBoolean( description: __( 'Whether this availability is limited to specific programs.', 'allcoach' ), default: false ),
			'program_ids'          => [
				'description'       => __( 'Program IDs when is_program_specific is true (e.g. shift[program_ids][]).', 'allcoach' ),
				'type'              => 'array',
				'items'             => [ 'type' => 'integer' ],
				'required'          => false,
				'default'           => [],
				'sanitize_callback' => fn( $v ) => is_array( $v ) ? array_map( 'absint', $v ) : [],
				'validate_callback' => 'rest_validate_request_arg',
			],
		];
	}

	private function updateArgs(): array {
		$args                           = $this->createArgs();
		$args['user_id']['required']    = false;
		$args['start_date']['required'] = false;
		$args['start_time']['required'] = false;
		$args['end_time']['required']   = false;
		return $args;
	}

	public function getAvailabilities( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$userId = $request->get_param( 'user_id' );
		if ( $userId === null ) {
			$userId = get_current_user_id();
		}
		$userId = (int) $userId;

		if ( $userId <= 0 ) {
			return $this->errorResponse( __( 'user_id is required or you must be logged in.', 'allcoach' ), 400 );
		}

		$page    = max( 1, (int) ( $request->get_param( 'page' ) ?? 1 ) );
		$perPage = min( 100, max( 1, (int) ( $request->get_param( 'per_page' ) ?? 50 ) ) );
		$total   = $this->queryService->countByUser( $userId );
		$items   = $total > 0
			? $this->queryService->findByUser( $userId, $page, $perPage )
			: [ AvailabilityQueryService::defaultAvailability( $userId ) ];
		$context = $this->getEffectiveContext( $request, 'manage_options' );
		$schema  = $this->getItemSchema();
		$data    = array_map(
			fn( Availability $a ) => $this->filterDataByContext( $a->toArray(), $schema, $context ),
			$items
		);

		return $this->successResponse(
			[
				'availabilities' => $data,
				'total'          => $total,
				'page'           => $page,
				'per_page'       => $perPage,
			]
		);
	}

	public function getAvailability( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$availability = $this->queryService->findById( (int) $request->get_param( 'id' ) );
		if ( $availability === null ) {
			return $this->notFoundResponse( __( 'Availability', 'allcoach' ) );
		}
		$context = $this->getEffectiveContext( $request, 'manage_options' );
		$data    = $this->filterDataByContext( $availability->toArray(), $this->getItemSchema(), $context );
		return $this->successResponse( $data );
	}

	public function createAvailability( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		try {
			$params = $request->get_params() ?: [];
			$dto    = AvailabilityDTO::fromArray( $params );
			if ( $dto->userId <= 0 ) {
				$params['user_id'] = get_current_user_id();
				$dto               = AvailabilityDTO::fromArray( $params );
			}
			if ( $dto->userId <= 0 ) {
				return $this->errorResponse( __( 'user_id is required or you must be logged in.', 'allcoach' ), 422 );
			}
			if ( $dto->startDate === '' ) {
				return $this->errorResponse( __( 'start_date are required (can be sent in shift object).', 'allcoach' ), 422 );
			}
			$availability = $this->createService->execute( $dto );
			return $this->successResponse( $availability->toArray(), 201 );
		} catch ( \Throwable $e ) {
			return $this->exceptionToResponse( $e );
		}
	}

	public function updateAvailability( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		try {
			$params       = $request->get_params() ?: [];
			$params['id'] = (int) $request->get_param( 'id' );
			$dto          = AvailabilityDTO::fromArray( $params );
			$availability = $this->updateService->execute( (int) $request->get_param( 'id' ), $dto );
			return $this->successResponse( $availability->toArray() );
		} catch ( \Throwable $e ) {
			return $this->exceptionToResponse( $e );
		}
	}

	public function deleteAvailability( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		try {
			$this->deleteService->execute( (int) $request->get_param( 'id' ) );
			return $this->successResponse( [ 'deleted' => true ] );
		} catch ( \Throwable $e ) {
			return $this->exceptionToResponse( $e );
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	public function getItemSchema(): array {
		return [
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'allcoach_avail',
			'type'       => 'object',
			'properties' => [
				'id'                   => [
					'description' => __( 'Unique identifier.', 'allcoach' ),
					'type'        => 'integer',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'user_id'              => [
					'description' => __( 'User (coach/admin) ID.', 'allcoach' ),
					'type'        => 'integer',
					'context'     => [ 'view', 'edit' ],
				],
				'start_date'           => [
					'description' => __( 'Start date (Y-m-d).', 'allcoach' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit' ],
				],
				'start_time'           => [
					'description' => __( 'Start time.', 'allcoach' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit' ],
				],
				'end_time'             => [
					'description' => __( 'End time.', 'allcoach' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit' ],
				],
				'is_repeating'         => [
					'description' => __( 'Whether the shift repeats.', 'allcoach' ),
					'type'        => 'boolean',
					'context'     => [ 'view', 'edit' ],
				],
				'repeat_week_interval' => [
					'description' => __( 'Repeat every N weeks.', 'allcoach' ),
					'type'        => 'integer',
					'context'     => [ 'view', 'edit' ],
				],
				'repeat_sunday'        => [
					'type'    => 'boolean',
					'context' => [ 'view', 'edit' ],
				],
				'repeat_monday'        => [
					'type'    => 'boolean',
					'context' => [ 'view', 'edit' ],
				],
				'repeat_tuesday'       => [
					'type'    => 'boolean',
					'context' => [ 'view', 'edit' ],
				],
				'repeat_wednesday'     => [
					'type'    => 'boolean',
					'context' => [ 'view', 'edit' ],
				],
				'repeat_thursday'      => [
					'type'    => 'boolean',
					'context' => [ 'view', 'edit' ],
				],
				'repeat_friday'        => [
					'type'    => 'boolean',
					'context' => [ 'view', 'edit' ],
				],
				'repeat_saturday'      => [
					'type'    => 'boolean',
					'context' => [ 'view', 'edit' ],
				],
				'is_program_specific'  => [
					'description' => __( 'Whether this availability is limited to specific programs.', 'allcoach' ),
					'type'        => 'boolean',
					'context'     => [ 'view', 'edit' ],
				],
				'program_ids'          => [
					'description' => __( 'Program IDs when is_program_specific is true.', 'allcoach' ),
					'type'        => 'array',
					'items'       => [ 'type' => 'integer' ],
					'context'     => [ 'view', 'edit' ],
				],
				'created_at'           => [
					'description' => __( 'Creation date.', 'allcoach' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
			],
		];
	}

	public function getAvailableDays( \WP_REST_Request $request ): \WP_REST_Response {
		$enrollmentId = (int) $request->get_param( 'enrollment_id' );
		$enrollment   = $this->enrollmentRepository->findById( $enrollmentId );

		if ( ! $enrollment ) {
			return $this->successResponse( [ 'available_days' => [] ] );
		}

		$program = $this->programRepository->findById( $enrollment->getProgramId() );
		if ( ! $program ) {
			return $this->successResponse( [ 'available_days' => [] ] );
		}

		$availabilities = $this->queryService->findByUserOrDefault( $program->getCoachId() );
		$availableDays  = [];

		foreach ( $availabilities as $availability ) {
			for ( $day = 0; $day <= 6; $day++ ) {
				if ( $availability->getSchedule()->isDayAvailable( $day ) ) {
					$availableDays[ $day ] = true;
				}
			}
		}

		return $this->successResponse( [ 'available_days' => array_values( array_keys( $availableDays ) ) ] );
	}

	public function getSlots( \WP_REST_Request $request ): \WP_REST_Response {
		$enrollmentId = (int) $request->get_param( 'enrollment_id' );
		$date         = sanitize_text_field( $request->get_param( 'date' ) );

		$enrollment = $this->enrollmentRepository->findById( $enrollmentId );
		if ( ! $enrollment ) {
			return $this->successResponse( [ 'slots' => [] ] );
		}

		$program = $this->programRepository->findById( $enrollment->getProgramId() );
		if ( ! $program ) {
			return $this->successResponse( [ 'slots' => [] ] );
		}

		$session  = $this->sessionRepository->findOneByProgramId( $program->getId() );
		$duration = $session ? max( 15, $session->getDurationMinutes() ) : 60;

		$availabilities = $this->queryService->findByUserOrDefault( $program->getCoachId() );
		$dateObj        = CarbonImmutable::createFromFormat( 'Y-m-d', $date );
		if ( ! $dateObj ) {
			return $this->successResponse( [ 'slots' => [] ] );
		}

		$dayOfWeek = (int) $dateObj->format( 'w' ); // 0=Sunday .. 6=Saturday

		// Collect available windows for this day across all availability records
		$windows = [];
		foreach ( $availabilities as $availability ) {
			$schedule = $availability->getSchedule();
			if ( $schedule->isDayAvailable( $dayOfWeek ) ) {
				$day       = $schedule->getDay( $dayOfWeek );
				$windows[] = [
					'start' => $day['start_time'],
					'end'   => $day['end_time'],
				];
			}
		}

		if ( empty( $windows ) ) {
			return $this->successResponse( [ 'slots' => [] ] );
		}

		// Get existing scheduled bookings for this coach on this date
		$filter           = new SessionBookingFilter(
			coachId:  $program->getCoachId(),
			fromDate: $date,
			toDate:   $date,
			excludeCancelled: true,
		);
		$existingBookings = $this->bookingRepository->findWithFilter( $filter, 200, 0 );

		// Generate slots within each window
		$slots = [];
		foreach ( $windows as $window ) {
			$current = strtotime( $window['start'] );
			$end     = strtotime( $window['end'] );

			while ( $current + ( $duration * 60 ) <= $end ) {
				$slotStart = gmdate( 'H:i', $current );
				$slotEnd   = gmdate( 'H:i', $current + ( $duration * 60 ) );

				// Check overlap with existing bookings
				$booked = false;
				foreach ( $existingBookings as $booking ) {
					if ( $booking->getStartTime() < $slotEnd && $booking->getEndTime() > $slotStart ) {
						$booked = true;
						break;
					}
				}

				$slots[] = [
					'start_time' => $slotStart,
					'end_time'   => $slotEnd,
					'available'  => ! $booked,
				];

				$current += $duration * 60;
			}
		}

		return $this->successResponse(
			[
				'slots'            => $slots,
				'duration_minutes' => $duration,
			]
		);
	}
}
