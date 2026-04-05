<?php

declare(strict_types=1);

namespace Statnive\Import;

use Statnive\Database\TableRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CSV data importer.
 *
 * Accepts CSV upload, validates structure, and batch-inserts into summary tables.
 * Required columns: date, url, visitors, views. Optional: source, country.
 */
final class CsvImporter extends ImportManager {

	/**
	 * Batch size per cron tick.
	 *
	 * @var int
	 */
	private const BATCH_SIZE = 1000;

	/**
	 * Get source identifier.
	 *
	 * @return string
	 */
	public function get_source(): string {
		return 'csv';
	}

	/**
	 * Process a batch of CSV rows.
	 *
	 * @return bool True if more rows remain.
	 */
	public function process_batch(): bool {
		$state = $this->get_state();

		if ( 'running' !== ( $state['status'] ?? '' ) ) {
			return false;
		}

		$file_path = $state['config']['file_path'] ?? '';
		$offset    = (int) ( $state['imported_rows'] ?? 0 );

		if ( empty( $file_path ) || ! file_exists( $file_path ) ) {
			$this->update_state(
				[
					'status' => 'error',
					'error'  => 'CSV file not found.',
				]
			);
			return false;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$handle = fopen( $file_path, 'r' );
		if ( false === $handle ) {
			$this->update_state(
				[
					'status' => 'error',
					'error'  => 'Cannot open CSV file.',
				]
			);
			return false;
		}

		// Skip header and already-processed rows.
		$header = fgetcsv( $handle );
		if ( false === $header ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			fclose( $handle );
			return false;
		}

		$col_map = array_flip( array_map( 'strtolower', array_map( 'trim', $header ) ) );

		// Skip to offset.
		for ( $i = 0; $i < $offset; $i++ ) {
			if ( false === fgetcsv( $handle ) ) {
				break;
			}
		}

		global $wpdb;
		$summary_table = TableRegistry::get( 'summary_totals' );
		$processed     = 0;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		while ( $processed < self::BATCH_SIZE && false !== ( $row = fgetcsv( $handle ) ) ) {
			$date     = $row[ $col_map['date'] ?? 0 ] ?? '';
			$visitors = absint( $row[ $col_map['visitors'] ?? 2 ] ?? 0 );
			$views    = absint( $row[ $col_map['views'] ?? 3 ] ?? 0 );

			if ( empty( $date ) || 0 === $visitors ) {
				++$processed;
				continue;
			}

			$wpdb->query(
				$wpdb->prepare(
					"INSERT INTO `{$summary_table}` (date, visitors, sessions, views, total_duration, bounces)
					VALUES (%s, %d, %d, %d, 0, 0)
					ON DUPLICATE KEY UPDATE
					visitors = visitors + VALUES(visitors),
					views = views + VALUES(views)",
					$date,
					$visitors,
					$visitors,
					$views
				)
			);

			++$processed;
		}
		// phpcs:enable

		$has_more = false !== fgetcsv( $handle );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose( $handle );

		$this->update_state(
			[
				'imported_rows' => $offset + $processed,
				'last_batch_at' => gmdate( 'Y-m-d H:i:s' ),
			]
		);

		if ( ! $has_more ) {
			$this->update_state( [ 'status' => 'complete' ] );
			return false;
		}

		// Schedule next batch.
		wp_schedule_single_event( time() + 5, 'statnive_import_batch', [ 'csv' ] );
		return true;
	}
}
