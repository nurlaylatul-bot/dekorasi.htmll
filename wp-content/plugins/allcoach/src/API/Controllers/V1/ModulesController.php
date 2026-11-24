<?php

declare(strict_types=1);

namespace AllCoach\API\Controllers\V1;

defined( 'ABSPATH' ) || exit;

use AllCoach\API\RestController;
use AllCoach\Modules\AddonInterface;
use AllCoach\Modules\CoreModule;
use AllCoach\Modules\ModuleInterface;
use AllCoach\Modules\ModuleRegistry;
use AllCoach\Traits\Hooks;

class ModulesController extends RestController {

	use Hooks;

	protected string $restBase = 'modules';

	public function __construct(
		private readonly ModuleRegistry $moduleRegistry
	) {
	}

	public function registerRoutes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->restBase,
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'getModules' ],
					'permission_callback' => fn() => $this->checkPermission( null, 'manage_options' ),
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->restBase . '/(?P<id>[a-zA-Z0-9_-]+)',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'getModule' ],
					'permission_callback' => fn() => $this->checkPermission( null, 'manage_options' ),
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->restBase . '/(?P<id>[a-zA-Z0-9_-]+)/enable',
			[
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'enableModule' ],
					'permission_callback' => fn() => $this->checkPermission( null, 'manage_options' ),
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->restBase . '/(?P<id>[a-zA-Z0-9_-]+)/disable',
			[
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'disableModule' ],
					'permission_callback' => fn() => $this->checkPermission( null, 'manage_options' ),
				],
			]
		);
	}

	public function getModules( \WP_REST_Request $request ): \WP_REST_Response {
		$modules = [];
		foreach ( $this->moduleRegistry->getAllModules() as $id => $module ) {
			$modules[] = $this->prepareModule( $id, $module );
		}

		return $this->successResponse( [ 'modules' => $modules ] );
	}

	public function getModule( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$id = (string) $request->get_param( 'id' );

		$module = $this->moduleRegistry->getModule( $id );
		if ( ! $module ) {
			return $this->notFoundResponse( __( 'Module', 'allcoach' ) );
		}

		return $this->successResponse( $this->prepareModule( $id, $module ) );
	}

	public function enableModule( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$id     = (string) $request->get_param( 'id' );
		$module = $this->moduleRegistry->getModule( $id );

		if ( ! $module ) {
			return $this->notFoundResponse( __( 'Module', 'allcoach' ) );
		}

		if ( ! $this->isToggleable( $module ) ) {
			return $this->errorResponse( __( 'Core features cannot be turned off.', 'allcoach' ), 400 );
		}

		/**
		 * Gate activation (e.g. license/plan checks). Add-ons return a list of
		 * error arrays `{ code, message, upgrade_url }`; a non-empty list blocks
		 * activation with a 403.
		 *
		 * @param array<int, array<string, mixed>> $errors
		 */
		$errors = (array) $this->applyFilters( 'allcoach:module:activation_requirements', [], $id, $module );
		if ( $errors !== [] ) {
			$first = $errors[0];
			return new \WP_Error(
				(string) ( $first['code'] ?? 'activation_blocked' ),
				(string) ( $first['message'] ?? __( 'This module cannot be activated.', 'allcoach' ) ),
				[
					'status'      => 403,
					'upgrade_url' => $first['upgrade_url'] ?? '',
					'errors'      => $errors,
				]
			);
		}

		$this->moduleRegistry->enableModule( $id );
		if ( $module instanceof AddonInterface ) {
			$module->activate();
		}

		return $this->successResponse(
			[
				'message' => sprintf(
					/* translators: %s: module name */
					__( '%s activated.', 'allcoach' ),
					$module->getName()
				),
				'module'  => $this->prepareModule( $id, $module ),
			]
		);
	}

	public function disableModule( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$id     = (string) $request->get_param( 'id' );
		$module = $this->moduleRegistry->getModule( $id );

		if ( ! $module ) {
			return $this->notFoundResponse( __( 'Module', 'allcoach' ) );
		}

		if ( ! $this->isToggleable( $module ) ) {
			return $this->errorResponse( __( 'Core features cannot be turned off.', 'allcoach' ), 400 );
		}

		if ( $module instanceof AddonInterface ) {
			$module->deactivate();
		}
		$this->moduleRegistry->disableModule( $id );

		return $this->successResponse(
			[
				'message' => sprintf(
					/* translators: %s: module name */
					__( '%s deactivated.', 'allcoach' ),
					$module->getName()
				),
				'module'  => $this->prepareModule( $id, $module ),
			]
		);
	}

	private function isToggleable( ModuleInterface $module ): bool {
		return $module instanceof AddonInterface && ! $module instanceof CoreModule;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function prepareModule( string $id, ModuleInterface $module ): array {
		if ( $module instanceof AddonInterface ) {
			$data            = $module->toArray();
			$data['enabled'] = $module->isEnabled();

			// Locked = a paid addon this site can't activate (no valid Pro
			// license). Requirements are contributed by Pro via the same filter
			// used at activation; presence of any requirement error means "locked".
			$errors           = (array) $this->applyFilters( 'allcoach:module:activation_requirements', [], $id, $module );
			$data['locked']   = ! $module->isEnabled() && $errors !== [];
			$data['upgrade_url'] = $errors !== [] ? (string) ( $errors[0]['upgrade_url'] ?? '' ) : '';
			return $data;
		}

		// Core / legacy modules: minimal, non-toggleable payload.
		return [
			'id'          => $id,
			'name'        => $module->getName(),
			'description' => $module->getDescription(),
			'version'     => $module->getVersion(),
			'category'    => 'core',
			'pro'         => false,
			'requires'    => [],
			'icon'        => 'admin-generic',
			'thumbnail'   => '',
			'docs_url'    => '',
			'settings_route' => '',
			'type'        => 'core',
			'toggleable'  => false,
			'default'     => true,
			'enabled'     => $module->isEnabled(),
			'locked'      => false,
			'upgrade_url' => '',
		];
	}
}
