<?php

declare(strict_types=1);

namespace AllCoach\API\Controllers\V1;

defined( 'ABSPATH' ) || exit;

use AllCoach\API\RestController;
use AllCoach\Application\Activity\ActivityDTO;
use AllCoach\Application\Activity\CreateActivityService;
use AllCoach\Domain\Activity\ActivityTemplate;
use AllCoach\Domain\Activity\ActivityTemplateRepository;
use AllCoach\Domain\Activity\ActivitySchema;


class LibraryActivitiesController extends RestController {

	protected string $restBase = 'activities';

	/** Supported field types for an ActivitySchema. */
	private const FIELD_TYPES = [
		'text',
		'textarea',
		'number',
		'rating',
		'checkbox',
		'select',
		'radio',
		'date',
		'file',
	];

	public function __construct(
		private readonly ActivityTemplateRepository $repository,
		private readonly CreateActivityService $createService,
	) {}

	public function registerRoutes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->restBase,
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'getActivities' ],
					'permission_callback' => '__return_true',
					'args'                => $this->collectionArgs(),
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'createActivity' ],
					'permission_callback' => [ $this, 'permCreate' ],
					'args'                => $this->createArgs(),
				],
				'schema' => [ $this, 'getPublicItemSchema' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->restBase . '/search',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'searchActivities' ],
					'permission_callback' => [ $this, 'permCoachOrAdmin' ],
					'args'                => [
						'q'        => $this->argString(
							description: __( 'Search term.', 'allcoach' ),
							required:    true,
							minLength:   1,
							maxLength:   200,
						),
						'per_page' => $this->argInteger(
							description: __( 'Maximum results to return.', 'allcoach' ),
							min:         1,
							max:         50,
							default:     20,
						),
					],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->restBase . '/(?P<id>\d+)',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'getActivity' ],
					'permission_callback' => '__return_true',
					'args'                => array_merge( $this->idArg( __( 'Activity library item ID.', 'allcoach' ) ), $this->contextArg() ),
				],
				[
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'updateActivity' ],
					'permission_callback' => [ $this, 'permCoachOrAdmin' ],
					'args'                => array_merge(
						$this->idArg( __( 'Activity library item ID.', 'allcoach' ) ),
						$this->updateArgs()
					),
				],
				[
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'deleteActivity' ],
					'permission_callback' => [ $this, 'permCoachOrAdmin' ],
					'args'                => $this->idArg( __( 'Activity library item ID.', 'allcoach' ) ),
				],
				'schema' => [ $this, 'getPublicItemSchema' ],
			]
		);
	}

	private function collectionArgs(): array {
		return array_merge(
			$this->paginationArgs( defaultPerPage: 20, maxPerPage: 100 ),
			$this->contextArg(),
			[
				'published_only' => $this->argBoolean(
					description: __( 'Return only published library items.', 'allcoach' ),
					default:     false,
				),
			]
		);
	}

	private function createArgs(): array {
		return array_merge(
			$this->writableArgs( required: true ),
			[
				'coach_id' => $this->argInteger(
					description: __( 'WP user ID of the activity owner. Admin-only — coaches are always assigned to themselves.', 'allcoach' ),
					min:         1,
				),
			]
		);
	}

	private function updateArgs(): array {
		return $this->writableArgs( required: false );
	}

	private function writableArgs( bool $required ): array {
		return [
			'title'        => $this->argString(
				description: __( 'Activity title.', 'allcoach' ),
				required:    $required,
				minLength:   1,
				maxLength:   200,
			),
			'description'  => $this->argString(
				description: __( 'Activity description.', 'allcoach' ),
				sanitize:    'wp_kses_post',
				default:     '',
			),
			'schema'       => [
				'description'       => __( 'JSON-encoded ActivitySchema defining the response fields.', 'allcoach' ),
				'type'              => [ 'object', 'string' ],
				'required'          => false,
				'default'           => [],
				'sanitize_callback' => fn( $v ) => is_array( $v ) ? wp_json_encode( $v ) : (string) $v,
				'validate_callback' => fn( $v ) => $this->validateSchema( $v ),
			],
			'is_published' => $this->argBoolean(
				description: __( 'Whether the activity is published and visible to all coaches.', 'allcoach' ),
				default:     false,
			),
		];
	}

	public function getPublicItemSchema(): array {
		return [
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'allcoach_activity',
			'type'       => 'object',
			'properties' => [
				'id'           => [
					'description' => __( 'Unique identifier.', 'allcoach' ),
					'type'        => 'integer',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'title'        => [
					'description' => __( 'Activity title.', 'allcoach' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit' ],
				],
				'description'  => [
					'description' => __( 'Activity description.', 'allcoach' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit' ],
				],
				'schema'       => [
					'description' => __( 'Field schema clients complete when doing this activity.', 'allcoach' ),
					'type'        => 'object',
					'context'     => [ 'view', 'edit' ],
					'properties'  => [
						'version' => [
							'type'     => 'string',
							'readonly' => true,
						],
						'fields'  => [
							'type'  => 'array',
							'items' => [
								'type'       => 'object',
								'properties' => [
									'type'     => [
										'type' => 'string',
										'enum' => self::FIELD_TYPES,
									],
									'label'    => [ 'type' => [ 'string', 'object' ] ],
									'required' => [ 'type' => 'boolean' ],
								],
							],
						],
					],
				],
				'coach_id'     => [
					'description' => __( 'WP user ID of the owning coach.', 'allcoach' ),
					'type'        => [ 'integer', 'null' ],
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'is_published' => [
					'description' => __( 'Whether the activity is published.', 'allcoach' ),
					'type'        => 'boolean',
					'context'     => [ 'view', 'edit' ],
				],
				'created_at'   => [
					'description' => __( 'Creation date (ISO 8601).', 'allcoach' ),
					'type'        => 'string',
					'format'      => 'date-time',
					'context'     => [ 'view' ],
					'readonly'    => true,
				],
				'updated_at'   => [
					'description' => __( 'Last update date (ISO 8601).', 'allcoach' ),
					'type'        => [ 'string', 'null' ],
					'format'      => 'date-time',
					'context'     => [ 'view' ],
					'readonly'    => true,
				],
			],
		];
	}

	public function permCreate(): bool {
		return $this->canCreate( 'allcoach_activity' );
	}

	public function permCoachOrAdmin(): bool {
		return is_user_logged_in() && ( $this->isCoach() || $this->isAdmin() );
	}

	public function getActivities( \WP_REST_Request $request ): \WP_REST_Response {
		$page    = (int) $request->get_param( 'page' );
		$perPage = (int) $request->get_param( 'per_page' );
		$offset  = ( $page - 1 ) * $perPage;

		if ( $this->isAdmin() ) {
			$items = $this->repository->findAll( $perPage, $offset );
			$total = $this->repository->count();
		} elseif ( $this->isCoach() ) {
			$userId = get_current_user_id();
			$items  = $this->repository->findByCoachId( $userId, $perPage, $offset );
			$total  = $this->repository->countByCoachId( $userId );
		} else {
			$items = $this->repository->findAll( $perPage, $offset );
			$total = $this->repository->count();
		}

		$context = $this->getEffectiveContext( $request, [ $this, 'permCoachOrAdmin' ] );
		$schema  = $this->getPublicItemSchema();
		$list    = array_map(
			fn( ActivityTemplate $a ) => $this->filterDataByContext( $this->prepareActivity( $a ), $schema, $context ),
			$items
		);

		return $this->successResponse(
			[
				'activities' => $list,
				'total'      => $total,
				'page'       => $page,
				'per_page'   => $perPage,
			]
		);
	}

	public function getActivity( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$id       = (int) $request->get_param( 'id' );
		$activity = $this->repository->findById( $id );

		if ( ! $activity ) {
			return $this->notFoundResponse( __( 'Activity', 'allcoach' ) );
		}

		$post = get_post( $id );
		if ( $post && $post->post_status !== 'publish' && ! $this->canRead( $id ) ) {
			return $this->forbiddenResponse();
		}

		$context = $this->getEffectiveContext( $request, [ $this, 'permCoachOrAdmin' ] );
		$data    = $this->filterDataByContext( $this->prepareActivity( $activity ), $this->getPublicItemSchema(), $context );

		return $this->successResponse( $data );
	}

	public function searchActivities( \WP_REST_Request $request ): \WP_REST_Response {
		$results = $this->repository->search(
			(string) $request->get_param( 'q' ),
			(int) $request->get_param( 'per_page' )
		);
		$context = $this->getEffectiveContext( $request, [ $this, 'permCoachOrAdmin' ] );
		$schema  = $this->getPublicItemSchema();
		$list    = array_map(
			fn( ActivityTemplate $a ) => $this->filterDataByContext( $this->prepareActivity( $a ), $schema, $context ),
			$results
		);

		return $this->successResponse(
			[
				'activities' => $list,
				'total'      => count( $results ),
			]
		);
	}

	public function createActivity( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$coachId = $this->isAdmin()
			? ( (int) $request->get_param( 'coach_id' ) ?: get_current_user_id() )
			: get_current_user_id();

		try {
			$dto = new ActivityDTO(
				title:       (string) $request->get_param( 'title' ),
				description: (string) $request->get_param( 'description' ),
				schemaJson:  (string) $request->get_param( 'schema' ),
				coachId:     $coachId,
				isPublished: (bool) $request->get_param( 'is_published' ),
			);

			return $this->successResponse( $this->prepareActivity( $this->createService->execute( $dto ) ), 201 );
		} catch ( \Throwable $e ) {
			return $this->exceptionToResponse( $e );
		}
	}

	public function updateActivity( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$id       = (int) $request->get_param( 'id' );
		$activity = $this->repository->findById( $id );

		if ( ! $activity ) {
			return $this->notFoundResponse( __( 'Activity', 'allcoach' ) );
		}

		if ( ! $this->canEdit( $id ) ) {
			return $this->forbiddenResponse();
		}

		$body = $request->get_params() ?? [];

		try {
			if ( array_key_exists( 'title', $body ) ) {
				$activity->setTitle( (string) $request->get_param( 'title' ) );
			}
			if ( array_key_exists( 'description', $body ) ) {
				$activity->setDescription( (string) $request->get_param( 'description' ) );
			}
			if ( array_key_exists( 'schema', $body ) ) {
				$activity->setSchema( ActivitySchema::fromJson( (string) $request->get_param( 'schema' ) ) );
			}
			if ( array_key_exists( 'is_published', $body ) ) {
				$request->get_param( 'is_published' ) ? $activity->publish() : $activity->unpublish();
			}

			return $this->successResponse( $this->prepareActivity( $this->repository->save( $activity ) ) );
		} catch ( \Throwable $e ) {
			return $this->exceptionToResponse( $e );
		}
	}

	public function deleteActivity( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$id       = (int) $request->get_param( 'id' );
		$activity = $this->repository->findById( $id );

		if ( ! $activity ) {
			return $this->notFoundResponse( __( 'Activity', 'allcoach' ) );
		}

		if ( ! $this->canDelete( $id ) ) {
			return $this->forbiddenResponse();
		}

		if ( ! $this->repository->delete( $id ) ) {
			return $this->errorResponse( __( 'Failed to delete activity.', 'allcoach' ), 500 );
		}

		return $this->successResponse(
			[
				'deleted' => true,
				'id'      => $id,
			]
		);
	}

	private function validateSchema( mixed $value ): bool|\WP_Error {
		if ( empty( $value ) ) {
			return true;
		}

		$json = is_array( $value ) ? wp_json_encode( $value ) : (string) $value;

		$decoded = json_decode( $json, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new \WP_Error(
				'invalid_schema',
				__( 'Schema must be valid JSON.', 'allcoach' ),
				[ 'status' => 400 ]
			);
		}

		if ( isset( $decoded['fields'] ) && ! is_array( $decoded['fields'] ) ) {
			return new \WP_Error(
				'invalid_schema',
				__( 'Schema fields must be an array.', 'allcoach' ),
				[ 'status' => 400 ]
			);
		}

		return true;
	}

	private function prepareActivity( ActivityTemplate $activity ): array {
		return [
			'id'           => $activity->getId(),
			'title'        => $activity->getTitle(),
			'description'  => $activity->getDescription(),
			'schema'       => $activity->getSchema()->toArray(),
			'coach_id'     => $activity->getCoachId(),
			'is_published' => $activity->isPublished(),
			'created_at'   => $activity->getCreatedAt()->format( 'c' ),
			'updated_at'   => $activity->getUpdatedAt()?->format( 'c' ),
		];
	}
}
