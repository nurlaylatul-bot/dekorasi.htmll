<?php

declare(strict_types=1);

namespace AllCoach\API\Controllers\V1;

defined( 'ABSPATH' ) || exit;

use AllCoach\API\RestController;
use AllCoach\Application\Activity\CreateProgramActivityService;
use AllCoach\Application\Activity\ProgramActivityDTO;
use AllCoach\Domain\Program\ProgramRepository;
use AllCoach\Domain\Activity\Activity;
use AllCoach\Domain\Activity\ActivityRepository;
use AllCoach\Domain\Section\Section;
use AllCoach\Domain\Section\SectionRepository;

class SectionsController extends RestController {

	protected string $restBase = 'programs';

	public function __construct(
		private readonly ProgramRepository $programRepository,
		private readonly SectionRepository $sectionRepository,
		private readonly ActivityRepository $activityRepository,
		private readonly CreateProgramActivityService $createActivityService,
	) {}

	public function registerRoutes(): void {
		$base = '/' . $this->restBase . '/(?P<program_id>\d+)/sections';

		register_rest_route(
			$this->namespace,
			$base,
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'getSections' ],
					'permission_callback' => [ $this, 'permCoachOrAdmin' ],
					'args'                => array_merge(
						$this->programIdArg(),
						$this->contextArg()
					),
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'createSection' ],
					'permission_callback' => [ $this, 'permCoachOrAdmin' ],
					'args'                => array_merge(
						$this->programIdArg(),
						$this->sectionCreateArgs()
					),
				],
				[
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'reorderSections' ],
					'permission_callback' => [ $this, 'permCoachOrAdmin' ],
					'args'                => array_merge(
						$this->programIdArg(),
						[
							'order' => [
								'description'       => __( 'Ordered list of section IDs for this program.', 'allcoach' ),
								'type'              => 'array',
								'items'             => [
									'type' => 'integer',
								],
								'required'          => true,
								'sanitize_callback' => fn( $v ) => is_array( $v ) ? array_map( 'intval', $v ) : [],
								'validate_callback' => 'rest_validate_request_arg',
							],
						]
					),
				],
			]
		);

		register_rest_route(
			$this->namespace,
			$base . '/(?P<section_id>\d+)',
			[
				[
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'updateSection' ],
					'permission_callback' => [ $this, 'permCoachOrAdmin' ],
					'args'                => array_merge(
						$this->programIdArg(),
						$this->sectionIdArg(),
						[
							'title'       => $this->argString(
								description: __( 'Section title.', 'allcoach' ),
								minLength:   1,
								maxLength:   200,
							),
							'description' => $this->argString(
								description: __( 'Section description.', 'allcoach' ),
								sanitize:    'wp_kses_post',
							),
						]
					),
				],
				[
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'deleteSection' ],
					'permission_callback' => [ $this, 'permCoachOrAdmin' ],
					'args'                => array_merge(
						$this->programIdArg(),
						$this->sectionIdArg()
					),
				],
			]
		);

		register_rest_route(
			$this->namespace,
			$base . '/(?P<section_id>\d+)/activities',
			[
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'createSectionActivity' ],
					'permission_callback' => [ $this, 'permCoachOrAdmin' ],
					'args'                => array_merge(
						$this->programIdArg(),
						$this->sectionIdArg(),
						$this->sectionActivityCreateArgs()
					),
				],
			]
		);

		register_rest_route(
			$this->namespace,
			$base . '/(?P<section_id>\d+)/activities/order',
			[
				[
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'reorderSectionActivities' ],
					'permission_callback' => [ $this, 'permCoachOrAdmin' ],
					'args'                => array_merge(
						$this->programIdArg(),
						$this->sectionIdArg(),
						[
							'activity_ids' => [
								'description'       => __( 'Ordered list of activity IDs belonging to this section.', 'allcoach' ),
								'type'              => 'array',
								'items'             => [
									'type' => 'integer',
								],
								'required'          => true,
								'sanitize_callback' => fn( $v ) => is_array( $v ) ? array_map( 'intval', $v ) : [],
								'validate_callback' => 'rest_validate_request_arg',
							],
						]
					),
				],
			]
		);
	}

	public function permCoachOrAdmin(): bool {
		return is_user_logged_in() && ( $this->isCoach() || $this->isAdmin() );
	}

	public function canEditStructure( \WP_REST_Request $request ): bool {
		return $this->permCoachOrAdmin();
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

	private function sectionIdArg(): array {
		return [
			'section_id' => $this->argInteger(
				description: __( 'Section ID.', 'allcoach' ),
				required:    true,
				min:         1,
			),
		];
	}

	private function sectionCreateArgs(): array {
		return [
			'title'       => $this->argString(
				description: __( 'Section title.', 'allcoach' ),
				required:    true,
				minLength:   1,
				maxLength:   200,
			),
			'description' => $this->argString(
				description: __( 'Optional section description.', 'allcoach' ),
				sanitize:    'wp_kses_post',
				default:     '',
			),
		];
	}

	private function sectionActivityCreateArgs(): array {
		return [
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
				'description'       => __( 'Per-instance configuration (e.g. custom labels).', 'allcoach' ),
				'type'              => 'object',
				'required'          => false,
				'default'           => [],
				'sanitize_callback' => fn( $v ) => is_array( $v ) ? $v : [],
				'validate_callback' => 'rest_validate_request_arg',
			],
		];
	}

	public function getSections( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$programId = (int) $request->get_param( 'program_id' );

		if ( ! $this->programRepository->findById( $programId ) ) {
			return $this->notFoundResponse( __( 'Program', 'allcoach' ) );
		}

		$sections   = $this->sectionRepository->findByProgram( $programId );
		$activities = $this->activityRepository->findByProgram( $programId );

		$bySection = [];
		foreach ( $activities as $activity ) {
			$sectionId = $activity->getSectionId();
			if ( $sectionId === null ) {
				continue;
			}
			$bySection[ $sectionId ][] = $activity;
		}

		$context     = $this->getEffectiveContext( $request, [ $this, 'canEditStructure' ] );
		$sectionsOut = [];

		foreach ( $sections as $section ) {
			$sectionArr        = $this->prepareSection( $section );
			$sectionActivities = $bySection[ (int) $section->getId() ] ?? [];
			usort( $sectionActivities, fn( Activity $a, Activity $b ) => $a->getPosition() <=> $b->getPosition() );
			$sectionArr['activities'] = array_map(
				fn( Activity $a ) => $this->prepareActivity( $a, $context ),
				$sectionActivities
			);

			$sectionsOut[] = $sectionArr;
		}

		return $this->successResponse(
			[
				'sections' => $sectionsOut,
			]
		);
	}

	public function createSection( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$programId = (int) $request->get_param( 'program_id' );

		if ( ! $this->programRepository->findById( $programId ) ) {
			return $this->notFoundResponse( __( 'Program', 'allcoach' ) );
		}

		$title       = (string) $request->get_param( 'title' );
		$description = (string) ( $request->get_param( 'description' ) ?? '' );

		$existing  = $this->sectionRepository->findByProgram( $programId );
		$position  = count( $existing );
		$section   = new Section( $programId, $title, $description, $position );
		$persisted = $this->sectionRepository->save( $section );

		return $this->successResponse( $this->prepareSection( $persisted ), 201 );
	}

	public function createSectionActivity( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$programId = (int) $request->get_param( 'program_id' );
		$sectionId = (int) $request->get_param( 'section_id' );

		if ( ! $this->programRepository->findById( $programId ) ) {
			return $this->notFoundResponse( __( 'Program', 'allcoach' ) );
		}

		$section = $this->sectionRepository->findById( $sectionId );
		if ( ! $section || $section->getProgramId() !== $programId ) {
			return $this->notFoundResponse( __( 'Section', 'allcoach' ) );
		}

		$templateId = $request->get_param( 'template_id' );
		$schemaJson = (string) $request->get_param( 'schema' );
		if ( $templateId === null && $schemaJson === '' ) {
			return $this->errorResponse(
				__( 'Provide either template_id (library-backed) or schema (custom activity).', 'allcoach' ),
				422
			);
		}

		$activitiesInSection = array_filter(
			$this->activityRepository->findByProgram( $programId ),
			fn( Activity $a ) => $a->getSectionId() === $sectionId
		);
		$position            = count( $activitiesInSection );

		try {
			$dto = new ProgramActivityDTO(
				programId:  $programId,
				sectionId:  $sectionId,
				day:        1,
				week:       1,
				position:   $position,
				templateId: $templateId !== null ? (int) $templateId : null,
				schemaJson: $schemaJson,
				settings:   (array) $request->get_param( 'settings' ),
			);

			$activity = $this->createActivityService->execute( $dto );
			return $this->successResponse( $this->prepareActivity( $activity, 'edit' ), 201 );
		} catch ( \Throwable $e ) {
			return $this->exceptionToResponse( $e );
		}
	}

	public function reorderSections( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$programId = (int) $request->get_param( 'program_id' );
		$order     = (array) $request->get_param( 'order' );

		if ( ! $this->programRepository->findById( $programId ) ) {
			return $this->notFoundResponse( __( 'Program', 'allcoach' ) );
		}

		$position = 0;
		foreach ( $order as $sectionId ) {
			$sectionId = (int) $sectionId;
			$section   = $this->sectionRepository->findById( $sectionId );
			if ( ! $section || $section->getProgramId() !== $programId ) {
				continue;
			}
			$section->setPosition( $position );
			$this->sectionRepository->save( $section );
			++$position;
		}

		return $this->successResponse(
			[
				'reordered' => true,
			]
		);
	}

	public function reorderSectionActivities( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$programId = (int) $request->get_param( 'program_id' );
		$sectionId = (int) $request->get_param( 'section_id' );

		if ( ! $this->programRepository->findById( $programId ) ) {
			return $this->notFoundResponse( __( 'Program', 'allcoach' ) );
		}

		$section = $this->sectionRepository->findById( $sectionId );
		if ( ! $section || $section->getProgramId() !== $programId ) {
			return $this->notFoundResponse( __( 'Section', 'allcoach' ) );
		}

		$activityIds = (array) $request->get_param( 'activity_ids' );
		$position    = 0;

		foreach ( $activityIds as $activityId ) {
			$activityId = (int) $activityId;
			$activity   = $this->activityRepository->findById( $activityId );
			if ( ! $activity || $activity->getProgramId() !== $programId ) {
				continue;
			}
			$activity->setSectionId( $sectionId );
			// position within section; keep existing week/day for now.
			$activity->reschedule( $activity->getDay(), $activity->getWeek(), $position );
			$this->activityRepository->save( $activity );
			++$position;
		}

		return $this->successResponse(
			[
				'reordered' => true,
			]
		);
	}

	public function updateSection( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$programId = (int) $request->get_param( 'program_id' );
		$sectionId = (int) $request->get_param( 'section_id' );

		if ( ! $this->programRepository->findById( $programId ) ) {
			return $this->notFoundResponse( __( 'Program', 'allcoach' ) );
		}

		$section = $this->sectionRepository->findById( $sectionId );
		if ( ! $section || $section->getProgramId() !== $programId ) {
			return $this->notFoundResponse( __( 'Section', 'allcoach' ) );
		}

		$body = $request->get_params() ?? [];

		if ( array_key_exists( 'title', $body ) ) {
			$section->setTitle( (string) $request->get_param( 'title' ) );
		}
		if ( array_key_exists( 'description', $body ) ) {
			$section->setDescription( (string) $request->get_param( 'description' ) );
		}

		return $this->successResponse( $this->prepareSection( $this->sectionRepository->save( $section ) ) );
	}

	public function deleteSection( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$programId = (int) $request->get_param( 'program_id' );
		$sectionId = (int) $request->get_param( 'section_id' );

		if ( ! $this->programRepository->findById( $programId ) ) {
			return $this->notFoundResponse( __( 'Program', 'allcoach' ) );
		}

		$section = $this->sectionRepository->findById( $sectionId );
		if ( ! $section || $section->getProgramId() !== $programId ) {
			return $this->notFoundResponse( __( 'Section', 'allcoach' ) );
		}

		// Delete all activities belonging to this section first.
		$activities = $this->activityRepository->findByProgram( $programId );
		foreach ( $activities as $activity ) {
			if ( $activity->getSectionId() === $sectionId ) {
				$this->activityRepository->delete( (int) $activity->getId() );
			}
		}

		if ( ! $this->sectionRepository->delete( $sectionId ) ) {
			return $this->errorResponse( __( 'Failed to delete section.', 'allcoach' ), 500 );
		}

		return $this->successResponse( [ 'deleted' => true, 'id' => $sectionId ] );
	}

	private function prepareSection( Section $section ): array {
		return [
			'id'          => $section->getId(),
			'program_id'  => $section->getProgramId(),
			'title'       => $section->getTitle(),
			'description' => $section->getDescription(),
			'position'    => $section->getPosition(),
			'created_at'  => $section->getCreatedAt()->format( 'c' ),
		];
	}

	private function prepareActivity( Activity $activity, string $context ): array {
		$data = $activity->toArray();

		// For now, we do not expose schema/context filtering here; the builder can request only what it needs.
		// The 'context' parameter is accepted to allow future refinement.
		// if ( $context !== 'edit' ) {
			// In view context, you might later strip settings or other edit-only fields.
		// }

		/**
		 * Filters activity data returned by the sections API.
		 *
		 * @param array    $data     Serialized activity data.
		 * @param Activity $activity Activity domain object.
		 */
		return apply_filters( 'allcoach:activity:data', $data, $activity );
	}
}
