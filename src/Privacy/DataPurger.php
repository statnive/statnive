<?php

declare(strict_types=1);

namespace Statnive\Privacy;

use Statnive\Database\TableRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Segmented batch data purge engine.
 *
 * Deletes analytics data older than the retention cutoff in batches
 * to prevent lock contention on shared hosting. Uses leaf-to-root
 * table ordering to avoid orphaned references.
 */
final class DataPurger {

	/**
	 * Batch size per table per execution.
	 *
	 * @var int
	 */
	private const BATCH_SIZE = 1000;

	/**
	 * Tables to purge in order (leaf-to-root).
	 *
	 * @var array<string, string> table_name => date_column
	 */
	private const PURGE_TABLES = [
		'events'     => 'created_at',
		'parameters' => 'created_at',
		'views'      => 'viewed_at',
		'sessions'   => 'started_at',
	];

	/**
	 * Run a purge cycle.
	 *
	 * @return array{deleted: int, remaining: bool}
	 */
	public static function purge(): array {
		if ( ! RetentionManager::should_purge() ) {
			return [
				'deleted'   => 0,
				'remaining' => false,
			];
		}

		$cutoff        = RetentionManager::get_cutoff_date();
		$total_deleted = 0;
		$has_remaining = false;

		global $wpdb;

		foreach ( self::PURGE_TABLES as $table_name => $date_column ) {
			$table = TableRegistry::get( $table_name );

			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
			$deleted = $wpdb->query(
				$wpdb->prepare(
					'DELETE FROM %i WHERE %i < %s LIMIT %d',
					$table,
					$date_column,
					$cutoff,
					self::BATCH_SIZE
				)
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching

			if ( false !== $deleted ) {
				$total_deleted += (int) $deleted;
				if ( (int) $deleted >= self::BATCH_SIZE ) {
					$has_remaining = true;
				}
			}
		}

		// Log last purge time.
		update_option( 'statnive_last_purge', gmdate( 'Y-m-d H:i:s' ), false );

		return [
			'deleted'   => $total_deleted,
			'remaining' => $has_remaining,
		];
	}
}
