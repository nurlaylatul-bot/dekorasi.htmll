<?php

declare(strict_types=1);

namespace AllCoach\Infrastructure\Database;

abstract class Migration {

	abstract public function up(): void;

	abstract public function down(): void;

	protected function table( string $name ): string {
		/** @var \wpdb */
		global $wpdb;
		return $wpdb->prefix . $name;
	}

	protected function charsetCollate(): string {
		/** @var \wpdb */
		global $wpdb;
		return $wpdb->get_charset_collate();
	}

	protected function dbDelta( string $sql ): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}
}
