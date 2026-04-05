<?php

declare(strict_types=1);

namespace Statnive\Cron;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Statnive\Licensing\LicenseHelper;
use Statnive\Service\GeoIPDownloader;

/**
 * Centralized cron job scheduler.
 *
 * Registers and deregisters all Statnive WP-Cron events.
 * GeoIP and license cron jobs are conditional — they only fire
 * when explicitly enabled by the user (WP.org Guideline 6 & 7).
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
		LicenseCheckJob::init();
		add_action( GeoIPDownloader::CRON_HOOK, [ GeoIPDownloader::class, 'download' ] );

		// Schedule core events (always active).
		SaltRotationJob::schedule();
		DailyAggregationJob::schedule();
		DataPurgeJob::schedule();
		EmailReportJob::schedule();

		// Conditional: only schedule if user has opted in.
		if ( get_option( 'statnive_geoip_enabled', false ) ) {
			GeoIPDownloader::schedule();
		}
		if ( LicenseHelper::has_license() ) {
			LicenseCheckJob::schedule();
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
		LicenseCheckJob::unschedule();
		GeoIPDownloader::unschedule();
	}
}
