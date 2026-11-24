<?php

declare(strict_types=1);

namespace AllCoach\API\Controllers\V1;

defined( 'ABSPATH' ) || exit;

use AllCoach\API\RestController;
use AllCoach\Application\Auth\LoginDTO;
use AllCoach\Application\Auth\LoginService;
use AllCoach\Core\RoleManager;

class AuthController extends RestController {

	protected string $restBase = 'auth';

	public function __construct(
		private readonly LoginService $loginService,
	) {}

	public function registerRoutes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->restBase . '/login',
			[
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'login' ],
					'permission_callback' => '__return_true',
					'args'                => [
						'login'    => $this->argString(
							description: __( 'Username or email.', 'allcoach' ),
							required: true,
						),
						'password' => [
							'description'       => __( 'Account password.', 'allcoach' ),
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => fn( $v ) => (string) $v,
							'validate_callback' => 'rest_validate_request_arg',
						],
						'role'     => $this->argEnum(
							description: __( 'Expected role for this login (client or coach).', 'allcoach' ),
							values: [ 'client', 'coach' ],
							required: false,
						),
					],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->restBase . '/logout',
			[
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'logout' ],
					'permission_callback' => fn() => is_user_logged_in(),
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->restBase . '/me',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'me' ],
					'permission_callback' => fn() => is_user_logged_in(),
				],
			]
		);
	}

	public function login( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$data = $request->get_params();

		try {
			$dto  = LoginDTO::fromArray( $data );
			$user = $this->loginService->execute( $dto );

			return $this->successResponse(
				[
					'id'    => $user->ID,
					'name'  => $user->display_name,
					'email' => $user->user_email,
					'roles' => (array) $user->roles,
				]
			);
		} catch ( \Throwable $e ) {
			return $this->exceptionToResponse( $e );
		}
	}

	public function logout( \WP_REST_Request $request ): \WP_REST_Response {
		wp_logout();
		return $this->successResponse(
			[
				'logged_out' => true,
			]
		);
	}

	public function me( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$user = wp_get_current_user();
		if ( ! $user || ! $user->exists() ) {
			return $this->forbiddenResponse();
		}

		return $this->successResponse(
			[
				'id'        => $user->ID,
				'name'      => $user->display_name,
				'email'     => $user->user_email,
				'roles'     => (array) $user->roles,
				'is_client' => in_array( RoleManager::ROLE_CLIENT, (array) $user->roles, true ),
				'is_coach'  => in_array( RoleManager::ROLE_COACH, (array) $user->roles, true ),
			]
		);
	}
}
