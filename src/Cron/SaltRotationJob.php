<?php

declare(strict_types=1);

namespace Statnive\Cron;

use Statnive\Service\SaltManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Daily cron job for rotating visitor hashing salts.
 *
 * Ensures salts rotate even if no admin visits trigger it.
 */
final class SaltRotationJob {

	/**
	 * Cron hook name.
	 *
	 * @var string
	 */
	public const HOOK = 'statnive_daily_salt_rotation';

	/**
	 * Register the cron hook callback.
	 */
	public static function init(): void {
		add_action( self::HOOK, [ self::class, 'run' ] );
	}

	/**
	 * Execute salt rotation.
	 */
	public static function run(): void {
		if ( SaltManager::should_rotate() ) {
			SaltManager::rotate();
		}
	}

	/**
	 * Schedule the daily cron event if not already scheduled.
	 */
	public static function schedule(): void {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time(), 'daily', self::HOOK );
		}
	}

	/**
	 * Unschedule the cron event.
	 */
	public static function unschedule(): void {
		wp_clear_scheduled_hook( self::HOOK );
	}
}
