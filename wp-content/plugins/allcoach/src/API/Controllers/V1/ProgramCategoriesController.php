<?php

declare(strict_types=1);

namespace AllCoach\API\Controllers\V1;

defined( 'ABSPATH' ) || exit;

use AllCoach\API\RestController;

class ProgramCategoriesController extends RestController {

	protected string $restBase = 'program-categories';

	private const TAXONOMY = 'allcoach_program_cat';

	public function registerRoutes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->restBase,
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'getProgramCategories' ],
					'permission_callback' => '__return_true',
					'args'                => array_merge( $this->paginationArgs( defaultPerPage: 50, maxPerPage: 100 ), $this->contextArg() ),
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'createProgramCategory' ],
					'permission_callback' => [ $this, 'permCoachOrAdmin' ],
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
					'callback'            => [ $this, 'getProgramCategory' ],
					'permission_callback' => '__return_true',
					'args'                => array_merge( $this->idArg( __( 'Program category term ID.', 'allcoach' ) ), $this->contextArg() ),
				],
				[
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'updateProgramCategory' ],
					'permission_callback' => [ $this, 'permCoachOrAdmin' ],
					'args'                => array_merge( $this->idArg( __( 'Program category term ID.', 'allcoach' ) ), $this->updateArgs() ),
				],
				[
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'deleteProgramCategory' ],
					'permission_callback' => [ $this, 'permCoachOrAdmin' ],
					'args'                => $this->idArg( __( 'Program category term ID.', 'allcoach' ) ),
				],
				'schema' => [ $this, 'getPublicItemSchema' ],
			]
		);
	}

	public function getPublicItemSchema(): array {
		return [
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => self::TAXONOMY,
			'type'       => 'object',
			'properties' => [
				'id'          => [
					'description' => __( 'Term ID.', 'allcoach' ),
					'type'        => 'integer',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'name'        => [
					'description' => __( 'Program category name.', 'allcoach' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'slug'        => [
					'description' => __( 'URL slug.', 'allcoach' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'description' => [
					'description' => __( 'Term description.', 'allcoach' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'count'       => [
					'description' => __( 'Number of programs using this category.', 'allcoach' ),
					'type'        => 'integer',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'parent'      => [
					'description' => __( 'Parent term ID (0 if top-level).', 'allcoach' ),
					'type'        => 'integer',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
			],
		];
	}

	private function createArgs(): array {
		return [
			'name'        => $this->argString(
				description: __( 'Program category name.', 'allcoach' ),
				required:    true,
				minLength:   1,
				maxLength:   200,
			),
			'slug'        => $this->argString(
				description: __( 'URL slug. Auto-generated from name if omitted.', 'allcoach' ),
				maxLength:   200,
			),
			'description' => $this->argString(
				description: __( 'Optional description.', 'allcoach' ),
				sanitize:    'wp_kses_post',
				default:     '',
			),
			'parent'      => $this->argInteger(
				description: __( 'Parent term ID for hierarchy. 0 or omit for top-level.', 'allcoach' ),
				min:         0,
				default:     0,
			),
		];
	}

	private function updateArgs(): array {
		return [
			'name'        => $this->argString(
				description: __( 'Program category name.', 'allcoach' ),
				minLength:   1,
				maxLength:   200,
			),
			'slug'        => $this->argString(
				description: __( 'URL slug.', 'allcoach' ),
				maxLength:   200,
			),
			'description' => $this->argString(
				description: __( 'Optional description.', 'allcoach' ),
				sanitize:    'wp_kses_post',
			),
			'parent'      => $this->argInteger(
				description: __( 'Parent term ID. 0 for top-level.', 'allcoach' ),
				min:         0,
			),
		];
	}

	public function permCoachOrAdmin(): bool {
		return is_user_logged_in() && ( $this->isCoach() || $this->isAdmin() );
	}

	public function getProgramCategories( \WP_REST_Request $request ): \WP_REST_Response {
		$page    = (int) $request->get_param( 'page' );
		$perPage = (int) $request->get_param( 'per_page' );
		$offset  = ( $page - 1 ) * $perPage;

		$terms = get_terms(
			[
				'taxonomy'   => self::TAXONOMY,
				'hide_empty' => false,
				'number'     => $perPage,
				'offset'     => $offset,
				'orderby'    => 'name',
				'order'      => 'ASC',
			]
		);

		if ( is_wp_error( $terms ) ) {
			return $this->successResponse(
				[
					'program_categories' => [],
					'total'              => 0,
					'page'               => $page,
					'per_page'           => $perPage,
				]
			);
		}

		$total = wp_count_terms(
			[
				'taxonomy'   => self::TAXONOMY,
				'hide_empty' => false,
			]
		);
		if ( is_wp_error( $total ) ) {
			$total = count( $terms );
		}

		$context = $this->getEffectiveContext( $request, [ $this, 'permCoachOrAdmin' ] );
		$schema  = $this->getPublicItemSchema();
		$items   = array_map(
			fn( $term ) => $this->filterDataByContext( $this->prepareTerm( $term ), $schema, $context ),
			$terms
		);

		return $this->successResponse(
			[
				'program_categories' => $items,
				'total'              => (int) $total,
				'page'               => $page,
				'per_page'           => $perPage,
			]
		);
	}

	public function getProgramCategory( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$id   = (int) $request->get_param( 'id' );
		$term = get_term( $id, self::TAXONOMY );

		if ( ! $term || is_wp_error( $term ) ) {
			return $this->notFoundResponse( __( 'Program category', 'allcoach' ) );
		}

		$context = $this->getEffectiveContext( $request, [ $this, 'permCoachOrAdmin' ] );
		$data    = $this->filterDataByContext( $this->prepareTerm( $term ), $this->getPublicItemSchema(), $context );

		return $this->successResponse( $data );
	}

	public function createProgramCategory( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$name        = (string) $request->get_param( 'name' );
		$slug        = $request->get_param( 'slug' );
		$description = (string) $request->get_param( 'description' );
		$parent      = (int) $request->get_param( 'parent' );

		$args = [
			'description' => $description,
			'parent'      => $parent > 0 ? $parent : 0,
		];
		if ( $slug !== null && $slug !== '' ) {
			$args['slug'] = sanitize_title( $slug );
		}

		$result = wp_insert_term( $name, self::TAXONOMY, $args );

		if ( is_wp_error( $result ) ) {
			return $this->errorResponse( $result->get_error_message(), 400 );
		}

		$term = get_term( (int) $result['term_id'], self::TAXONOMY );
		if ( ! $term || is_wp_error( $term ) ) {
			return $this->errorResponse( __( 'Program category created but could not be retrieved.', 'allcoach' ), 500 );
		}

		return $this->successResponse( $this->prepareTerm( $term ), 201 );
	}

	public function updateProgramCategory( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$id   = (int) $request->get_param( 'id' );
		$term = get_term( $id, self::TAXONOMY );

		if ( ! $term || is_wp_error( $term ) ) {
			return $this->notFoundResponse( __( 'Program category', 'allcoach' ) );
		}

		$args = [];
		$name = $request->get_param( 'name' );
		if ( $name !== null && $name !== '' ) {
			$args['name'] = (string) $name;
		}
		$slug = $request->get_param( 'slug' );
		if ( $slug !== null && $slug !== '' ) {
			$args['slug'] = sanitize_title( $slug );
		}
		if ( $request->get_param( 'description' ) !== null ) {
			$args['description'] = (string) $request->get_param( 'description' );
		}
		if ( $request->get_param( 'parent' ) !== null ) {
			$args['parent'] = max( 0, (int) $request->get_param( 'parent' ) );
		}

		if ( $args === [] ) {
			return $this->successResponse( $this->prepareTerm( $term ) );
		}

		$result = wp_update_term( $id, self::TAXONOMY, $args );

		if ( is_wp_error( $result ) ) {
			return $this->errorResponse( $result->get_error_message(), 400 );
		}

		$updated = get_term( (int) $result['term_id'], self::TAXONOMY );
		if ( ! $updated || is_wp_error( $updated ) ) {
			return $this->successResponse( $this->prepareTerm( $term ) );
		}

		return $this->successResponse( $this->prepareTerm( $updated ) );
	}

	public function deleteProgramCategory( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$id   = (int) $request->get_param( 'id' );
		$term = get_term( $id, self::TAXONOMY );

		if ( ! $term || is_wp_error( $term ) ) {
			return $this->notFoundResponse( __( 'Program category', 'allcoach' ) );
		}

		$result = wp_delete_term( $id, self::TAXONOMY );

		if ( is_wp_error( $result ) ) {
			return $this->errorResponse( $result->get_error_message(), 400 );
		}

		if ( ! $result ) {
			return $this->errorResponse( __( 'Could not delete program category.', 'allcoach' ), 400 );
		}

		return $this->successResponse(
			[
				'deleted' => true,
				'id'      => $id,
			],
			200
		);
	}

	/**
	 * @param \WP_Term $term
	 * @return array{id: int, name: string, slug: string, description: string, count: int, parent: int}
	 */
	private function prepareTerm( \WP_Term $term ): array {
		return [
			'id'          => $term->term_id,
			'name'        => $term->name,
			'slug'        => $term->slug,
			'description' => $term->description ?? '',
			'count'       => (int) $term->count,
			'parent'      => (int) $term->parent,
		];
	}
}
