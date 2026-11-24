<?php

declare(strict_types=1);

namespace AllCoach\Core;

defined( 'ABSPATH' ) || exit;

use AllCoach\Traits\Hooks;

class RoleManager {

	use Hooks;

	public const ROLE_COACH  = 'allcoach_coach';
	public const ROLE_CLIENT = 'allcoach_client';

	public function createRoles(): void {
		$this->createCoachRole();
		$this->createClientRole();
		$this->grantAdminCapabilities();

		$this->doAction( 'allcoach:roles:created' );
	}

	public function removeRoles(): void {
		remove_role( self::ROLE_COACH );
		remove_role( self::ROLE_CLIENT );

		$this->doAction( 'allcoach:roles:removed' );
	}

	private function createCoachRole(): void {
		if ( get_role( self::ROLE_COACH ) ) {
			return;
		}

		$capabilities = $this->applyFilters( 'allcoach:role:coach_capabilities', $this->coachCapabilities() );

		add_role(
			self::ROLE_COACH,
			__( 'Coach', 'allcoach' ),
			$capabilities
		);
	}

	private function createClientRole(): void {
		if ( get_role( self::ROLE_CLIENT ) ) {
			return;
		}

		$capabilities = $this->applyFilters( 'allcoach:role:client_capabilities', $this->clientCapabilities() );

		add_role(
			self::ROLE_CLIENT,
			__( 'Client', 'allcoach' ),
			$capabilities
		);
	}

	private function coachCapabilities(): array {
		return [
			// WordPress baseline.
			'read'                                 => true,
			'upload_files'                         => true,

			// Programs (own only).
			'edit_allcoach_programs'               => true,
			'publish_allcoach_programs'            => true,
			'delete_allcoach_programs'             => true,
			'delete_published_allcoach_programs'   => true,
			'read_allcoach_program'                => true,

			// Activities (own only — library items they create).
			'edit_allcoach_activities'             => true,
			'publish_allcoach_activities'          => true,
			'delete_allcoach_activities'           => true,
			'delete_published_allcoach_activities' => true,
			'read_allcoach_activity'               => true,

			// Clients enrolled in their own programs.
			'read_allcoach_client'                 => true,

			// Orders (enrollments).
			'view_allcoach_orders'                 => true,
			'manage_allcoach_orders'               => true,

			// Session bookings.
			'view_allcoach_session_bookings'       => true,
			'manage_allcoach_session_bookings'     => true,
		];
	}

	private function clientCapabilities(): array {
		return [
			// WordPress baseline.
			'read'                           => true,

			// Programs.
			'read_allcoach_program'          => true,

			// Orders.
			'view_allcoach_orders'           => true,

			// Session bookings.
			'view_allcoach_session_bookings' => true,

			// Activities.
			'read_allcoach_activity'         => true,
			'complete_allcoach_activity'     => true,
		];
	}

	public function grantAdminCapabilities(): void {
		$role = get_role( 'administrator' );
		if ( ! $role ) {
			return;
		}

		foreach ( $this->adminCapabilities() as $cap ) {
			$role->add_cap( $cap );
		}
	}

	private function adminCapabilities(): array {
		return [
			// Programs — all, including others'.
			'edit_allcoach_programs',
			'edit_others_allcoach_programs',
			'publish_allcoach_programs',
			'delete_allcoach_programs',
			'delete_published_allcoach_programs',
			'delete_others_allcoach_programs',
			'delete_private_allcoach_programs',
			'read_private_allcoach_programs',
			'read_allcoach_program',
			'manage_allcoach_orders',
			'view_allcoach_orders',
			'manage_allcoach_session_bookings',
			'view_allcoach_session_bookings',

			// Activities — all, including others'.
			'edit_allcoach_activities',
			'edit_others_allcoach_activities',
			'publish_allcoach_activities',
			'delete_allcoach_activities',
			'delete_published_allcoach_activities',
			'delete_others_allcoach_activities',
			'delete_private_allcoach_activities',
			'read_private_allcoach_activities',
			'read_allcoach_activity',

			// Clients.
			'manage_allcoach_clients',
			'edit_allcoach_clients',
			'edit_allcoach_client',
			'delete_allcoach_clients',
			'delete_allcoach_client',
			'read_allcoach_client',
			'complete_allcoach_activity',

			// Coaches.
			'manage_allcoach_coaches',
			'edit_allcoach_coaches',
			'edit_allcoach_coach',
			'delete_allcoach_coaches',
			'delete_allcoach_coach',
			'read_allcoach_coach',
		];
	}
}
