<?php

declare(strict_types=1);

namespace AllCoach\Domain\Client;

defined( 'ABSPATH' ) || exit;

interface ClientRepository {

	public function findById( int $id ): ?Client;

	public function findByEmail( string $email ): ?Client;

	/**
	 * @return array<Client>
	 */
	public function findAll( int $limit = 100, int $offset = 0 ): array;

	/**
	 * @return array<Client>
	 */
	public function findWithFilter( ClientFilter $filter, int $limit = 100, int $offset = 0 ): array;

	public function save( Client $client ): Client;

	public function delete( int $id ): bool;

	public function count(): int;

	public function countWithFilter( ClientFilter $filter ): int;

	public function countRegisteredSince( \DateTimeInterface $since ): int;
}
