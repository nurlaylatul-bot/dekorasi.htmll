<?php

declare(strict_types=1);

namespace AllCoach\API\Controllers\V1;

defined( 'ABSPATH' ) || exit;

use AllCoach\API\BulkActionsTrait;
use AllCoach\API\RestController;
use AllCoach\Application\Program\CreateProgramService;
use AllCoach\Core\Data\CurrencyData;
use AllCoach\Application\Program\ProgramDTO;
use AllCoach\Application\Program\SyncProgramSessionService;
use AllCoach\Core\Settings\SettingsManager;
use AllCoach\Domain\Booking\MeetingType;
use AllCoach\Domain\Enrollment\EnrollmentRepository;
use AllCoach\Domain\Program\Program;
use AllCoach\Domain\Program\ProgramDuration;
use AllCoach\Domain\Coach\CoachRepository;
use AllCoach\Domain\Program\ProgramFilter;
use AllCoach\Domain\Program\ProgramRepository;
use AllCoach\Domain\Session\SessionRepository;

use function AllCoach\Support\getAttachmentImages;

class ProgramsController extends RestController {

	use BulkActionsTrait;

	protected string $restBase = 'programs';

	/** Supported duration units. */
	private const DURATION_UNITS = [ 'days', 'weeks', 'months' ];

	private const PROGRAM_CATEGORY_TAX = 'allcoach_program_cat';

	public function __construct(
		private readonly CurrencyData $currencyData,
		private readonly ProgramRepository $repository,
		private readonly CreateProgramService $createService,
		private readonly SyncProgramSessionService $syncSessionService,
		private readonly EnrollmentRepository $enrollmentRepository,
		private readonly CoachRepository $coachRepository,
		private readonly SessionRepository $sessionRepository,
		private readonly SettingsManager $settingsManager,
	) {}

