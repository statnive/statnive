<?php

declare(strict_types=1);

namespace Statnive\Cron;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Statnive\Licensing\ApiCommunicator;
use Statnive\Licensing\LicenseHelper;
use Statnive\Licensing\LicenseStatus;

/**
 * Weekly license validation cron job.
 *
 * Validates the stored license key against the API server.
 * Uses 3-strike degradation: only downgrades after 3 consecutive failures.
 */
final class LicenseCheckJob {

	public const HOOK = 'statnive_weekly_license_check';

	private const FAILURE_TRANSIENT = 'statnive_license_check_failures';
	private const MAX_FAILURES      = 3;

	/**
	 * Register the cron hook callback.
	 */
	public static function init(): void {
		add_action( self::HOOK, [ self::class, 'run' ] );
	}

	/**
	 * Execute weekly license check.
	 */
	public static function run(): void {
		if ( ! LicenseHelper::has_license() ) {
			return;
		}

		$key = LicenseHelper::get_license_key();
		if ( null === $key ) {
			return;
		}

		$old_status = LicenseHelper::get_cached_status();
		$new_status = ApiCommunicator::validate_license( $key );

		// Handle API errors with 3-strike policy.
		if ( LicenseStatus::STATUS_ERROR === $new_status->status ) {
			$failures = (int) get_transient( self::FAILURE_TRANSIENT );
			++$failures;
			set_transient( self::FAILURE_TRANSIENT, $failures, WEEK_IN_SECONDS );

			// Keep current status if under threshold.
			if ( $failures < self::MAX_FAILURES ) {
				return;
			}

			// After 3 failures, downgrade to free.
			$new_status = LicenseStatus::free();
		} else {
			// Reset failure counter on successful check.
			delete_transient( self::FAILURE_TRANSIENT );
		}

		LicenseHelper::cache_status( $new_status );

		// Fire action if status changed.
		if ( $old_status->status !== $new_status->status || $old_status->plan_tier !== $new_status->plan_tier ) {
			/**
			 * Fires when the license status changes.
			 *
			 * @param LicenseStatus $new_status New license status.
			 * @param LicenseStatus $old_status Previous license status.
			 */
			do_action( 'statnive_license_status_changed', $new_status, $old_status );
		}
	}

	/**
	 * Schedule the weekly cron event.
	 *
	 * Only schedules if a license key is present (WP.org Guideline 6).
	 */
	public static function schedule(): void {
		if ( ! LicenseHelper::has_license() ) {
			return;
		}
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time(), 'weekly', self::HOOK );
		}
	}

	/**
	 * Unschedule the cron event.
	 */
	public static function unschedule(): void {
		wp_clear_scheduled_hook( self::HOOK );
	}
}
