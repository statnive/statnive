<?php

declare(strict_types=1);

namespace Statnive\Cron;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Statnive\Privacy\DataArchiver;
use Statnive\Privacy\DataPurger;
use Statnive\Privacy\RetentionManager;

/**
 * Daily cron job for purging expired analytics data.
 *
 * Runs daily. Archives data first if in archive mode, then purges.
 * Re-schedules for +5min if batch has remaining rows.
 */
final class DataPurgeJob {

	/**
	 * Cron hook name.
	 *
	 * @var string
	 */
	public const HOOK = 'statnive_daily_data_purge';

	/**
	 * Register the cron hook callback.
	 */
	public static function init(): void {
		add_action( self::HOOK, [ self::class, 'run' ] );
	}

	/**
	 * Execute data purge.
	 */
	public static function run(): void {
		if ( ! RetentionManager::should_purge() ) {
			return;
		}

		// Archive before purge if in archive mode.
		if ( 'archive' === RetentionManager::get_mode() ) {
			$cutoff_date = RetentionManager::get_cutoff_date();
			$year_month  = substr( $cutoff_date, 0, 7 );
			DataArchiver::archive_month( $year_month );
		}

		$result = DataPurger::purge();

		// If there's more data to purge, schedule another run in 5 minutes.
		if ( $result['remaining'] ) {
			wp_schedule_single_event( time() + 300, self::HOOK );
		}
	}

	/**
	 * Schedule the daily purge cron event.
	 */
	public static function schedule(): void {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			$next_run = strtotime( 'tomorrow 00:30:00 UTC' );
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
