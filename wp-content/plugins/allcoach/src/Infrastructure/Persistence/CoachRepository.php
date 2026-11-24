<?php

declare(strict_types=1);

namespace AllCoach\Infrastructure\Persistence;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\RoleManager;
use AllCoach\Domain\Coach\Coach;
use AllCoach\Domain\Coach\CoachRepository as CoachRepositoryInterface;
use Carbon\CarbonImmutable;

class CoachRepository implements CoachRepositoryInterface {

	public function findById( int $id ): ?Coach {
		$user = get_userdata( $id );
		if ( ! $user || ! current_user_can( 'edit_allcoach_programs' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
			return null;
		}
		return $this->hydrate( $user );
	}

	public function findByEmail( string $email ): ?Coach {
		$user = get_user_by( 'email', $email );
		if ( ! $user || ! current_user_can( 'edit_allcoach_programs' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
			return null;
		}
		return $this->hydrate( $user );
	}

	public function findByUserId( int $userId ): ?Coach {
		return $this->findById( $userId );
	}

	public function findAll( int $limit = 100, int $offset = 0 ): array {
		$query = new \WP_User_Query(
			[
				'role'    => RoleManager::ROLE_COACH,
				'number'  => $limit,
				'offset'  => $offset,
				'orderby' => 'display_name',
				'order'   => 'ASC',
			]
		);

		return array_map( fn( \WP_User $u ) => $this->hydrate( $u ), $query->get_results() );
	}

	public function save( Coach $coach ): Coach {
		return $coach->isNew() ? $this->insert( $coach ) : $this->update( $coach );
	}

	public function delete( int $id ): bool {
		$user = get_userdata( $id );
		if ( ! $user ) {
			return false;
		}
		$user->remove_role( RoleManager::ROLE_COACH );
		delete_user_meta( $id, '_allcoach_bio' );
		delete_user_meta( $id, '_allcoach_created_at' );
		return true;
	}

	public function count(): int {
		$query = new \WP_User_Query(
			[
				'role'        => RoleManager::ROLE_COACH,
				'number'      => 1,
				'count_total' => true,
			]
		);
		return (int) $query->get_total();
	}

	public function search( string $query, int $limit = 100, int $offset = 0 ): array {
		$wpQuery = new \WP_User_Query(
			[
				'role'           => RoleManager::ROLE_COACH,
				'search'         => '*' . $query . '*',
				'search_columns' => [ 'display_name', 'user_email', 'user_login' ],
				'number'         => $limit,
				'offset'         => $offset,
				'count_total'    => false,
			]
		);

		return array_map( fn( \WP_User $u ) => $this->hydrate( $u ), $wpQuery->get_results() );
	}

	public function searchCount( string $query ): int {
		$wpQuery = new \WP_User_Query(
			[
				'role'           => RoleManager::ROLE_COACH,
				'search'         => '*' . $query . '*',
				'search_columns' => [ 'display_name', 'user_email', 'user_login' ],
				'number'         => 1,
				'count_total'    => true,
			]
		);

		return (int) $wpQuery->get_total();
	}

	private function insert( Coach $coach ): Coach {
		$username = $this->uniqueUsername( $coach->getEmail() );

		$userId = wp_create_user( $username, wp_generate_password( 16 ), $coach->getEmail() );

		if ( is_wp_error( $userId ) ) {
			throw new \RuntimeException( 'Failed to create coach: ' . esc_html( $userId->get_error_message() ) );
		}

		wp_update_user(
			[
				'ID'           => $userId,
				'display_name' => $coach->getName(),
				'nickname'     => $coach->getName(),
			]
		);

		$user = new \WP_User( $userId );
		$user->set_role( RoleManager::ROLE_COACH );

		$coach = Coach::reconstitute(
			id:        $userId,
			name:      $coach->getName(),
			email:     $coach->getEmail(),
			bio:       $coach->getBio(),
			createdAt: $coach->getCreatedAt(),
			updatedAt: null,
		);
		$this->saveMeta( $coach );

		return $coach;
	}

	private function update( Coach $coach ): Coach {
		$result = wp_update_user(
			[
				'ID'           => $coach->getId(),
				'display_name' => $coach->getName(),
				'user_email'   => $coach->getEmail(),
				'nickname'     => $coach->getName(),
			]
		);

		if ( is_wp_error( $result ) ) {
			throw new \RuntimeException( 'Failed to update coach: ' . esc_html( $result->get_error_message() ) );
		}

		$this->saveMeta( $coach );
		return $coach;
	}

	private function saveMeta( Coach $coach ): void {
		$id = (int) $coach->getId();
		update_user_meta( $id, '_allcoach_bio', $coach->getBio() ?? '' );
		update_user_meta( $id, '_allcoach_created_at', $coach->getCreatedAt()->format( 'Y-m-d H:i:s' ) );
	}

	private function hydrate( \WP_User $user ): Coach {
		$bio       = get_user_meta( $user->ID, '_allcoach_bio', true );
		$createdAt = get_user_meta( $user->ID, '_allcoach_created_at', true );

		return Coach::reconstitute(
			id:        $user->ID,
			name:      $user->display_name ?: $user->user_login,
			email:     $user->user_email,
			bio:       ( $bio !== '' && $bio !== false ) ? $bio : null,
			createdAt: CarbonImmutable::parse( $createdAt ?: $user->user_registered ),
			updatedAt: null,
		);
	}

	private function uniqueUsername( string $email ): string {
		$base = sanitize_user( strstr( $email, '@', true ), true );
		$base = $base ?: 'coach';

		$username = $base;
		$suffix   = 1;
		while ( username_exists( $username ) ) {
			$username = $base . ( $suffix++ );
		}

		return $username;
	}
}
