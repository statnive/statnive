<?php

declare(strict_types=1);

namespace Statnive\Cron;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Statnive\Service\GeoIPDownloader;

/**
 * Centralized cron job scheduler.
 *
 * Registers and deregisters all Statnive WP-Cron events.
 * The GeoIP cron job is conditional — it only fires when the user
 * has explicitly opted in (WP.org Guideline 7).
 */
final class CronRegistrar {

	/**
	 * Register all cron event callbacks and schedule them.
	 */
	public static function register_all(): void {
		// Register callbacks (always safe — only fires when scheduled).
		SaltRotationJob::init();
		DailyAggregationJob::init();
		DataPurgeJob::init();
		EmailReportJob::init();
		add_action(
			GeoIPDownloader::CRON_HOOK,
			static function (): void {
				GeoIPDownloader::download();
			}
		);

		// Schedule core events (always active).
		SaltRotationJob::schedule();
		DailyAggregationJob::schedule();
		DataPurgeJob::schedule();
		EmailReportJob::schedule();

		// Conditional: only schedule if user has opted in.
		if ( get_option( 'statnive_geoip_enabled', false ) ) {
			GeoIPDownloader::schedule();
		}
	}

	/**
	 * Deregister all cron events.
	 *
	 * Called during plugin deactivation.
	 */
	public static function deregister_all(): void {
		SaltRotationJob::unschedule();
		DailyAggregationJob::unschedule();
		DataPurgeJob::unschedule();
		EmailReportJob::unschedule();
		GeoIPDownloader::unschedule();
	}
}