	public function registerRoutes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->restBase,
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'getPrograms' ],
					'permission_callback' => '__return_true',
					'args'                => $this->collectionArgs(),
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'createProgram' ],
					'permission_callback' => [ $this, 'permCreate' ],
					'args'                => $this->createArgs(),
				],
				'schema' => [ $this, 'getPublicItemSchema' ],
			]
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->restBase . '/(?P<id>\d+)',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'getProgram' ],
					'permission_callback' => '__return_true',
					'args'                => array_merge( $this->idArg(), $this->contextArg() ),
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'updateProgram' ],
					'permission_callback' => fn() => $this->permCoachOrAdmin(),
					'args'                => array_merge( $this->idArg(), $this->updateArgs() ),
				],
				[
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'deleteProgram' ],
					'permission_callback' => fn() => $this->permCoachOrAdmin(),
					'args'                => $this->idArg(),
				],
				'schema' => [ $this, 'getPublicItemSchema' ],
			]
		);

		$this->registerBulkRoute();
	}

	protected function supportedBulkActions(): array {
		return [ 'delete', 'activate', 'deactivate' ];
	}

	protected function bulkPermissionCallback(): bool {
		return $this->permCoachOrAdmin();
	}

	protected function executeBulkAction( string $action, int $id ): array {
		$program = $this->repository->findById( $id );

		if ( ! $program ) {
			return [
				'id'      => $id,
				'success' => false,
				'error'   => __( 'Program not found.', 'allcoach' ),
			];
		}

		try {
			match ( $action ) {
				'delete' => $this->canDelete( $id )
					? $this->repository->delete( $id )
					: throw new \RuntimeException( __( 'Permission denied.', 'allcoach' ) ),
				'activate' => ( function () use ( $program ) {
					$program->activate();
					$this->repository->save( $program );
				} )(),
				'deactivate' => ( function () use ( $program ) {
					$program->deactivate();
					$this->repository->save( $program );
				} )(),
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

	public function permCreate(): bool {
		return $this->canCreate( 'allcoach_program' );
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private function validateMeetingFields( array $data ): bool|\WP_Error {
		$meetingType = $data['meeting_type'] ?? 'google_meet';

		if ( $meetingType === 'custom_url' && empty( $data['custom_meeting_url'] ) ) {
			return $this->errorResponse(
				__( 'A meeting URL is required when meeting type is Custom URL.', 'allcoach' )
			);
		}

		if ( $meetingType === 'face_to_face' && empty( $data['meeting_location'] ) ) {
			return $this->errorResponse(
				__( 'A location is required when meeting type is Face to Face.', 'allcoach' )
			);
		}

		return true;
	}

	public function getPrograms( \WP_REST_Request $request ): \WP_REST_Response {
		$page    = max( 1, (int) ( $request->get_param( 'page' ) ?? 1 ) );
		$perPage = min( 100, max( 1, (int) ( $request->get_param( 'per_page' ) ?? 20 ) ) );
		$offset  = ( $page - 1 ) * $perPage;
		$search  = sanitize_text_field( (string) ( $request->get_param( 'search' ) ?? '' ) );
		$status  = $request->get_param( 'status' ) ?? 'any';
		$catId   = $request->get_param( 'category_id' ) ? (int) $request->get_param( 'category_id' ) : null;

		$context = $this->getEffectiveContext( $request, [ $this, 'permCoachOrAdmin' ] );
		$schema  = $this->getPublicItemSchema();

		$filter   = $this->buildFilter( $status, $search, $catId );
		$programs = $this->repository->findWithFilter( $filter, $perPage, $offset );
		$total    = $this->repository->countWithFilter( $filter );

		$items = array_map(
			fn( Program $p ) => $this->filterDataByContext( $this->prepareProgram( $p ), $schema, $context ),
			$programs
		);

		return $this->successResponse(
			[
				'programs' => $items,
				'total'    => $total,
				'page'     => $page,
				'per_page' => $perPage,
			]
		);
	}

	private function buildFilter( string $status, string $search, ?int $catId ): ProgramFilter {
		$authorId = null;
		$isActive = null;

		if ( $this->isAdmin() ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedIf
			// No restrictions — leave all nulls
		} elseif ( $this->isCoach() ) {
			$authorId = get_current_user_id();
		} else {
			$isActive = true;
		}
		if ( $this->permCoachOrAdmin() ) {
			if ( $status === 'active' ) {
				$isActive = true;
			} elseif ( $status === 'draft' ) {
				$isActive = false;
			}
		}

		return new ProgramFilter(
			isActive:      $isActive,
			authorId:      $authorId,
			search:        $search !== '' ? $search : null,
			categoryId:    $catId,
		);
	}

	public function getProgram( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$id      = (int) $request->get_param( 'id' );
		$program = $this->repository->findById( $id );

		if ( ! $program ) {
			return $this->notFoundResponse( __( 'Program', 'allcoach' ) );
		}

		$post = get_post( $id );
		if ( $post && $post->post_status !== 'publish' && ! $this->canRead( $id ) ) {
			return $this->forbiddenResponse();
		}

		$context = $this->getEffectiveContext( $request, [ $this, 'permCoachOrAdmin' ] );
		$data    = $this->filterDataByContext( $this->prepareProgram( $program ), $this->getPublicItemSchema(), $context );

		return $this->successResponse( $data );
	}

	public function createProgram( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$data = $request->get_params();

		$validation = $this->validateRequired( $data, [ 'title', 'duration_value', 'duration_unit' ] );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$meetingValidation = $this->validateMeetingFields( $data );
		if ( is_wp_error( $meetingValidation ) ) {
			return $meetingValidation;
		}

		$data['coach_id']       = get_current_user_id();
		$data['price_currency'] = $this->settingsManager->get( 'currency' );
		try {
			$dto     = ProgramDTO::fromArray( $data );
			$program = $this->createService->execute( $dto );
			$this->syncSessionService->sync( $program->getId(), $data );

			if ( ! empty( $data['category_ids'] ) ) {
				$ids = array_map( 'intval', (array) $data['category_ids'] );
				wp_set_object_terms( $program->getId(), $ids, self::PROGRAM_CATEGORY_TAX );
			}

			return $this->successResponse( $this->prepareProgram( $program ), 201 );
		} catch ( \Throwable $e ) {
			return $this->exceptionToResponse( $e );
		}
	}

	public function updateProgram( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$id = (int) $request->get_param( 'id' );

		$program = $this->repository->findById( $id );
		if ( ! $program ) {
			return $this->notFoundResponse( __( 'Program', 'allcoach' ) );
		}

		if ( ! $this->canEdit( $id ) ) {
			return $this->forbiddenResponse();
		}
		$body = $request->get_json_params() ?? [];

		$effectiveMeetingType = array_key_exists( 'meeting_type', $body ) ? $body['meeting_type'] : $program->getMeetingType()->getValue();
		$effectiveCustomUrl   = array_key_exists( 'custom_meeting_url', $body ) ? $body['custom_meeting_url'] : $program->getCustomMeetingUrl();
		$effectiveLocation    = array_key_exists( 'meeting_location', $body ) ? $body['meeting_location'] : $program->getMeetingLocation();
		$meetingValidation    = $this->validateMeetingFields(
			[
				'meeting_type'       => $effectiveMeetingType,
				'custom_meeting_url' => $effectiveCustomUrl,
				'meeting_location'   => $effectiveLocation,
			]
		);
		if ( is_wp_error( $meetingValidation ) ) {
			return $meetingValidation;
		}

		try {
			$this->applyProgramUpdates( $program, $body, $request );
			$program = $this->repository->save( $program );
			$this->syncSessionService->sync( $program->getId(), $body );

			if ( array_key_exists( 'category_ids', $body ) ) {
				$ids = array_map( 'intval', (array) $request->get_param( 'category_ids' ) );
				wp_set_object_terms( $program->getId(), $ids, self::PROGRAM_CATEGORY_TAX );
			}

			return $this->successResponse( $this->prepareProgram( $program ) );
		} catch ( \Throwable $e ) {
			return $this->exceptionToResponse( $e );
		}
	}


	private function applyProgramUpdates( Program $program, array $body, \WP_REST_Request $request ): void {
		if ( array_key_exists( 'title', $body ) ) {
			$program->setTitle( (string) $request->get_param( 'title' ) );
		}
		if ( array_key_exists( 'description', $body ) ) {
			$program->setDescription( (string) $request->get_param( 'description' ) );
		}
		if ( array_key_exists( 'is_active', $body ) ) {
			$request->get_param( 'is_active' ) ? $program->activate() : $program->deactivate();
		}
		if ( array_key_exists( 'duration_value', $body ) || array_key_exists( 'duration_unit', $body ) ) {
			$program->setDuration(
				new ProgramDuration(
					array_key_exists( 'duration_value', $body ) ? (int) $request->get_param( 'duration_value' ) : $program->getDuration()->getValue(),
					array_key_exists( 'duration_unit', $body ) ? (string) $request->get_param( 'duration_unit' ) : $program->getDuration()->getUnit(),
				)
			);
		}
		if ( array_key_exists( 'price_amount', $body ) ) {
			$program->setPrice( (float) $request->get_param( 'price_amount' ) );
		}
		if ( array_key_exists( 'max_enrollments', $body ) ) {
			$program->setMaxEnrollments( (int) $request->get_param( 'max_enrollments' ) );
		}
		if ( array_key_exists( 'meeting_type', $body ) || array_key_exists( 'custom_meeting_url', $body ) || array_key_exists( 'meeting_location', $body ) ) {
			$program->setMeeting(
				array_key_exists( 'meeting_type', $body ) ? MeetingType::fromString( (string) $request->get_param( 'meeting_type' ) ) : $program->getMeetingType(),
				array_key_exists( 'custom_meeting_url', $body ) ? (string) $request->get_param( 'custom_meeting_url' ) : $program->getCustomMeetingUrl(),
				array_key_exists( 'meeting_location', $body ) ? (string) $request->get_param( 'meeting_location' ) : $program->getMeetingLocation(),
			);
		}
		if ( array_key_exists( 'thumbnail_id', $body ) ) {
			$program->setThumbnailId( (int) $request->get_param( 'thumbnail_id' ) );
		}
	}

	public function deleteProgram( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$id = (int) $request->get_param( 'id' );

		$program = $this->repository->findById( $id );
		if ( ! $program ) {
			return $this->notFoundResponse( __( 'Program', 'allcoach' ) );
		}

		if ( ! $this->canDelete( $id ) ) {
			return $this->forbiddenResponse();
		}

		if ( ! $this->repository->delete( $id ) ) {
			return $this->errorResponse( __( 'Failed to delete program', 'allcoach' ), 500 );
		}

		return $this->successResponse(
			[
				'deleted' => true,
				'id'      => $id,
			]
		);
	}

	private function prepareProgram( Program $program ): array {
		$programId  = $program->getId();
		$coachId    = $program->getCoachId();
		$coach      = $this->coachInfo( $coachId );
		$categories = $this->programCategoriesForPost( $programId );
		$session    = $this->sessionRepository->findOneByProgramId( $programId );
		return [
			'id'                => $programId,
			'title'             => $program->getTitle(),
			'description'       => $program->getDescription(),
			'duration'          => [
				'value' => $program->getDuration()->getValue(),
				'unit'  => $program->getDuration()->getUnit(),
			],
			'price'             => [
				'amount'   => $program->getPrice(),
				'currency' => (string) $this->settingsManager->get( 'currency' ),
				'is_free'  => $program->isFree(),
			],
			'max_enrollments'   => $program->getMaxEnrollments(),
			'is_active'         => $program->isActive(),
			'coach_id'          => $coachId,
			'coach'             => $coach,
			'booking_count'     => $this->enrollmentRepository->countByProgram( $programId ),
			'categories'        => $categories,
			'created_at'        => $program->getCreatedAt()->format( 'c' ),
			'updated_at'        => $program->getUpdatedAt()?->format( 'c' ),
			'session'           => $session !== null ? [
				'id'               => $session->getId(),
				'duration_minutes' => $session->getDurationMinutes(),
				'repetition_count' => $session->getRepetitionCount(),
			] : null,
			'sessions_count'    => $session !== null ? $session->getRepetitionCount() : 0,
			'sessions_duration' => $this->sessionRepository->totalDurationByProgram( $programId ),
			'thumbnail_id'      => $program->getThumbnailId(),
			'featured_image'    => getAttachmentImages( $program->getThumbnailId() ),
			'meeting_type'       => $program->getMeetingType()->getValue(),
			'custom_meeting_url' => $program->getCustomMeetingUrl(),
			'meeting_location'   => $program->getMeetingLocation(),
		];
	}

	private function collectionArgs(): array {
		return array_merge(
			$this->paginationArgs( defaultPerPage: 20, maxPerPage: 100 ),
			$this->contextArg(),
			[
				'search'      => $this->argString(
					description: __( 'Filter programs by title.', 'allcoach' ),
				),
				'status'      => $this->argEnum(
					description: __( 'Filter by program status: active, draft, or any.', 'allcoach' ),
					values:      [ 'active', 'draft', 'any' ],
					default:     'any',
				),
				'category_id' => $this->argInteger(
					description: __( 'Filter by program category term ID.', 'allcoach' ),
					min:         1,
				),
			]
		);
	}
	private function writableArgs( bool $required ): array {
		return [
			'title'                    => $this->argString(
				description: __( 'Program title.', 'allcoach' ),
				required:    $required,
				minLength:   1,
				maxLength:   200,
			),
			'description'              => $this->argString(
				description: __( 'Program description (HTML allowed).', 'allcoach' ),
				sanitize:    'wp_kses_post',
				default:     '',
			),
			'duration_value'           => $this->argInteger(
				description: __( 'Duration length, e.g. 4 for "4 weeks".', 'allcoach' ),
				required:    $required,
				min:         1,
				max:         365,
			),
			'duration_unit'            => $this->argEnum(
				description: __( 'Duration unit: days, weeks, or months.', 'allcoach' ),
				values:      self::DURATION_UNITS,
				required:    $required,
			),
			'price_amount'             => $this->argNumber(
				description: __( 'Program price. Set to 0 for a free program.', 'allcoach' ),
				min:         0.0,
				default:     0.0,
				sanitize:    fn( $v ) => abs( (float) $v ),
			),
			'price_currency'           => $this->argEnum(
				description: __( 'ISO 4217 currency code.', 'allcoach' ),
				values:      $this->currencyData->getCodes(),
				sanitize:    fn( $v ) => strtoupper( sanitize_text_field( $v ) ),
				default:     'USD',
			),
			'max_enrollments'          => $this->argInteger(
				description: __( 'Maximum enrolled clients. 0 means unlimited.', 'allcoach' ),
				min:         0,
				default:     0,
			),
			'is_active'                => $this->argBoolean(
				description: __( 'Whether the program is accepting enrollments.', 'allcoach' ),
				default:     true,
			),
			'thumbnail_id'             => $this->argInteger(
				description: __( 'Attachment ID for the program thumbnail. 0 or omit to clear.', 'allcoach' ),
				min:         0,
			),
			'category_ids'             => [
				'description' => __( 'Array of program category term IDs.', 'allcoach' ),
				'type'        => 'array',
				'items'       => [
					'type'    => 'integer',
					'minimum' => 1,
				],
			],
			'session_duration_minutes' => $this->argInteger(
				description: __( 'Session duration per occurrence in minutes.', 'allcoach' ),
				min:         0,
			),
			'session_repetition_count' => $this->argInteger(
				description: __( 'Number of times the session repeats (count).', 'allcoach' ),
				min:         1,
			),
			'meeting_type'             => $this->argEnum(
				description: __( 'How sessions for this program are conducted.', 'allcoach' ),
				values:      MeetingType::all(),
				default:     'google_meet',
			),
			'custom_meeting_url'       => $this->argString(
				description: __( 'Meeting URL to use when meeting_type is custom_url.', 'allcoach' ),
				sanitize:    'esc_url_raw',
			),
			'meeting_location'        => $this->argString(
				description: __( 'Location/instructions to use when meeting_type is face_to_face.', 'allcoach' ),
				sanitize:    'sanitize_textarea_field',
			),
		];
	}

	private function createArgs(): array {
		return array_merge(
			$this->writableArgs( required: true ),
			[
				'coach_id' => $this->argInteger(
					description: __( 'WP user ID of the program owner. Admin-only — coaches are always assigned to themselves.', 'allcoach' ),
					min:         1,
				),
			]
		);
	}

	private function updateArgs(): array {
		return array_merge(
			$this->writableArgs( required: false ),
			[
				'remove_session' => $this->argBoolean(
					description: __( 'If true, remove the program session.', 'allcoach' ),
					default:     false,
				),
			]
		);
	}

	public function getPublicItemSchema(): array {
		return [
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'allcoach_program',
			'type'       => 'object',
			'properties' => [
				'id'                       => [
					'description' => __( 'Unique identifier.', 'allcoach' ),
					'type'        => 'integer',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'title'                    => [
					'description' => __( 'Program title.', 'allcoach' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit' ],
				],
				'description'              => [
					'description' => __( 'Program description (may contain HTML).', 'allcoach' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit' ],
				],
				'duration'                 => [
					'description' => __( 'Program duration.', 'allcoach' ),
					'type'        => 'object',
					'context'     => [ 'view', 'edit' ],
					'properties'  => [
						'value' => [
							'type'    => 'integer',
							'minimum' => 1,
						],
						'unit'  => [
							'type' => 'string',
							'enum' => self::DURATION_UNITS,
						],
					],
				],
				'price'                    => [
					'description' => __( 'Program price.', 'allcoach' ),
					'type'        => 'object',
					'context'     => [ 'view', 'edit' ],
					'properties'  => [
						'amount'   => [
							'type'    => 'number',
							'minimum' => 0,
						],
						'currency' => [
							'type' => 'string',
							'enum' => $this->currencyData->getCodes(),
						],
						'is_free'  => [
							'type'     => 'boolean',
							'readonly' => true,
						],
					],
				],
				'max_enrollments'          => [
					'description' => __( 'Maximum enrollments. 0 = unlimited.', 'allcoach' ),
					'type'        => 'integer',
					'minimum'     => 0,
					'context'     => [ 'view', 'edit' ],
				],
				'is_active'                => [
					'description' => __( 'Whether the program accepts enrollments.', 'allcoach' ),
					'type'        => 'boolean',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'coach_id'                 => [
					'description' => __( 'WP user ID of the program owner.', 'allcoach' ),
					'type'        => [ 'integer', 'null' ],
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'coach'                    => [
					'description' => __( 'Coach display info.', 'allcoach' ),
					'type'        => 'object',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
					'properties'  => [
						'id'   => [ 'type' => [ 'integer', 'null' ] ],
						'name' => [ 'type' => 'string' ],
					],
				],
				'booking_count'            => [
					'description' => __( 'Number of active bookings.', 'allcoach' ),
					'type'        => 'integer',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'categories'               => [
					'description' => __( 'Program categories.', 'allcoach' ),
					'type'        => 'array',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
					'items'       => [
						'type'       => 'object',
						'properties' => [
							'id'   => [ 'type' => 'integer' ],
							'name' => [ 'type' => 'string' ],
							'slug' => [ 'type' => 'string' ],
						],
					],
				],
				'created_at'               => [
					'description' => __( 'Creation date (ISO 8601).', 'allcoach' ),
					'type'        => 'string',
					'format'      => 'date-time',
					'context'     => [ 'view' ],
					'readonly'    => true,
				],
				'updated_at'               => [
					'description' => __( 'Last update date (ISO 8601).', 'allcoach' ),
					'type'        => [ 'string', 'null' ],
					'format'      => 'date-time',
					'context'     => [ 'view' ],
					'readonly'    => true,
				],
				'sessions_count'           => [
					'description' => __( 'Session repetition count (number of times the session repeats).', 'allcoach' ),
					'type'        => 'integer',
					'context'     => [ 'view' ],
					'readonly'    => true,
				],
				'sessions_duration'        => [
					'description' => __( 'Total session duration in minutes (duration × repetition count).', 'allcoach' ),
					'type'        => 'integer',
					'context'     => [ 'view' ],
					'readonly'    => true,
				],
				'session'                  => [
					'description' => __( 'The single session for this program (if any).', 'allcoach' ),
					'type'        => [ 'object', 'null' ],
					'context'     => [ 'view' ],
					'readonly'    => true,
					'properties'  => [
						'id'               => [ 'type' => 'integer' ],
						'duration_minutes' => [ 'type' => 'integer' ],
						'repetition_count' => [ 'type' => 'integer' ],
					],
				],
				'thumbnail_id'             => [
					'description' => __( 'Attachment ID for the program thumbnail.', 'allcoach' ),
					'type'        => [ 'integer', 'null' ],
					'context'     => [ 'view', 'edit' ],
				],
				'session_duration_minutes' => [
					'description' => __( 'Session duration per occurrence in minutes (create/update).', 'allcoach' ),
					'type'        => 'integer',
					'minimum'     => 0,
					'context'     => [ 'edit' ],
				],
				'session_repetition_count' => [
					'description' => __( 'Number of times the session repeats (create/update).', 'allcoach' ),
					'type'        => 'integer',
					'minimum'     => 1,
					'context'     => [ 'edit' ],
				],
				'remove_session'           => [
					'description' => __( 'If true, remove the program session (update only).', 'allcoach' ),
					'type'        => 'boolean',
					'context'     => [ 'edit' ],
				],
				'meeting_type'             => [
					'description' => __( 'How sessions for this program are conducted.', 'allcoach' ),
					'type'        => 'string',
					'enum'        => MeetingType::all(),
					'context'     => [ 'view', 'edit' ],
				],
				'custom_meeting_url'       => [
					'description' => __( 'Meeting URL used when meeting_type is custom_url.', 'allcoach' ),
					'type'        => [ 'string', 'null' ],
					'context'     => [ 'view', 'edit' ],
				],
				'meeting_location'        => [
					'description' => __( 'Location/instructions used when meeting_type is face_to_face.', 'allcoach' ),
					'type'        => [ 'string', 'null' ],
					'context'     => [ 'view', 'edit' ],
				],
				'featured_image'           => [
					'description' => __( 'Program featured image.', 'allcoach' ),
					'type'        => [ 'object', 'null' ],
					'properties'  => [
						'id'    => [
							'type' => 'integer',
						],
						'sizes' => [
							'type'       => 'object',
							'properties' => array_reduce(
								array_keys( wp_get_registered_image_subsizes() ),
								function ( $acc, $curr ) {
									$acc[ $curr ] = [
										$curr => [
											'type'       => 'object',
											'properties' => [
												'height' => 'integer',
												'width'  => 'integer',
												'url'    => 'string',
											],
										],
									];
									return $acc;
								},
								[]
							),
						],
					],
					'context'     => [ 'view' ],
				],
			],
		];
	}

	private function coachInfo( ?int $coachId ): array {
		if ( $coachId === null || $coachId <= 0 ) {
			return [
				'id'   => null,
				'name' => '',
			];
		}
		$coach = $this->coachRepository->findById( $coachId );
		if ( $coach !== null ) {
			return [
				'id'   => $coachId,
				'name' => $coach->getName(),
			];
		}
		$user = get_userdata( $coachId );
		return [
			'id'   => $coachId,
			'name' => $user ? ( $user->display_name ?: $user->user_login ) : '',
		];
	}

	private function programCategoriesForPost( int $postId ): array {
		$terms = get_the_terms( $postId, self::PROGRAM_CATEGORY_TAX );
		if ( ! $terms || is_wp_error( $terms ) ) {
			return [];
		}
		$out = [];
		foreach ( $terms as $term ) {
			$out[] = [
				'id'   => $term->term_id,
				'name' => $term->name,
				'slug' => $term->slug,
			];
		}
		return $out;
	}
}
