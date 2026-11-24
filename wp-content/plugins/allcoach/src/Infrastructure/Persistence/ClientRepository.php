<?php

declare(strict_types=1);

namespace AllCoach\Infrastructure\Persistence;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\RoleManager;
use AllCoach\Domain\Client\Client;
use AllCoach\Domain\Client\ClientFilter;
use AllCoach\Domain\Client\ClientRepository as ClientRepositoryInterface;
use AllCoach\Domain\Client\ClientStatus;
use Carbon\CarbonImmutable;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.SlowDBQuery

class ClientRepository implements ClientRepositoryInterface {

	public function findById( int $id ): ?Client {
		$user = get_userdata( $id );

		if ( ! $user || ! in_array( RoleManager::ROLE_CLIENT, (array) $user->roles, true ) ) {
			return null;
		}

		return $this->hydrate( $user );
	}

	public function findByEmail( string $email ): ?Client {
		$user = get_user_by( 'email', $email );

		if ( ! $user || ! in_array( RoleManager::ROLE_CLIENT, (array) $user->roles, true ) ) {
			return null;
		}

		return $this->hydrate( $user );
	}

	public function findAll( int $limit = 100, int $offset = 0 ): array {
		return $this->runQuery( [], $limit, $offset );
	}

	public function findWithFilter( ClientFilter $filter, int $limit = 100, int $offset = 0 ): array {
		if ( $filter->search !== null && $filter->search !== '' ) {
			return $this->runSearchQuery( $filter, $limit, $offset );
		}

		$args = $this->buildQueryArgsFromFilter( $filter );

		if ( $filter->coachId !== null ) {
			$coachClientIds = $this->resolveClientIdsByCoach( $filter->coachId );
			if ( empty( $coachClientIds ) ) {
				return [];
			}
			$args['include'] = $coachClientIds;
		}

		return $this->runQuery( $args, $limit, $offset );
	}

	public function count(): int {
		return $this->runCountQuery( [] );
	}

	public function countWithFilter( ClientFilter $filter ): int {
		if ( $filter->search !== null && $filter->search !== '' ) {
			return $this->runSearchCount( $filter );
		}

		$args = $this->buildQueryArgsFromFilter( $filter );

		if ( $filter->coachId !== null ) {
			$coachClientIds = $this->resolveClientIdsByCoach( $filter->coachId );
			if ( empty( $coachClientIds ) ) {
				return 0;
			}
			$args['include'] = $coachClientIds;
		}

		return $this->runCountQuery( $args );
	}

	public function countRegisteredSince( \DateTimeInterface $since ): int {
		return $this->runCountQuery(
			[
				'date_query' => [
					[
						'column'    => 'user_registered',
						'after'     => $since->format( 'Y-m-d H:i:s' ),
						'inclusive' => true,
					],
				],
			]
		);
	}

	public function save( Client $client ): Client {
		return $client->isNew() ? $this->insert( $client ) : $this->update( $client );
	}

	public function delete( int $id ): bool {
		$user = get_userdata( $id );

		if ( ! $user ) {
			return false;
		}

		$user->remove_role( RoleManager::ROLE_CLIENT );

		foreach ( $this->metaKeys() as $key ) {
			delete_user_meta( $id, $key );
		}

		return true;
	}

	private function insert( Client $client ): Client {
		$existing = get_user_by( 'email', $client->getEmail() );

		if ( $existing instanceof \WP_User ) {
			$userId = $existing->ID;
		} else {
			$username = $this->uniqueUsername( $client->getEmail() );
			$userId   = wp_create_user( $username, wp_generate_password( 16 ), $client->getEmail() );

			if ( is_wp_error( $userId ) ) {
				throw new \RuntimeException(
					'Failed to create client: ' . esc_html( $userId->get_error_message() )
				);
			}
		}

		wp_update_user(
			[
				'ID'           => $userId,
				'first_name'   => $client->getFirstName(),
				'last_name'    => $client->getLastName(),
				'display_name' => $client->getName(),
				'nickname'     => $client->getName(),
			]
		);

		$user = new \WP_User( $userId );
		$user->add_role( RoleManager::ROLE_CLIENT );

		$reconstituted = Client::reconstitute(
			id: $userId,
			firstName: $client->getFirstName(),
			lastName: $client->getLastName(),
			email: $client->getEmail(),
			phone: $client->getPhone(),
			timezone: $client->getTimezone(),
			status: $client->getStatus(),
			notes: $client->getNotes(),
			createdAt: $client->getCreatedAt(),
			updatedAt: $client->getUpdatedAt(),
		);

		$this->saveMeta( $reconstituted );

		return $reconstituted;
	}

