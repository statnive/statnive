<?php

declare(strict_types=1);

namespace Statnive\Cron;

use Statnive\Service\AggregationService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Daily cron job for aggregating analytics data.
 *
 * Runs at 00:15 UTC daily. Aggregates the previous day's raw data
 * into summary tables for fast dashboard queries.
 */
final class DailyAggregationJob {

	/**
	 * Cron hook name.
	 *
	 * @var string
	 */
	public const HOOK = 'statnive_daily_aggregation';

	/**
	 * Register the cron hook callback.
	 */
	public static function init(): void {
		add_action( self::HOOK, [ self::class, 'run' ] );
	}

	/**
	 * Execute daily aggregation.
	 *
	 * Aggregates the previous day's data. On first run, also backfills
	 * any days that have raw data but no summaries.
	 */
	public static function run(): void {
		$yesterday = gmdate( 'Y-m-d', strtotime( '-1 day' ) );

		// Aggregate yesterday's data.
		AggregationService::aggregate_day( $yesterday );

		// Backfill check: find un-aggregated days.
		self::backfill();
	}

	/**
	 * Find days with raw data but no summary and aggregate them.
	 *
	 * Checks the last 30 days for gaps.
	 */
	private static function backfill(): void {
		$end   = gmdate( 'Y-m-d', strtotime( '-2 days' ) );
		$start = gmdate( 'Y-m-d', strtotime( '-30 days' ) );

		$current = strtotime( $start );
		$end_ts  = strtotime( $end );

		if ( false === $current || false === $end_ts ) {
			return;
		}

		while ( $current <= $end_ts ) {
			$date = gmdate( 'Y-m-d', $current );
			if ( ! AggregationService::is_aggregated( $date ) ) {
				AggregationService::aggregate_day( $date );
			}
			$current = strtotime( '+1 day', $current );
		}
	}

	/**
	 * Schedule the daily aggregation cron event.
	 */
	public static function schedule(): void {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			// Schedule at 00:15 UTC to avoid midnight contention.
			$next_run = strtotime( 'tomorrow 00:15:00 UTC' );
			if ( false !== $next_run ) {
				wp_schedule_event( $next_run, 'daily', self::HOOK );
			}
		}
	}

	/**
	 * Unschedule the cron event.
	 */
	public static function unschedule(): void {
		wp_clear_scheduled_hook( self::HOOK );
	}
}
