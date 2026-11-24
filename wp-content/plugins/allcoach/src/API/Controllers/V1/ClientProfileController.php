<?php

declare(strict_types=1);

namespace AllCoach\API\Controllers\V1;

defined( 'ABSPATH' ) || exit;

use AllCoach\API\RestController;
use AllCoach\Application\Client\ClientDTO;
use AllCoach\Application\Client\UpdateClientService;
use AllCoach\Domain\Client\ClientRepository;

class ClientProfileController extends RestController {

	protected string $restBase = 'client';

	public function __construct(
		private readonly UpdateClientService $updateService,
		private readonly ClientRepository $clientRepository,
	) {}

	public function registerRoutes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->restBase . '/update-profile',
			[
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'updateProfile' ],
					'permission_callback' => fn() => $this->permClient(),
					'args'                => [
						'first_name' => $this->argString(
							description: __( 'Client first name.', 'allcoach' ),
						),
						'last_name'  => $this->argString(
							description: __( 'Client last name.', 'allcoach' ),
						),
						'email'      => $this->argString(
							description: __( 'Client email.', 'allcoach' ),
						),
						'phone'      => $this->argString(
							description: __( 'Client phone number.', 'allcoach' ),
						),
						'timezone'   => $this->argString(
							description: __( 'Client timezone (IANA identifier, e.g. America/New_York).', 'allcoach' ),
						),
					],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->restBase . '/change-password',
			[
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'changePassword' ],
					'permission_callback' => fn() => $this->permClient(),
					'args'                => [
						'current_password' => $this->argString(
							description: __( 'Current password.', 'allcoach' ),
							required: true,
						),
						'new_password'     => $this->argString(
							description: __( 'New password.', 'allcoach' ),
							required: true,
						),
						'confirm_password' => $this->argString(
							description: __( 'Confirm new password.', 'allcoach' ),
							required: true,
						),
					],
				],
			]
		);
	}

	public function updateProfile( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$userId = get_current_user_id();
		$data   = $request->get_params();

		try {
			$dto    = ClientDTO::fromArray( $data );
			$client = $this->clientRepository->findById( $userId );

			if ( $client ) {
				$this->updateService->execute( $userId, $dto );
			} else {
				wp_update_user(
					[
						'ID'           => $userId,
						'first_name'   => $dto->firstName,
						'last_name'    => $dto->lastName,
						'display_name' => trim( $dto->firstName . ' ' . $dto->lastName ),
						'user_email'   => $dto->email,
					]
				);
				update_user_meta( $userId, '_allcoach_client_phone', $dto->phone );
				update_user_meta( $userId, '_allcoach_client_timezone', $dto->timezone );
			}

			return $this->successResponse( [ 'message' => __( 'Profile updated successfully.', 'allcoach' ) ] );
		} catch ( \Throwable $e ) {
			return $this->exceptionToResponse( $e );
		}
	}

	public function changePassword( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$user            = wp_get_current_user();
		$currentPassword = (string) $request->get_param( 'current_password' );
		$newPassword     = (string) $request->get_param( 'new_password' );
		$confirmPassword = (string) $request->get_param( 'confirm_password' );

		if ( ! wp_check_password( $currentPassword, $user->user_pass, $user->ID ) ) {
			return $this->errorResponse( __( 'Current password is incorrect.', 'allcoach' ), 422 );
		}

		if ( strlen( $newPassword ) < 8 ) {
			return $this->errorResponse( __( 'New password must be at least 8 characters.', 'allcoach' ), 422 );
		}

		if ( $newPassword !== $confirmPassword ) {
			return $this->errorResponse( __( 'Passwords do not match.', 'allcoach' ), 422 );
		}

		wp_set_password( $newPassword, $user->ID );

		// Re-authenticate so the user isn't logged out.
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true );

		return $this->successResponse( [ 'message' => __( 'Password updated successfully.', 'allcoach' ) ] );
	}

	private function permClient(): bool {
		return is_user_logged_in() && ( $this->isClient() || $this->isCoach() || $this->isAdmin() );
	}
}