	private function update( Client $client ): Client {
		$result = wp_update_user(
			[
				'ID'           => $client->getId(),
				'first_name'   => $client->getFirstName(),
				'last_name'    => $client->getLastName(),
				'display_name' => $client->getName(),
				'user_email'   => $client->getEmail(),
				'nickname'     => $client->getName(),
			]
		);

		if ( is_wp_error( $result ) ) {
			throw new \RuntimeException(
				'Failed to update client: ' . esc_html( $result->get_error_message() )
			);
		}

		$this->saveMeta( $client );

		return $client;
	}

	private function saveMeta( Client $client ): void {
		$id = (int) $client->getId();

		update_user_meta( $id, '_allcoach_client_phone', $client->getPhone() );
		update_user_meta( $id, '_allcoach_client_timezone', $client->getTimezone() );
		update_user_meta( $id, '_allcoach_client_status', $client->getStatus()->getValue() );
		update_user_meta( $id, '_allcoach_client_notes', $client->getNotes() );
		update_user_meta( $id, '_allcoach_created_at', $client->getCreatedAt()->format( 'Y-m-d H:i:s' ) );
	}

	/**
	 * @return array<string>
	 */
	private function metaKeys(): array {
		return [
			'_allcoach_client_phone',
			'_allcoach_client_timezone',
			'_allcoach_client_status',
			'_allcoach_client_notes',
			'_allcoach_created_at',
		];
	}

	/**
	 * Path: enrollments.user_id -> order_items.program_id -> posts (program) with meta _coach_id.
	 *
	 * @return int[]
	 */
	private function resolveClientIdsByCoach( int $coachId ): array {
		global $wpdb;

		$enrollmentsTable = $wpdb->prefix . 'allcoach_enrollments';
		$itemsTable       = $wpdb->prefix . 'allcoach_order_items';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT e.user_id
				FROM {$enrollmentsTable} e
				INNER JOIN {$itemsTable} oi ON oi.id = e.order_item_id
				INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = oi.program_id
					AND pm.meta_key = '_coach_id'
					AND pm.meta_value = %s
				WHERE e.status = 'active'",
				(string) $coachId
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return array_map( 'intval', $ids ?: [] );
	}

	/**
	 * @param  array<string, mixed> $args   Extra WP_User_Query args to merge in.
	 * @return array<Client>
	 */
	private function runQuery( array $args, int $limit, int $offset ): array {
		$query = new \WP_User_Query(
			array_merge(
				[
					'role'    => RoleManager::ROLE_CLIENT,
					'number'  => $limit,
					'offset'  => $offset,
					'orderby' => 'display_name',
					'order'   => 'ASC',
				],
				$args
			)
		);

		return array_map(
			fn( \WP_User $u ) => $this->hydrate( $u ),
			$query->get_results()
		);
	}

	/** @param array<string, mixed> $args */
	private function runCountQuery( array $args ): int {
		$query = new \WP_User_Query(
			array_merge(
				[
					'role'        => RoleManager::ROLE_CLIENT,
					'number'      => 1,
					'count_total' => true,
				],
				$args
			)
		);

		return (int) $query->get_total();
	}

	/**
	 * Full-text search across display_name, user_email, and phone meta.
	 * WP_User_Query's built-in 'search' only covers core user table columns,
	 * so we need raw SQL to also hit usermeta (phone).
	 *
	 * @return array<Client>
	 */
	private function runSearchQuery( ClientFilter $filter, int $limit, int $offset ): array {
		$ids = $this->resolveSearchIds( $filter, $limit, $offset );

		if ( empty( $ids ) ) {
			return [];
		}

		$users = get_users(
			[
				'include' => $ids,
				'orderby' => 'display_name',
				'order'   => 'ASC',
			]
		);

		return array_map( fn( \WP_User $u ) => $this->hydrate( $u ), $users );
	}

