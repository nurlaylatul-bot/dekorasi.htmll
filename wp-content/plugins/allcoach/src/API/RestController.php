<?php

declare(strict_types=1);

namespace AllCoach\API;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\Exceptions\NotFoundException;
use AllCoach\Core\Exceptions\ValidationException;
use AllCoach\Core\RoleManager;

abstract class RestController {

	protected string $namespace = 'allcoach/v1';
	protected string $restBase  = '';

	abstract public function registerRoutes(): void;

	protected function checkPermission( \WP_REST_Request|null $request, string $capability = 'manage_options' ): bool {
		return current_user_can( $capability );
	}

	protected function successResponse( mixed $data, int $status = 200 ): \WP_REST_Response {
		return new \WP_REST_Response(
			[
				'success' => true,
				'data'    => $data,
			],
			$status
		);
	}

	protected function errorResponse( string $message, int $status = 400 ): \WP_Error {
		return new \WP_Error( 'allcoach_error', $message, [ 'status' => $status ] );
	}

	protected function notFoundResponse( string $resource = '' ): \WP_Error {
		$message = $resource
			? sprintf(
				/* translators: %s: Resource name */
				__( '%s not found.', 'allcoach' ),
				$resource
			)
			: __( 'Resource not found.', 'allcoach' );
		return $this->errorResponse( $message, 404 );
	}

	protected function forbiddenResponse(): \WP_Error {
		return new \WP_Error(
			'allcoach_forbidden',
			__( 'You do not have permission to perform this action.', 'allcoach' ),
			[ 'status' => 403 ]
		);
	}

	protected function exceptionToResponse( \Throwable $e ): \WP_Error {
		if ( $e instanceof NotFoundException ) {
			return $this->errorResponse( $e->getMessage() ?: __( 'Resource not found.', 'allcoach' ), 404 );
		}
		if ( $e instanceof ValidationException ) {
			$data = [ 'errors' => $e->getErrors() ];
			return new \WP_Error(
				'allcoach_validation_error',
				$e->getMessage(),
				[
					'status'          => 422,
					'additional_data' => $data,
				]
			);
		}
		return $this->errorResponse( __( 'An error occurred.', 'allcoach' ), 500 );
	}

	protected function validateRequired( array $data, array $required ): bool|\WP_Error {
		foreach ( $required as $field ) {
			if ( ! isset( $data[ $field ] ) || $data[ $field ] === '' ) {
				return $this->errorResponse(
					sprintf(
						/* translators: %s: Field name */
						__( 'The %s field is required.', 'allcoach' ),
						$field
					),
					400
				);
			}
		}

		return true;
	}

	protected function canRead( int $postId ): bool {
		return current_user_can( 'read_post', $postId );
	}

	protected function canEdit( int $postId ): bool {
		return current_user_can( 'edit_posts', $postId );
	}

	protected function canCreate( string $postType ): bool {
		$obj = get_post_type_object( $postType );
		if ( ! $obj ) {
			return false;
		}
		return current_user_can( $obj->cap->create_posts );
	}

	protected function canDelete( int $postId ): bool {
		return current_user_can( 'delete_post', $postId );
	}

	protected function currentUserHasRole( string $role ): bool {
		$user = wp_get_current_user();
		return $user->exists() && in_array( $role, (array) $user->roles, true );
	}

	protected function isClient(): bool {
		return $this->currentUserHasRole( RoleManager::ROLE_CLIENT );
	}

	protected function isAdmin(): bool {
		return current_user_can( 'manage_options' );
	}

	protected function isCoach(): bool {
		return $this->currentUserHasRole( RoleManager::ROLE_COACH );
	}

	protected function permCoachOrAdmin(): bool {
		return is_user_logged_in() && ( $this->isCoach() || $this->isAdmin() );
	}

	protected function argInteger(
		string $description,
		bool $required = false,
		int $min = 0,
		?int $max = null,
		mixed $default = null,
	): array {
		$arg = [
			'description'       => $description,
			'type'              => 'integer',
			'required'          => $required,
			'minimum'           => $min,
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
		];
		if ( $max !== null ) {
			$arg['maximum'] = $max;
		}
		if ( $default !== null ) {
			$arg['default'] = $default;
		}
		return $arg;
	}

