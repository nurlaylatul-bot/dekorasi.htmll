<?php

declare(strict_types=1);

namespace AllCoach\Core\Database;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

/**
 * Wraps WordPress $wpdb in database transaction semantics.
 */
class Transaction {

	/**
	 * Execute a callback inside a database transaction.
	 *
	 * @template T
	 * @param callable(): T $callback
	 * @return T
	 * @throws \Throwable Re-throws any exception after rolling back.
	 */
	public static function run( callable $callback ): mixed {
		global $wpdb;

		$wpdb->query( 'START TRANSACTION' );

		try {
			$result = $callback();
			$wpdb->query( 'COMMIT' );
			return $result;
		} catch ( \Throwable $e ) {
			$wpdb->query( 'ROLLBACK' );
			throw $e;
		}
	}
}
