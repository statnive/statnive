<?php

declare(strict_types=1);

namespace Statnive\Service;

use Statnive\Database\TableRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Daily aggregation service.
 *
 * Populates summary and summary_totals tables from raw event data.
 * Uses ON DUPLICATE KEY UPDATE for idempotent re-runs.
 */
final class AggregationService {

	/**
	 * Aggregate data for a single date.
	 *
	 * @param string $date Date in Y-m-d format.
	 */
	public static function aggregate_day( string $date ): void {
		self::aggregate_per_uri( $date );
		self::aggregate_totals( $date );
	}

	/**
	 * Aggregate per-URI metrics into summary table.
	 *
	 * @param string $date Date in Y-m-d format.
	 */
	private static function aggregate_per_uri( string $date ): void {
		global $wpdb;

		$views    = TableRegistry::get( 'views' );
		$sessions = TableRegistry::get( 'sessions' );
		$summary  = TableRegistry::get( 'summary' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO `{$summary}` (date, resource_uri_id, visitors, sessions, views, total_duration, bounces)
				SELECT %s, v.resource_uri_id,
					COUNT(DISTINCT s.visitor_id),
					COUNT(DISTINCT s.ID),
					COUNT(v.ID),
					COALESCE(SUM(v.duration), 0),
					SUM(CASE WHEN s.total_views = 1 THEN 1 ELSE 0 END)
				FROM `{$views}` v
				INNER JOIN `{$sessions}` s ON v.session_id = s.ID
				WHERE DATE(v.viewed_at) = %s
				GROUP BY v.resource_uri_id
				ON DUPLICATE KEY UPDATE
					visitors = VALUES(visitors),
					sessions = VALUES(sessions),
					views = VALUES(views),
					total_duration = VALUES(total_duration),
					bounces = VALUES(bounces)",
				$date,
				$date
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Aggregate site-wide totals into summary_totals table.
	 *
	 * @param string $date Date in Y-m-d format.
	 */
	private static function aggregate_totals( string $date ): void {
		global $wpdb;

		$sessions       = TableRegistry::get( 'sessions' );
		$views          = TableRegistry::get( 'views' );
		$summary_totals = TableRegistry::get( 'summary_totals' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO `{$summary_totals}` (date, visitors, sessions, views, total_duration, bounces)
				SELECT %s,
					COUNT(DISTINCT s.visitor_id),
					COUNT(DISTINCT s.ID),
					COUNT(v.ID),
					COALESCE(SUM(v.duration), 0),
					SUM(CASE WHEN s.total_views = 1 THEN 1 ELSE 0 END)
				FROM `{$sessions}` s
				LEFT JOIN `{$views}` v ON v.session_id = s.ID AND DATE(v.viewed_at) = %s
				WHERE DATE(s.started_at) = %s
				ON DUPLICATE KEY UPDATE
					visitors = VALUES(visitors),
					sessions = VALUES(sessions),
					views = VALUES(views),
					total_duration = VALUES(total_duration),
					bounces = VALUES(bounces)",
				$date,
				$date,
				$date
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Check if a date has been aggregated.
	 *
	 * @param string $date Date in Y-m-d format.
	 * @return bool True if summary_totals has data for this date.
	 */
	public static function is_aggregated( string $date ): bool {
		global $wpdb;
		$table = TableRegistry::get( 'summary_totals' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM `{$table}` WHERE date = %s",
				$date
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching

		return $count > 0;
	}

	/**
	 * Aggregate a range of dates.
	 *
	 * @param string $start_date Start date (Y-m-d).
	 * @param string $end_date   End date (Y-m-d).
	 */
	public static function aggregate_range( string $start_date, string $end_date ): void {
		$current = strtotime( $start_date );
		$end     = strtotime( $end_date );

		if ( false === $current || false === $end ) {
			return;
		}

		while ( $current <= $end ) {
			$date = gmdate( 'Y-m-d', $current );
			self::aggregate_day( $date );
			$current = strtotime( '+1 day', $current );
		}
	}
}
