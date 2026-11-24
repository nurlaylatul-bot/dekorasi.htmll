<?php

declare(strict_types=1);

namespace AllCoach\API\Controllers\V1;

defined( 'ABSPATH' ) || exit;

use AllCoach\API\RestController;
use AllCoach\Core\Settings\SettingsManager;

class SettingsController extends RestController {

	protected string $restBase = 'settings';

	public function __construct(
		private readonly SettingsManager $settingsManager,
	) {}

	public function registerRoutes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->restBase,
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'getSettings' ],
					'permission_callback' => [ $this, 'permManageSettings' ],
					'args'                => [],
				],
				[
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'updateSettings' ],
					'permission_callback' => [ $this, 'permManageSettings' ],
					'args'                => $this->updateArgs(),
				],
				'schema' => [ $this, 'getItemSchema' ],
			]
		);
	}

	public function permManageSettings(): bool {
		return current_user_can( 'manage_options' );
	}

	public function getSettings( \WP_REST_Request $request ): \WP_REST_Response {
		return $this->successResponse( $this->settingsManager->all() );
	}

	public function updateSettings( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$body    = $request->get_params();
		$schema  = $this->settingsManager->getSchema();
		$allowed = array_intersect_key( $body, $schema );
		if ( empty( $allowed ) ) {
			return $this->successResponse( $this->settingsManager->all() );
		}
		$this->settingsManager->setMultiple( $allowed );
		return $this->successResponse( $this->settingsManager->all() );
	}

	private function updateArgs(): array {
		$args = [];
		foreach ( $this->settingsManager->getSchema() as $key => $def ) {
			$arg = [
				'description' => $def['description'] ?? $key,
				'type'        => $def['type'],
			];
			if ( array_key_exists( 'default', $def ) ) {
				$arg['default'] = $def['default'];
			}
			if ( $def['type'] === 'integer' ) {
				$arg['minimum']           = $def['minimum'] ?? 0;
				$arg['maximum']           = $def['maximum'] ?? 1000;
				$arg['sanitize_callback'] = 'absint';
			}
			if ( $def['type'] === 'boolean' ) {
				$arg['sanitize_callback'] = 'rest_sanitize_boolean';
			}
			if ( $def['type'] === 'string' ) {
				$arg['sanitize_callback'] = $def['sanitize_callback'] ?? 'sanitize_text_field';
			}
			$arg['validate_callback'] = 'rest_validate_request_arg';
			$args[ $key ]             = $arg;
		}
		return $args;
	}

	public function getItemSchema(): array {
		$properties = [];
		foreach ( $this->settingsManager->getSchema() as $key => $def ) {
			$properties[ $key ] = [
				'description' => $def['description'] ?? $key,
				'type'        => $def['type'],
				'context'     => [ 'view', 'edit' ],
			];
			if ( array_key_exists( 'default', $def ) ) {
				$properties[ $key ]['default'] = $def['default'];
			}
			if ( isset( $def['minimum'] ) ) {
				$properties[ $key ]['minimum'] = $def['minimum'];
			}
			if ( isset( $def['maximum'] ) ) {
				$properties[ $key ]['maximum'] = $def['maximum'];
			}
		}
		return [
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'allcoach_settings',
			'type'       => 'object',
			'properties' => $properties,
		];
	}
}
