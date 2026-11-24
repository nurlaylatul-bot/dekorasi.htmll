<?php

declare(strict_types=1);

namespace AllCoach\API\Controllers\V1;

defined( 'ABSPATH' ) || exit;

use AllCoach\API\RestController;
use AllCoach\Application\Activity\ActivityCompletionDTO;
use AllCoach\Application\Activity\ActivityProgressQueryService;
use AllCoach\Application\Activity\CompleteActivityService;
use AllCoach\Domain\Activity\Activity;
use AllCoach\Domain\Activity\ActivityCompletion;
use AllCoach\Domain\Activity\ActivityRepository;
use AllCoach\Domain\Activity\ActivitySchema;
use AllCoach\Domain\Program\ProgramRepository;

class ActivitiesController extends RestController {

	protected string $restBase = 'programs';

	public function __construct(
		private readonly ProgramRepository $programRepository,
		private readonly ActivityRepository $activityRepository,
		private readonly CompleteActivityService $completeActivityService,
		private readonly ActivityProgressQueryService $progressQueryService,
	) {}

	public function registerRoutes(): void {
		$base = '/' . $this->restBase . '/(?P<program_id>\d+)/activities';

		register_rest_route(
			$this->namespace,
			$base,
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'getActivities' ],
					'permission_callback' => [ $this, 'permRead' ],
					'args'                => array_merge(
						$this->programIdArg(),
						$this->paginationArgs( defaultPerPage: 50, maxPerPage: 200 ),
						$this->contextArg(),
						[ 'week' => $this->argInteger( __( 'Filter by week number.', 'allcoach' ), min: 1 ) ]
					),
				],
				'schema' => [ $this, 'getPublicItemSchema' ],
			]
		);

		register_rest_route(
			$this->namespace,
			$base . '/(?P<id>\d+)',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'getActivity' ],
					'permission_callback' => [ $this, 'permRead' ],
					'args'                => array_merge( $this->programIdArg(), $this->idArg( __( 'Activity ID.', 'allcoach' ) ), $this->contextArg() ),
				],
				[
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'updateActivity' ],
					'permission_callback' => [ $this, 'permWrite' ],
					'args'                => array_merge(
						$this->programIdArg(),
						$this->idArg( __( 'Activity ID.', 'allcoach' ) ),
						$this->updateArgs()
					),
				],
				[
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'deleteActivity' ],
					'permission_callback' => [ $this, 'permWrite' ],
					'args'                => array_merge( $this->programIdArg(), $this->idArg( __( 'Activity ID.', 'allcoach' ) ) ),
				],
				'schema' => [ $this, 'getPublicItemSchema' ],
			]
		);

		register_rest_route(
			$this->namespace,
			$base . '/(?P<id>\d+)/complete',
			[
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'completeActivity' ],
					'permission_callback' => [ $this, 'permRead' ],
					'args'                => array_merge(
						$this->programIdArg(),
						$this->idArg( __( 'Activity ID.', 'allcoach' ) ),
						[
							'enrollment_id' => $this->argInteger(
								description: __( 'Enrollment ID for which this activity is being completed.', 'allcoach' ),
								required: true,
								min: 1,
							),
							'response_data' => [
								'description'       => __( 'Client response/submission data.', 'allcoach' ),
								'type'              => [ 'object', 'array' ],
								'required'          => false,
								'default'           => [],
								'sanitize_callback' => fn( $v ) => is_array( $v ) ? $v : [],
								'validate_callback' => 'rest_validate_request_arg',
							],
						]
					),
				],
			]
		);

		register_rest_route(
			$this->namespace,
			$base . '/completions',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'getCompletions' ],
					'permission_callback' => [ $this, 'permRead' ],
					'args'                => array_merge(
						$this->programIdArg(),
						[
							'enrollment_id' => $this->argInteger(
								description: __( 'Enrollment ID.', 'allcoach' ),
								required: true,
								min: 1,
							),
						]
					),
				],
			]
		);

		register_rest_route(
			$this->namespace,
			$base . '/progress',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'getActivityProgress' ],
					'permission_callback' => [ $this, 'permRead' ],
					'args'                => array_merge(
						$this->programIdArg(),
						[
							'enrollment_id' => $this->argInteger(
								description: __( 'Enrollment ID.', 'allcoach' ),
								required: true,
								min: 1,
							),
						]
					),
				],
			]
		);
	}

	private function programIdArg(): array {
		return [
			'program_id' => $this->argInteger(
				description: __( 'Program ID.', 'allcoach' ),
				required:    true,
				min:         1,
			),
		];
	}

	private function createArgs(): array {
		return array_merge(
			$this->scheduleArgs( required: true ),
			[
				'template_id' => $this->argInteger(
					description: __( 'Activity Library template ID. When provided, the schema is copied from the library item. Omit for a custom one-off activity.', 'allcoach' ),
					min:         1,
				),
				'schema'      => [
					'description'       => __( 'JSON-encoded ActivitySchema for a custom activity (required when template_id is omitted).', 'allcoach' ),
					'type'              => [ 'object', 'string' ],
					'required'          => false,
					'default'           => '',
					'sanitize_callback' => fn( $v ) => is_array( $v ) ? wp_json_encode( $v ) : (string) $v,
					'validate_callback' => 'rest_validate_request_arg',
				],
				'settings'    => [
					'description'       => __( 'Per-instance configuration overriding the base schema (e.g. custom labels, visibility rules).', 'allcoach' ),
					'type'              => 'object',
					'required'          => false,
					'default'           => [],
					'sanitize_callback' => fn( $v ) => is_array( $v ) ? $v : [],
					'validate_callback' => 'rest_validate_request_arg',
				],
			]
		);
	}

	private function updateArgs(): array {
		return array_merge(
			$this->scheduleArgs( required: false ),
			[
				'settings' => [
					'description'       => __( 'Per-instance configuration.', 'allcoach' ),
					'type'              => 'object',
					'required'          => false,
					'sanitize_callback' => fn( $v ) => is_array( $v ) ? $v : [],
					'validate_callback' => 'rest_validate_request_arg',
				],
				'schema'   => [
					'description'       => __( 'Updated activity schema (fields).', 'allcoach' ),
					'type'              => [ 'object', 'string' ],
					'required'          => false,
					'sanitize_callback' => fn( $v ) => is_array( $v ) ? wp_json_encode( $v ) : (string) $v,
					'validate_callback' => 'rest_validate_request_arg',
				],
			]
		);
	}

	private function scheduleArgs( bool $required ): array {
		return [
			'week'     => $this->argInteger(
				description: __( 'Week number this activity falls in (1-based).', 'allcoach' ),
				required:    $required,
				min:         1,
				max:         52,
			),
			'day'      => $this->argInteger(
				description: __( 'Day within the week (1 = Monday … 7 = Sunday).', 'allcoach' ),
				required:    $required,
				min:         1,
				max:         7,
			),
			'position' => $this->argInteger(
				description: __( 'Display order within the same day (0-based).', 'allcoach' ),
				min:         0,
			),
		];
	}

	public function getPublicItemSchema(): array {
		return [
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'allcoach_activity',
			'type'       => 'object',
			'properties' => [
				'id'          => [
					'description' => __( 'Unique identifier.', 'allcoach' ),
					'type'        => 'integer',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'program_id'  => [
					'description' => __( 'ID of the parent program.', 'allcoach' ),
					'type'        => 'integer',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'template_id' => [
					'description' => __( 'Library template ID, or null for a custom activity.', 'allcoach' ),
					'type'        => [ 'integer', 'null' ],
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'schema'      => [
					'description' => __( 'Snapshotted ActivitySchema at the time this activity was added to the program.', 'allcoach' ),
					'type'        => 'object',
					'context'     => [ 'view', 'edit' ],
				],
				'settings'    => [
					'description' => __( 'Per-instance configuration.', 'allcoach' ),
					'type'        => 'object',
					'context'     => [ 'view', 'edit' ],
				],
				'week'        => [
					'description' => __( 'Week number (1-based).', 'allcoach' ),
					'type'        => 'integer',
					'minimum'     => 1,
					'context'     => [ 'view', 'edit' ],
				],
				'day'         => [
					'description' => __( 'Day of week (1–7).', 'allcoach' ),
					'type'        => 'integer',
					'minimum'     => 1,
					'maximum'     => 7,
					'context'     => [ 'view', 'edit' ],
				],
				'position'    => [
					'description' => __( 'Display order within the same day.', 'allcoach' ),
					'type'        => 'integer',
					'minimum'     => 0,
					'context'     => [ 'view', 'edit' ],
				],
				'created_at'  => [
					'description' => __( 'Creation date (ISO 8601).', 'allcoach' ),
					'type'        => 'string',
					'format'      => 'date-time',
					'context'     => [ 'view' ],
					'readonly'    => true,
				],
			],
		];
	}

	public function permRead(): bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		return $this->isAdmin() || $this->isCoach() || $this->isClient();
	}

	public function permWrite( \WP_REST_Request $request ): bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		if ( $this->isAdmin() ) {
			return true;
		}
		if ( ! $this->isCoach() ) {
			return false;
		}
		$programId = (int) $request->get_param( 'program_id' );
		return $programId > 0 && $this->canEdit( $programId );
	}

	public function getActivities( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$programId = (int) $request->get_param( 'program_id' );

		if ( ! $this->programRepository->findById( $programId ) ) {
			return $this->notFoundResponse( __( 'Program', 'allcoach' ) );
		}

		$week    = $request->get_param( 'week' );
		$context = $this->getEffectiveContext( $request, [ $this, 'permWrite' ] );
		$schema  = $this->getPublicItemSchema();

		$activities = $week !== null
			? $this->activityRepository->findByProgramAndWeek( $programId, (int) $week )
			: $this->activityRepository->findByProgram( $programId );

		$items = array_map(
			fn( Activity $a ) => $this->filterDataByContext( $this->prepareActivity( $a ), $schema, $context ),
			$activities
		);

		return $this->successResponse(
			[
				'activities' => $items,
				'total'      => count( $activities ),
				'program_id' => $programId,
			]
		);
	}

	public function getActivity( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		[ $programId, $activity ] = $this->resolveActivity( $request );
		if ( $activity instanceof \WP_Error ) {
			return $activity;
		}

		$context = $this->getEffectiveContext( $request, [ $this, 'permWrite' ] );
		$data    = $this->filterDataByContext( $this->prepareActivity( $activity ), $this->getPublicItemSchema(), $context );

		return $this->successResponse( $data );
	}

	public function updateActivity( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		[ $programId, $activity ] = $this->resolveActivity( $request );
		if ( $activity instanceof \WP_Error ) {
			return $activity;
		}

		$body = $request->get_params() ?? [];

		try {
			if ( array_key_exists( 'week', $body ) || array_key_exists( 'day', $body ) || array_key_exists( 'position', $body ) ) {
				$activity->reschedule(
					array_key_exists( 'day', $body ) ? (int) $request->get_param( 'day' ) : $activity->getDay(),
					array_key_exists( 'week', $body ) ? (int) $request->get_param( 'week' ) : $activity->getWeek(),
					array_key_exists( 'position', $body ) ? (int) $request->get_param( 'position' ) : $activity->getPosition(),
				);
			}
			if ( array_key_exists( 'settings', $body ) ) {
				$activity->setSettings( (array) $request->get_param( 'settings' ) );
			}
			if ( array_key_exists( 'schema', $body ) ) {
				$activity->setSchema( ActivitySchema::fromJson( (string) $request->get_param( 'schema' ) ) );
			}

			return $this->successResponse( $this->prepareActivity( $this->activityRepository->save( $activity ) ) );
		} catch ( \Throwable $e ) {
			return $this->exceptionToResponse( $e );
		}
	}

	public function deleteActivity( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		[ $programId, $activity ] = $this->resolveActivity( $request );
		if ( $activity instanceof \WP_Error ) {
			return $activity;
		}

		if ( ! $this->activityRepository->delete( (int) $activity->getId() ) ) {
			return $this->errorResponse( __( 'Failed to delete activity.', 'allcoach' ), 500 );
		}

		return $this->successResponse(
			[
				'deleted' => true,
				'id'      => $activity->getId(),
			]
		);
	}

	/**
	 * @return array{int, Activity|\WP_Error}
	 */
	private function resolveActivity( \WP_REST_Request $request ): array {
		$programId  = (int) $request->get_param( 'program_id' );
		$activityId = (int) $request->get_param( 'id' );

		if ( ! $this->programRepository->findById( $programId ) ) {
			return [ 0, $this->notFoundResponse( __( 'Program', 'allcoach' ) ) ];
		}

		$activity = $this->activityRepository->findById( $activityId );
		if ( ! $activity ) {
			return [ 0, $this->notFoundResponse( __( 'Activity', 'allcoach' ) ) ];
		}

		if ( $activity->getProgramId() !== $programId ) {
			return [ 0, $this->notFoundResponse( __( 'Activity', 'allcoach' ) ) ];
		}

		return [ $programId, $activity ];
	}

	public function completeActivity( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		[ $programId, $activity ] = $this->resolveActivity( $request );
		if ( $activity instanceof \WP_Error ) {
			return $activity;
		}

		try {
			$dto        = new ActivityCompletionDTO(
				enrollmentId: (int) $request->get_param( 'enrollment_id' ),
				activityId: (int) $activity->getId(),
				responseData: (array) ( $request->get_param( 'response_data' ) ?? [] ),
			);
			$completion = $this->completeActivityService->execute( $dto );

			return $this->successResponse( $this->prepareCompletion( $completion ), 201 );
		} catch ( \Throwable $e ) {
			return $this->exceptionToResponse( $e );
		}
	}

	public function getCompletions( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$programId    = (int) $request->get_param( 'program_id' );
		$enrollmentId = (int) $request->get_param( 'enrollment_id' );

		if ( ! $this->programRepository->findById( $programId ) ) {
			return $this->notFoundResponse( __( 'Program', 'allcoach' ) );
		}

		$completions = $this->progressQueryService->findByEnrollment( $enrollmentId );

		$items = array_map(
			fn( ActivityCompletion $c ) => $this->prepareCompletion( $c ),
			$completions
		);

		return $this->successResponse(
			[
				'completions' => $items,
				'total'       => count( $items ),
			]
		);
	}

	public function getActivityProgress( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$programId    = (int) $request->get_param( 'program_id' );
		$enrollmentId = (int) $request->get_param( 'enrollment_id' );

		if ( ! $this->programRepository->findById( $programId ) ) {
			return $this->notFoundResponse( __( 'Program', 'allcoach' ) );
		}

		$progress = $this->progressQueryService->getProgress( $enrollmentId, $programId );

		return $this->successResponse( $progress );
	}

	private function prepareCompletion( ActivityCompletion $completion ): array {
		return [
			'id'            => $completion->getId(),
			'enrollment_id' => $completion->getEnrollmentId(),
			'client_id'     => $completion->getClientId(),
			'activity_id'   => $completion->getActivityId(),
			'program_id'    => $completion->getProgramId(),
			'response_data' => $completion->getResponseData(),
			'completed_at'  => $completion->getCompletedAt()->format( 'c' ),
			'created_at'    => $completion->getCreatedAt()->format( 'c' ),
		];
	}

	private function prepareActivity( Activity $activity ): array {
		return [
			'id'              => $activity->getId(),
			'program_id'      => $activity->getProgramId(),
			'template_id'     => $activity->getTemplateId(),
			'is_from_library' => $activity->isFromLibrary(),
			'schema'          => $activity->getSchema()->toArray(),
			'settings'        => $activity->getSettings(),
			'week'            => $activity->getWeek(),
			'day'             => $activity->getDay(),
			'position'        => $activity->getPosition(),
			'created_at'      => $activity->getCreatedAt()->format( 'c' ),
		];
	}
}
