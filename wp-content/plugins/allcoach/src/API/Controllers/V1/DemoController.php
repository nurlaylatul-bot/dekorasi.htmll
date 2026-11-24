<?php

declare(strict_types=1);

namespace AllCoach\API\Controllers\V1;

defined( 'ABSPATH' ) || exit;

use AllCoach\API\RestController;
use AllCoach\Frontend\Router;
use AllCoach\Modules\Demo\DemoClientManager;
use AllCoach\Modules\Demo\DemoOrderService;
use AllCoach\Modules\Demo\DemoSessionService;

class DemoController extends RestController {

	protected string $restBase = 'demo';

	public function __construct(
		private readonly DemoClientManager $demoClientManager,
		private readonly DemoSessionService $demoSessionService,
		private readonly DemoOrderService $demoOrderService,
	) {}

	public function registerRoutes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->restBase . '/start',
			[
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'startDemo' ],
					'permission_callback' => fn() => $this->permCoachOrAdmin(),
					'args'                => [
						'program_id' => [
							'type'              => 'integer',
							'description'       => __( 'Program to enroll the demo client in (optional).', 'allcoach' ),
							'required'          => false,
							'minimum'           => 1,
							'sanitize_callback' => 'absint',
						],
					],
				],
			]
		);
	}

	public function startDemo( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$programId = (int) ( $request->get_param( 'program_id' ) ?? 0 );

		try {
			$demoUser = $this->demoClientManager->getOrCreateDemoUser();

			if ( $programId > 0 ) {
				if ( $this->demoOrderService->hasDemoEnrollment( $demoUser->ID, $programId ) ) {
					$redirectUrl = Router::url( 'programs', $programId );
				} else {
					$token = wp_generate_password( 48, false );
					$this->demoOrderService->createForProgram( $demoUser->ID, $programId, $token );
					$redirectUrl = Router::url( 'programs', $programId );
				}

				$magicLink = $this->demoSessionService->generateMagicLink( get_current_user_id(), $redirectUrl );
			} else {
				$magicLink = $this->demoSessionService->generateMagicLink( get_current_user_id() );
			}

			return $this->successResponse( [ 'url' => $magicLink ] );
		} catch ( \Throwable $e ) {
			return $this->errorResponse( $e->getMessage(), 500 );
		}
	}
}
