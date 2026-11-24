<?php

declare(strict_types=1);

namespace AllCoach\API\Controllers\V1;

defined( 'ABSPATH' ) || exit;

use AllCoach\API\BulkActionsTrait;
use AllCoach\API\RestController;
use AllCoach\Application\Client\ClientDTO;
use AllCoach\Application\Client\CreateClientService;
use AllCoach\Application\Client\UpdateClientService;
use AllCoach\Application\Client\DeleteClientService;
use AllCoach\Domain\Client\Client;
use AllCoach\Domain\Client\ClientFilter;
use AllCoach\Domain\Client\ClientRepository;

class ClientsController extends RestController {

	use BulkActionsTrait;

	protected string $restBase = 'clients';

	public function __construct(
		private readonly ClientRepository $repository,
		private readonly CreateClientService $createService,
		private readonly UpdateClientService $updateService,
		private readonly DeleteClientService $deleteService,
	) {
	}

	public function registerRoutes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->restBase,
			[
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'createClient' ],
					'permission_callback' => fn() => $this->checkPermission( null, 'edit_allcoach_clients' ),
				],
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'getClients' ],
					'permission_callback' => fn() => $this->checkPermission( null, 'manage_allcoach_clients' ),
					'args'                => array_merge( $this->paginationArgs(), $this->contextArg(), [ 'search' => $this->argString( description: __( 'Search by name or email.', 'allcoach' ) ) ] ),
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
					'callback'            => [ $this, 'getClient' ],
					'permission_callback' => fn() => $this->checkPermission( null, 'manage_allcoach_clients' ),
					'args'                => array_merge( $this->idArg(), $this->contextArg() ),
				],
				[
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'deleteClient' ],
					'permission_callback' => fn() => $this->checkPermission( null, 'delete_allcoach_clients' ),
				],
				[
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'updateClient' ],
					'permission_callback' => fn() => $this->checkPermission( null, 'edit_allcoach_clients' ),
				],
				'schema' => [ $this, 'getItemSchema' ],
			]
		);

		$this->registerBulkRoute();
	}

	protected function supportedBulkActions(): array {
		return [ 'delete' ];
	}

	protected function bulkPermissionCallback(): bool {
		return $this->checkPermission( null, 'delete_allcoach_clients' );
	}

	protected function executeBulkAction( string $action, int $id ): array {
		try {
			match ( $action ) {
				'delete' => $this->deleteService->execute( $id ),
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

	public function getClients( \WP_REST_Request $request ): \WP_REST_Response {
		$page    = max( 1, (int) ( $request->get_param( 'page' ) ?? 1 ) );
		$perPage = min( 100, max( 1, (int) ( $request->get_param( 'per_page' ) ?? 20 ) ) );
		$offset  = ( $page - 1 ) * $perPage;
		$search  = sanitize_text_field( (string) ( $request->get_param( 'search' ) ?? '' ) );
		$context = $this->getEffectiveContext( $request, 'manage_allcoach_clients' );
		$schema  = $this->getItemSchema();

		$filter  = $this->buildFilter( $search );
		$clients = $this->repository->findWithFilter( $filter, $perPage, $offset );
		$total   = $this->repository->countWithFilter( $filter );

		$items = array_map(
			fn( Client $c ) => $this->filterDataByContext( $this->prepareClient( $c ), $schema, $context ),
			$clients
		);

		return $this->successResponse(
			[
				'clients'  => $items,
				'total'    => $total,
				'page'     => $page,
				'per_page' => $perPage,
			]
		);
	}

	private function buildFilter( string $search ): ClientFilter {
		$coachId = null;

		if ( $this->isCoach() ) {
			$coachId = get_current_user_id();
		}

		return new ClientFilter(
			coachId: $coachId,
			search: empty( $search ) ? null : $search,
		);
	}

	public function getClient( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$id     = (int) $request->get_param( 'id' );
		$client = $this->repository->findById( $id );

		if ( ! $client ) {
			return $this->errorResponse( 'Client not found', 404 );
		}

		$context = $this->getEffectiveContext( $request, 'manage_allcoach_clients' );
		$data    = $this->filterDataByContext( $this->prepareClient( $client ), $this->getItemSchema(), $context );

		return $this->successResponse( $data );
	}

	public function createClient( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$data = $request->get_params();

		$validation = $this->validateRequired( $data, [ 'email' ] );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		try {
			$dto    = ClientDTO::fromArray( $data );
			$client = $this->createService->execute( $dto );

			return $this->successResponse( $this->prepareClient( $client ), 201 );
		} catch ( \Throwable $e ) {
			return $this->exceptionToResponse( $e );
		}
	}

	public function updateClient( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$id   = (int) $request->get_param( 'id' );
		$data = $request->get_params();

		try {
			$dto    = ClientDTO::fromArray( $data );
			$client = $this->updateService->execute( $id, $dto );

			return $this->successResponse( $this->prepareClient( $client ) );
		} catch ( \Throwable $e ) {
			return $this->exceptionToResponse( $e );
		}
	}

	public function deleteClient( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$id = (int) $request->get_param( 'id' );

		try {
			$this->deleteService->execute( $id );
		} catch ( \Throwable $e ) {
			return $this->exceptionToResponse( $e );
		}

		return $this->successResponse( [ 'deleted' => true ] );
	}

	private function prepareClient( Client $client ): array {
		return [
			'id'         => $client->getId(),
			'first_name' => $client->getFirstName(),
			'last_name'  => $client->getLastName(),
			'name'       => $client->getName(),
			'email'      => $client->getEmail(),
			'phone'      => $client->getPhone(),
			'timezone'   => $client->getTimezone(),
			'status'     => $client->getStatus()->getValue(),
			'notes'      => $client->getNotes(),
			'created_at' => $client->getCreatedAt()->format( 'c' ),
			'updated_at' => $client->getUpdatedAt()?->format( 'c' ),
			'is_demo'    => $client->isDemo(),
		];
	}

	public function getItemSchema(): array {
		return [
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'allcoach_client',
			'type'       => 'object',
			'properties' => [
				'id'         => [
					'description' => __( 'Unique identifier.', 'allcoach' ),
					'type'        => 'integer',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'first_name' => [
					'description' => __( 'Client first name.', 'allcoach' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit' ],
				],
				'last_name'  => [
					'description' => __( 'Client last name.', 'allcoach' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit' ],
				],
				'name'       => [
					'description' => __( 'Client full name.', 'allcoach' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'email'      => [
					'description' => __( 'Client email.', 'allcoach' ),
					'type'        => 'string',
					'format'      => 'email',
					'context'     => [ 'view', 'edit' ],
				],
				'phone'      => [
					'description' => __( 'Client phone number.', 'allcoach' ),
					'type'        => [ 'string', 'null' ],
					'context'     => [ 'view', 'edit' ],
				],
				'timezone'   => [
					'description' => __( 'Client timezone (IANA, e.g. America/New_York).', 'allcoach' ),
					'type'        => [ 'string', 'null' ],
					'context'     => [ 'view', 'edit' ],
				],
				'status'     => [
					'description' => __( 'Client status.', 'allcoach' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit' ],
				],
				'notes'      => [
					'description' => __( 'Internal notes.', 'allcoach' ),
					'type'        => [ 'string', 'null' ],
					'context'     => [ 'edit' ],
				],
				'created_at' => [
					'description' => __( 'Creation date (ISO 8601).', 'allcoach' ),
					'type'        => 'string',
					'format'      => 'date-time',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'updated_at' => [
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
