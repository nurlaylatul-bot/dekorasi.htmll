<?php

declare(strict_types=1);

namespace AllCoach\Core\Cache;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

class CacheManager {

	private string $prefix;
	private int $defaultTtl;

	public function __construct( string $prefix = 'allcoach_', int $defaultTtl = 3600 ) {
		$this->prefix     = $prefix;
		$this->defaultTtl = $defaultTtl;
	}

	public function get( string $key, mixed $default = null ): mixed {
		$value = get_transient( $this->prefix . $key );
		return $value !== false ? $value : $default;
	}


	public function set( string $key, mixed $value, ?int $ttl = null ): bool {
		$ttl = $ttl ?? $this->defaultTtl;
		return set_transient( $this->prefix . $key, $value, $ttl );
	}

	public function has( string $key ): bool {
		return false !== get_transient( $this->prefix . $key );
	}

	public function delete( string $key ): bool {
		return delete_transient( $this->prefix . $key );
	}


	public function remember( string $key, callable $callback, ?int $ttl = null ): mixed {
		if ( $this->has( $key ) ) {
			return $this->get( $key );
		}

		$value = $callback();
		$this->set( $key, $value, $ttl );

		return $value;
	}

	public function flushTag( string $tag ): void {
		$tagKey = $this->prefix . 'tag_' . $tag;
		$keys   = get_transient( $tagKey );

		if ( is_array( $keys ) ) {
			foreach ( $keys as $key ) {
				$this->delete( $key );
			}
		}

		delete_transient( $tagKey );
	}

	public function tag( string $key, array $tags ): void {
		foreach ( $tags as $tag ) {
			$tagKey = $this->prefix . 'tag_' . $tag;
			$keys   = get_transient( $tagKey ) ?: [];

			if ( ! in_array( $key, $keys, true ) ) {
				$keys[] = $key;
				set_transient( $tagKey, $keys, $this->defaultTtl );
			}
		}
	}

	public function flush(): void {
		global $wpdb;

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_' . $this->prefix ) . '%',
				$wpdb->esc_like( '_transient_timeout_' . $this->prefix ) . '%'
			)
		);
	}
}
