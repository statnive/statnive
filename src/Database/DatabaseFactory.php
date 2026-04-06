<?php

declare(strict_types=1);

namespace Statnive\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Database factory for creating and dropping Statnive tables.
 *
 * Uses WordPress dbDelta for safe table creation/updates.
 */
final class DatabaseFactory {

	/**
	 * Create or update all Statnive tables via dbDelta.
	 *
	 * @return array<string, string> Results from dbDelta keyed by table/column.
	 */
	public static function create_tables(): array {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = SchemaDefinition::get_sql();

		return dbDelta( $sql );
	}

	/**
	 * Drop all Statnive tables.
	 *
	 * Used during uninstall. Should only be called from uninstall.php.
	 */
	public static function drop_all_tables(): void {
		global $wpdb;

		$prefix = $wpdb->prefix . 'statnive_';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange
		$tables = $wpdb->get_col(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$wpdb->esc_like( $prefix ) . '%'
			)
		);

		foreach ( $tables as $table ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.SchemaChange
	}

	/**
	 * Check if all expected tables exist.
	 *
	 * @return array{missing: string[], existing: string[]} Tables grouped by status.
	 */
	public static function check_tables(): array {
		global $wpdb;

		$expected = TableRegistry::all_prefixed();
		$missing  = [];
		$existing = [];

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		foreach ( $expected as $table ) {
			$result = $wpdb->get_var(
				$wpdb->prepare( 'SHOW TABLES LIKE %s', $table )
			);
			if ( null === $result ) {
				$missing[] = $table;
			} else {
				$existing[] = $table;
			}
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching

		return [
			'missing'  => $missing,
			'existing' => $existing,
		];
	}
}