	protected function argString(
		string $description,
		bool $required = false,
		?int $minLength = null,
		?int $maxLength = null,
		string $sanitize = 'sanitize_text_field',
		mixed $default = null,
	): array {
		$arg = [
			'description'       => $description,
			'type'              => 'string',
			'required'          => $required,
			'sanitize_callback' => $sanitize,
			'validate_callback' => 'rest_validate_request_arg',
		];
		if ( $minLength !== null ) {
			$arg['minLength'] = $minLength;
		}
		if ( $maxLength !== null ) {
			$arg['maxLength'] = $maxLength;
		}
		if ( $default !== null ) {
			$arg['default'] = $default;
		}
		return $arg;
	}

	protected function argBoolean(
		string $description,
		bool $required = false,
		bool $default = false,
	): array {
		return [
			'description'       => $description,
			'type'              => 'boolean',
			'required'          => $required,
			'default'           => $default,
			'sanitize_callback' => 'rest_sanitize_boolean',
			'validate_callback' => 'rest_validate_request_arg',
		];
	}

	protected function argNumber(
		string $description,
		bool $required = false,
		float $min = 0.0,
		mixed $default = null,
		?callable $sanitize = null,
	): array {
		$arg = [
			'description'       => $description,
			'type'              => 'number',
			'required'          => $required,
			'minimum'           => $min,
			'sanitize_callback' => $sanitize ?? fn( $v ) => (float) $v,
			'validate_callback' => 'rest_validate_request_arg',
		];
		if ( $default !== null ) {
			$arg['default'] = $default;
		}
		return $arg;
	}

	protected function argEnum(
		string $description,
		array $values,
		bool $required = false,
		string|callable $sanitize = 'sanitize_key',
		mixed $default = null,
	): array {
		$arg = [
			'description'       => $description,
			'type'              => 'string',
			'required'          => $required,
			'enum'              => $values,
			'sanitize_callback' => $sanitize,
			'validate_callback' => 'rest_validate_request_arg',
		];
		if ( $default !== null ) {
			$arg['default'] = $default;
		}
		return $arg;
	}

	protected function paginationArgs( int $defaultPerPage = 20, int $maxPerPage = 100 ): array {
		return [
			'page'     => $this->argInteger(
				description: __( 'Current page of results.', 'allcoach' ),
				min:         1,
				default:     1,
			),
			'per_page' => $this->argInteger(
				description: sprintf(
					/* translators: %d: maximum items per page */
					__( 'Maximum results per page (max %d).', 'allcoach' ),
					$maxPerPage
				),
				min:         1,
				max:         $maxPerPage,
				default:     $defaultPerPage,
			),
		];
	}

	protected function idArg( string $description = '' ): array {
		return [
			'id' => array_filter(
				[
					'description'       => $description ?: __( 'Unique identifier for the resource.', 'allcoach' ),
					'type'              => 'integer',
					'required'          => true,
					'minimum'           => 1,
					'sanitize_callback' => 'absint',
					'validate_callback' => 'rest_validate_request_arg',
				]
			),
		];
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	protected function contextArg(): array {
		return [
			'context' => $this->argEnum(
				description: __( 'Scope under which the request is made; determines fields present in response.', 'allcoach' ),
				values:      [ 'view', 'edit', 'embed' ],
				default:     'view',
			),
		];
	}

	protected function getRequestContext( \WP_REST_Request $request ): string {
		$context = $request->get_param( 'context' );
		return in_array( $context, [ 'view', 'edit', 'embed' ], true ) ? $context : 'view';
	}

	/**
	 * @param \WP_REST_Request $request
	 * @param string|callable|null $editCapabilityOrCallback
	 * @return string 'view', 'edit', or 'embed'.
	 */
	protected function getEffectiveContext( \WP_REST_Request $request, string|callable|null $editCapabilityOrCallback ): string {
		$requested = $this->getRequestContext( $request );
		if ( $requested !== 'edit' ) {
			return $requested;
		}
		if ( ! is_user_logged_in() ) {
			return 'view';
		}
		if ( $editCapabilityOrCallback === null ) {
			return 'view';
		}
		if ( is_callable( $editCapabilityOrCallback ) ) {
			return $editCapabilityOrCallback( $request ) ? 'edit' : 'view';
		}
		return current_user_can( $editCapabilityOrCallback ) ? 'edit' : 'view';
	}

	/**
	 * @param array<string, mixed> $data
	 * @param array<string, mixed> $schema
	 * @param string $context
	 * @return array<string, mixed>
	 */
	protected function filterDataByContext( array $data, array $schema, string $context ): array {
		if ( ! function_exists( 'rest_filter_response_by_context' ) ) {
			return $data;
		}
		$filtered = rest_filter_response_by_context( $data, $schema, $context );
		return is_array( $filtered ) ? $filtered : $data;
	}
}
