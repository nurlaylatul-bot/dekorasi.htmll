<?php

declare(strict_types=1);

namespace AllCoach\API;

defined( 'ABSPATH' ) || exit;

trait BulkActionsTrait {

	/**
	 * @return string[]
	 */
	abstract protected function supportedBulkActions(): array;

	/**
	 * @return array{id: int, success: bool, error?: string}
	 */
	abstract protected function executeBulkAction( string $action, int $id ): array;

	abstract protected function bulkPermissionCallback(): bool;

	protected function registerBulkRoute(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->restBase . '/bulk',
			[
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'handleBulk' ],
					'permission_callback' => fn() => $this->bulkPermissionCallback(),
					'args'                => $this->bulkArgs(),
				],
			]
		);
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	protected function bulkArgs(): array {
		return [
			'action' => [
				'description'       => __( 'The bulk action to perform.', 'allcoach' ),
				'type'              => 'string',
				'required'          => true,
				'enum'              => $this->supportedBulkActions(),
				'sanitize_callback' => 'sanitize_key',
				'validate_callback' => 'rest_validate_request_arg',
			],
			'ids'    => [
				'description'       => __( 'Array of resource IDs to act on.', 'allcoach' ),
				'type'              => 'array',
				'required'          => true,
				'items'             => [
					'type'    => 'integer',
					'minimum' => 1,
				],
				'minItems'          => 1,
				'maxItems'          => 100,
				'sanitize_callback' => fn( $ids ) => array_map( 'absint', (array) $ids ),
				'validate_callback' => 'rest_validate_request_arg',
			],
		];
	}

	public function handleBulk( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$action = $request->get_param( 'action' );
		$ids    = $request->get_param( 'ids' );

		$results   = [];
		$processed = 0;
		$failed    = 0;

		foreach ( $ids as $id ) {
			$result    = $this->executeBulkAction( $action, (int) $id );
			$results[] = $result;

			if ( $result['success'] ) {
				++$processed;
			} else {
				++$failed;
			}
		}

		return $this->successResponse(
			[
				'action'    => $action,
				'processed' => $processed,
				'failed'    => $failed,
				'results'   => $results,
			]
		);
	}
}
