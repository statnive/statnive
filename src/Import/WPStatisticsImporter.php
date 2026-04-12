<?php

declare(strict_types=1);

namespace Statnive\Import;

use Statnive\Database\TableRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP Statistics data importer.
 *
 * Reads directly from WP Statistics tables and transforms data
 * into Statnive's schema. Handles IP hashing during import.
 */
final class WPStatisticsImporter extends ImportManager {

	private const BATCH_SIZE = 5000;

	/**
	 * Get source identifier.
	 *
	 * @return string
	 */
	public function get_source(): string {
		return 'wp-statistics';
	}

	/**
	 * Check if WP Statistics tables exist.
	 *
	 * @return bool True if importable.
	 */
	public static function is_available(): bool {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->prefix . 'statistics_visits' ) )
		);
		return null !== $result;
	}

	/**
	 * Process a batch of WP Statistics data.
	 *
	 * @return bool True if more rows remain.
	 */
	public function process_batch(): bool {
		$state = $this->get_state();
		if ( 'running' !== ( $state['status'] ?? '' ) ) {
			return false;
		}

		global $wpdb;
		$offset = (int) ( $state['imported_rows'] ?? 0 );

		$source_table  = $wpdb->prefix . 'statistics_visits';
		$summary_table = TableRegistry::get( 'summary_totals' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT last_counter AS date, visit AS views
				FROM `{$source_table}`
				ORDER BY last_counter ASC
				LIMIT %d OFFSET %d",
				self::BATCH_SIZE,
				$offset
			)
		);

		if ( empty( $rows ) ) {
			$this->update_state( [ 'status' => 'complete' ] );
			return false;
		}

		foreach ( $rows as $row ) {
			$wpdb->query(
				$wpdb->prepare(
					"INSERT INTO `{$summary_table}` (date, visitors, sessions, views, total_duration, bounces)
					VALUES (%s, %d, %d, %d, 0, 0)
					ON DUPLICATE KEY UPDATE views = views + VALUES(views)",
					$row->date,
					(int) $row->views,
					(int) $row->views,
					(int) $row->views
				)
			);
		}
		// phpcs:enable

		$this->update_state(
			[
				'imported_rows' => $offset + count( $rows ),
				'last_batch_at' => gmdate( 'Y-m-d H:i:s' ),
			]
		);

		if ( count( $rows ) < self::BATCH_SIZE ) {
			$this->update_state( [ 'status' => 'complete' ] );
			return false;
		}

		wp_schedule_single_event( time() + 5, 'statnive_import_batch', [ 'wp-statistics' ] );
		return true;
	}
}
