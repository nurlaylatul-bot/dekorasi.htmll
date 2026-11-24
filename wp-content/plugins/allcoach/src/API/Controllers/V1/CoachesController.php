<?php

declare(strict_types=1);

namespace AllCoach\API\Controllers\V1;

defined( 'ABSPATH' ) || exit;

use AllCoach\API\RestController;
use AllCoach\Application\Coach\CoachDTO;
use AllCoach\Application\Coach\CoachQueryService;
use AllCoach\Application\Coach\CreateCoachService;
use AllCoach\Application\Coach\UpdateCoachService;
use AllCoach\Domain\Coach\Coach;
use AllCoach\Domain\Coach\CoachFilter;

class CoachesController extends RestController {

	protected string $restBase = 'coaches';

	public function __construct(
		private readonly CoachQueryService $queryService,
		private readonly CreateCoachService $createService,
		private readonly UpdateCoachService $updateService
	) {
	}

	public function registerRoutes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->restBase,
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'getCoaches' ],
					'permission_callback' => fn() => $this->checkPermission( null, 'manage_allcoach_coaches' ),
					'args'                => array_merge(
						$this->paginationArgs(),
						$this->contextArg(),
						[
							'search' => $this->argString(
								description: __( 'Search coaches by name or email.', 'allcoach' ),
							),
						]
					),
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'createCoach' ],
					'permission_callback' => fn() => $this->checkPermission( null, 'manage_options' ),
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
					'callback'            => [ $this, 'getCoach' ],
					'permission_callback' => fn() => $this->checkPermission( null, 'manage_allcoach_coaches' ),
					'args'                => array_merge( $this->idArg(), $this->contextArg() ),
				],
				[
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'updateCoach' ],
					'permission_callback' => fn() => $this->checkPermission( null, 'manage_options' ),
				],
				[
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'deleteCoach' ],
					'permission_callback' => fn() => $this->checkPermission( null, 'manage_options' ),
				],
				'schema' => [ $this, 'getItemSchema' ],
			]
		);
	}

	public function getCoaches( \WP_REST_Request $request ): \WP_REST_Response {
		$page    = max( 1, (int) ( $request->get_param( 'page' ) ?? 1 ) );
		$perPage = min( 100, max( 1, (int) ( $request->get_param( 'per_page' ) ?? 20 ) ) );
		$search  = sanitize_text_field( (string) ( $request->get_param( 'search' ) ?? '' ) );

		$filter  = new CoachFilter(
			search: $search !== '' ? $search : null,
		);
		$coaches = $this->queryService->findWithFilter( $filter, $page, $perPage );
		$total   = $this->queryService->getTotalCount();

		$context = $this->getEffectiveContext( $request, 'manage_allcoach_coaches' );
		$schema  = $this->getItemSchema();
		$items   = array_map(
			fn( Coach $c ) => $this->filterDataByContext( $c->toArray(), $schema, $context ),
			$coaches
		);

		return $this->successResponse(
			[
				'coaches'  => $items,
				'total'    => $total,
				'page'     => $page,
				'per_page' => $perPage,
			]
		);
	}

	public function getCoach( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$coach = $this->queryService->findById( (int) $request->get_param( 'id' ) );
		if ( $coach === null ) {
			return $this->errorResponse( 'Coach not found.', 404 );
		}
		$context = $this->getEffectiveContext( $request, 'manage_allcoach_coaches' );
		$data    = $this->filterDataByContext( $coach->toArray(), $this->getItemSchema(), $context );
		return $this->successResponse( $data );
	}

	public function createCoach( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		try {
			$dto   = CoachDTO::fromArray( $request->get_params() ?: [] );
			$coach = $this->createService->execute( $dto );
			return $this->successResponse( $coach->toArray(), 201 );
		} catch ( \Throwable $e ) {
			return $this->exceptionToResponse( $e );
		}
	}

	public function updateCoach( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		try {
			$dto   = CoachDTO::fromArray( $request->get_params() ?: [] );
			$coach = $this->updateService->execute( (int) $request->get_param( 'id' ), $dto );
			return $this->successResponse( $coach->toArray() );
		} catch ( \Throwable $e ) {
			return $this->exceptionToResponse( $e );
		}
	}

	public function deleteCoach( \WP_REST_Request $request ): \WP_Error {
		return $this->errorResponse( 'Delete coaches via the WP admin panel.', 501 );
	}

	public function getItemSchema(): array {
		return [
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'allcoach_coach',
			'type'       => 'object',
			'properties' => [
				'id'         => [
					'description' => __( 'Unique identifier.', 'allcoach' ),
					'type'        => 'integer',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'user_id'    => [
					'description' => __( 'WordPress user ID.', 'allcoach' ),
					'type'        => 'integer',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'name'       => [
					'description' => __( 'Coach name.', 'allcoach' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit' ],
				],
				'email'      => [
					'description' => __( 'Coach email.', 'allcoach' ),
					'type'        => 'string',
					'format'      => 'email',
					'context'     => [ 'view', 'edit' ],
				],
				'bio'        => [
					'description' => __( 'Coach bio.', 'allcoach' ),
					'type'        => [ 'string', 'null' ],
					'context'     => [ 'edit' ],
				],
				'created_at' => [
					'description' => __( 'Creation date.', 'allcoach' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'updated_at' => [
					'description' => __( 'Last update date.', 'allcoach' ),
					'type'        => [ 'string', 'null' ],
					'context'     => [ 'edit' ],
					'readonly'    => true,
				],
			],
		];
	}
}
