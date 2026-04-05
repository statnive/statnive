<?php

declare(strict_types=1);

namespace Statnive\Service;

use Statnive\Database\TableRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exclusion event logger.
 *
 * Records exclusion counts by date and reason in the exclusions table.
 * Uses INSERT ON DUPLICATE KEY UPDATE for atomic increment.
 */
final class ExclusionLogger {

	/**
	 * Log an exclusion event.
	 *
	 * @param string $reason Exclusion reason (e.g., 'bot:search_crawler', 'ip_range', 'role').
	 */
	public static function log( string $reason ): void {
		global $wpdb;

		$table  = TableRegistry::get( 'exclusions' );
		$date   = gmdate( 'Y-m-d' );
		$reason = sanitize_text_field( substr( $reason, 0, 50 ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO `{$table}` (date, reason, count) VALUES (%s, %s, 1)
				ON DUPLICATE KEY UPDATE count = count + 1",
				$date,
				$reason
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching
	}
}