	private function runSearchCount( ClientFilter $filter ): int {
		global $wpdb;

		[ $sql, $bindings ] = $this->buildSearchSql( $filter, null, null );

		$countSql = "SELECT COUNT(*) FROM ({$sql}) AS sub";

		return (int) $wpdb->get_var( $wpdb->prepare( $countSql, $bindings ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * @return array<int>
	 */
	private function resolveSearchIds( ClientFilter $filter, int $limit, int $offset ): array {
		global $wpdb;

		[ $sql, $bindings ] = $this->buildSearchSql( $filter, $limit, $offset );

		$ids = $wpdb->get_col( $wpdb->prepare( $sql, $bindings ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return array_map( 'intval', $ids ?: [] );
	}

	/**
	 * Pass null for $limit/$offset to get a COUNT-friendly subquery.
	 *
	 * @return array{0: string, 1: array<mixed>}
	 */
	private function buildSearchSql( ClientFilter $filter, ?int $limit, ?int $offset ): array {
		global $wpdb;

		$capKey  = $wpdb->get_blog_prefix() . 'capabilities';
		$like    = '%' . $wpdb->esc_like( $filter->search ?? '' ) . '%';
		$capLike = '%' . $wpdb->esc_like( RoleManager::ROLE_CLIENT ) . '%';

		$metaJoins  = '';
		$extraWhere = '';
		$bindings   = [ $capKey, $capLike, $like, $like, $like ];

		if ( $filter->coachId !== null ) {
			$coachClientIds = $this->resolveClientIdsByCoach( $filter->coachId );
			if ( empty( $coachClientIds ) ) {
				// Return impossible condition — will yield zero results.
				return [ 'SELECT 0 AS ID WHERE 1 = 0', [] ];
			}
			$placeholders = implode( ', ', array_fill( 0, count( $coachClientIds ), '%d' ) );
			$extraWhere  .= " AND u.ID IN ({$placeholders})"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$bindings     = array_merge( $bindings, $coachClientIds );
		}

		if ( $filter->status !== null ) {
			$metaJoins .= " INNER JOIN {$wpdb->usermeta} st
                               ON st.user_id  = u.ID
                              AND st.meta_key  = '_allcoach_client_status'
                              AND st.meta_value = %s";
			$bindings[] = $filter->status->getValue();
		}

		$sql = "SELECT DISTINCT u.ID
                  FROM {$wpdb->users} u
                  INNER JOIN {$wpdb->usermeta} cap
                          ON cap.user_id   = u.ID
                         AND cap.meta_key  = %s
                         AND cap.meta_value LIKE %s
                  LEFT JOIN {$wpdb->usermeta} phone
                         ON phone.user_id  = u.ID
                        AND phone.meta_key = '_allcoach_client_phone'
                  {$metaJoins}
                 WHERE (
                     u.display_name   LIKE %s
                  OR u.user_email     LIKE %s
                  OR phone.meta_value LIKE %s
                 )
                 {$extraWhere}
                 ORDER BY u.display_name ASC";

		if ( $limit !== null && $offset !== null ) {
			$sql       .= ' LIMIT %d OFFSET %d';
			$bindings[] = $limit;
			$bindings[] = $offset;
		}

		return [ $sql, $bindings ];
	}

	/** @return array<string, mixed> */
	private function buildQueryArgsFromFilter( ClientFilter $filter ): array {
		$args      = [];
		$metaQuery = [];

		if ( $filter->status !== null ) {
			$metaQuery[] = [
				'key'     => '_allcoach_client_status',
				'value'   => $filter->status->getValue(),
				'compare' => '=',
			];
		}

		if ( ! empty( $metaQuery ) ) {
			$args['meta_query'] = array_merge( [ 'relation' => 'AND' ], $metaQuery );
		}

		$args['orderby'] = match ( $filter->orderBy ) {
			'name'  => 'display_name',
			'email' => 'user_email',
			'date'  => 'registered',
			default => 'display_name',
		};
		$args['order'] = $filter->order;

		return $args;
	}

	private function hydrate( \WP_User $user ): Client {
		$statusRaw = get_user_meta( $user->ID, '_allcoach_client_status', true ) ?: 'active';
		$createdAt = get_user_meta( $user->ID, '_allcoach_created_at', true );
		$isDemo    = '1' === get_user_meta( $user->ID, '_allcoach_is_demo', true );

		return Client::reconstitute(
			id: $user->ID,
			firstName: $user->first_name ?: '',
			lastName: $user->last_name ?: '',
			email: $user->user_email,
			phone: (string) get_user_meta( $user->ID, '_allcoach_client_phone', true ),
			timezone: (string) get_user_meta( $user->ID, '_allcoach_client_timezone', true ),
			status: ClientStatus::fromString( $statusRaw ),
			notes: (string) get_user_meta( $user->ID, '_allcoach_client_notes', true ),
			createdAt: CarbonImmutable::parse( $createdAt ?: $user->user_registered ),
			isDemo: $isDemo,
		);
	}

	private function uniqueUsername( string $email ): string {
		$base = sanitize_user( strstr( $email, '@', true ), true );
		$base = $base ?: 'client';

		$username = $base;
		$suffix   = 1;

		while ( username_exists( $username ) ) {
			$username = $base . ( $suffix++ );
		}

		return $username;
	}
}
