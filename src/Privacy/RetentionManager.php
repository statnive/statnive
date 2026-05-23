<?php

declare(strict_types=1);

namespace Statnive\Privacy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Data retention configuration manager.
 *
 * Reads retention settings and provides cutoff dates for the purge engine.
 */
final class RetentionManager {

	/**
	 * Get the retention mode.
	 *
	 * @return string One of 'forever', 'delete', 'archive'.
	 */
	public static function get_mode(): string {
		$mode  = get_option( 'statnive_retention_mode', 'forever' );
		$valid = [ 'forever', 'delete', 'archive' ];
		return in_array( $mode, $valid, true ) ? $mode : 'forever';
	}

	/**
	 * Get the retention period in days.
	 *
	 * @return int Number of days to retain data.
	 */
	public static function get_retention_days(): int {
		return max( 30, min( (int) get_option( 'statnive_retention_days', 3650 ), 3650 ) );
	}

	/**
	 * Get the cutoff date — data older than this should be purged.
	 *
	 * @return string Date in Y-m-d format.
	 */
	public static function get_cutoff_date(): string {
		$days = self::get_retention_days();
		return gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );
	}

	/**
	 * Check if purging should run.
	 *
	 * @return bool True if retention mode is delete or archive.
	 */
	public static function should_purge(): bool {
		return 'forever' !== self::get_mode();
	}
}
