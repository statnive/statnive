<?php

declare(strict_types=1);

namespace Statnive\Import;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract base class for data importers.
 *
 * Provides common infrastructure for GA4, WP Statistics, and CSV imports.
 * Uses wp_options for state persistence across WP-Cron batch runs.
 */
abstract class ImportManager {

	/**
	 * Get the import source identifier.
	 *
	 * @return string Source name (e.g., 'ga4', 'wp-statistics', 'csv').
	 */
	abstract public function get_source(): string;

	/**
	 * Start a new import.
	 *
	 * @param array<string,mixed> $config Import configuration.
	 * @return bool True on success.
	 */
	public function start( array $config ): bool {
		$state = [
			'status'        => 'running',
			'config'        => $config,
			'total_rows'    => 0,
			'imported_rows' => 0,
			'started_at'    => gmdate( 'Y-m-d H:i:s' ),
			'last_batch_at' => null,
			'error'         => null,
		];

		update_option( 'statnive_import_' . $this->get_source(), $state, false );

		// Schedule first batch.
		wp_schedule_single_event( time(), 'statnive_import_batch', [ $this->get_source() ] );

		return true;
	}

	/**
	 * Process a single batch.
	 *
	 * @return bool True if more batches remain.
	 */
	abstract public function process_batch(): bool;

	/**
	 * Get current import progress.
	 *
	 * @return array{status: string, total_rows: int, imported_rows: int, percentage: int, error: string|null}
	 */
	public function get_progress(): array {
		$state = get_option( 'statnive_import_' . $this->get_source(), [] );

		$total    = (int) ( $state['total_rows'] ?? 0 );
		$imported = (int) ( $state['imported_rows'] ?? 0 );
		$pct      = $total > 0 ? (int) round( ( $imported / $total ) * 100 ) : 0;

		return [
			'status'        => $state['status'] ?? 'idle',
			'total_rows'    => $total,
			'imported_rows' => $imported,
			'percentage'    => $pct,
			'error'         => $state['error'] ?? null,
		];
	}

	/**
	 * Cancel an in-progress import.
	 */
	public function cancel(): void {
		$state           = get_option( 'statnive_import_' . $this->get_source(), [] );
		$state['status'] = 'cancelled';
		update_option( 'statnive_import_' . $this->get_source(), $state, false );
	}

	/**
	 * Update import state.
	 *
	 * @param array<string,mixed> $updates Fields to merge.
	 */
	protected function update_state( array $updates ): void {
		$state = get_option( 'statnive_import_' . $this->get_source(), [] );
		$state = array_merge( $state, $updates );
		update_option( 'statnive_import_' . $this->get_source(), $state, false );
	}

	/**
	 * Get current state.
	 *
	 * @return array<string,mixed>
	 */
	protected function get_state(): array {
		return get_option( 'statnive_import_' . $this->get_source(), [] );
	}
}
