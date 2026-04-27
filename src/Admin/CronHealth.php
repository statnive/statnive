<?php

declare(strict_types=1);

namespace Statnive\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Statnive\Cron\DailyAggregationJob;
use Statnive\Cron\DataPurgeJob;
use Statnive\Cron\SaltRotationJob;
use Statnive\Service\GeoIPDownloader;

/**
 * Cron health detector for the WP-Cron disabled admin notice.
 *
 * Single source of truth for "is Statnive's background work running?".
 * Replaces the naïve `defined('DISABLE_WP_CRON')` check that fires false
 * positives on managed WordPress hosts (WP Engine, Kinsta, SiteGround,
 * Cloudways) where the constant is intentionally true while a system cron
 * runs `wp-cron.php` on a real schedule.
 *
 * Maps every Statnive cron hook to the option that records its last
 * successful run (`statnive_last_*`) and applies a grace window per
 * recurrence. Combined with `WP_ENVIRONMENT_TYPE` suppression and
 * per-user signature-based dismissal, this satisfies:
 *
 *  - WP.org submission checklist §9 (DISABLE_WP_CRON detection + fallback)
 *  - §25 cause/fix/auto-action triplet (the auto-action is "this notice
 *    self-dismisses once cron catches up")
 *  - §28 stale-schedule detection (release blocker)
 *  - Common Issue #17 / Guideline 11 (self-dismissing site-wide notices)
 */
final class CronHealth {

	/**
	 * Grace window for daily jobs: 24h cadence + 12h slack for low-traffic
	 * sites where WP-Cron may be late to fire.
	 *
	 * @var int
	 */
	private const STALE_GRACE_DAILY_SECONDS = 36 * HOUR_IN_SECONDS;

	/**
	 * Grace window for weekly jobs: 7d cadence + 2d slack.
	 *
	 * @var int
	 */
	private const STALE_GRACE_WEEKLY_SECONDS = 9 * DAY_IN_SECONDS;

	/**
	 * User-meta key recording which stale-job signature the current admin
	 * has dismissed. The dismissal only sticks while the signature is
	 * stable — when a new job goes stale, the signature changes and the
	 * notice re-arms automatically.
	 *
	 * @var string
	 */
	public const DISMISS_META_KEY = 'statnive_cron_notice_dismissed_sig';

	/**
	 * Request-scoped memo for `job_status()`. Populated on first call
	 * within a request so `should_warn()` → `any_stale()` → `stale_hooks()`
	 * → `job_status()` and the subsequent render-side calls all share
	 * one set of `wp_next_scheduled()` + `get_option()` lookups.
	 *
	 * @var array<string, array{label: string, last_run_iso: ?string, next_run_iso: ?string, is_stale: bool}>|null
	 */
	private static ?array $memo = null;

	/**
	 * Map of cron hook => last-run option name + grace window seconds.
	 *
	 * Hook names and option names come from the Job class constants so
	 * a rename in one place propagates here without manual sync.
	 *
	 * @return array<string, array{option: string, grace: int, label: string}>
	 */
	private static function hook_map(): array {
		return [
			SaltRotationJob::HOOK      => [
				'option' => SaltRotationJob::LAST_RUN_OPTION,
				'grace'  => self::STALE_GRACE_DAILY_SECONDS,
				'label'  => __( 'Daily salt rotation', 'statnive' ),
			],
			DailyAggregationJob::HOOK  => [
				'option' => DailyAggregationJob::LAST_RUN_OPTION,
				'grace'  => self::STALE_GRACE_DAILY_SECONDS,
				'label'  => __( 'Daily data aggregation', 'statnive' ),
			],
			DataPurgeJob::HOOK         => [
				'option' => DataPurgeJob::LAST_RUN_OPTION,
				'grace'  => self::STALE_GRACE_DAILY_SECONDS,
				'label'  => __( 'Retention cleanup', 'statnive' ),
			],
			GeoIPDownloader::CRON_HOOK => [
				'option' => GeoIPDownloader::LAST_RUN_OPTION,
				'grace'  => self::STALE_GRACE_WEEKLY_SECONDS,
				'label'  => __( 'Weekly GeoIP database update', 'statnive' ),
			],
		];
	}

	/**
	 * Should the WP-Cron disabled admin notice fire right now?
	 *
	 * Combines environment suppression, stale-job detection, and per-user
	 * dismissal into one decision. Called from `GeoIPNotice` on every
	 * `admin_notices` render.
	 */
	public static function should_warn(): bool {
		if ( self::is_dev_environment() ) {
			return false;
		}

		if ( ! self::any_stale() ) {
			return false;
		}

		return ! self::is_dismissed_for_current_signature();
	}

	/**
	 * Per-job status used by the notice copy and the diagnostics endpoint.
	 *
	 * Skips hooks that are not currently scheduled — e.g. the GeoIP weekly
	 * update when the user has not opted in, or any hook on a fresh
	 * install before activation.
	 *
	 * Memoised per request: subsequent calls within the same PHP process
	 * return the cached result so the render path does not repeat the
	 * `wp_next_scheduled()` + `get_option()` round-trips.
	 *
	 * @return array<string, array{label: string, last_run_iso: ?string, next_run_iso: ?string, is_stale: bool}>
	 */
	public static function job_status(): array {
		if ( null !== self::$memo ) {
			return self::$memo;
		}

		$now = time();
		$out = [];
		foreach ( self::hook_map() as $hook => $meta ) {
			$next_ts = wp_next_scheduled( $hook );
			if ( false === $next_ts ) {
				// Not scheduled — opt-out feature or pre-activation. Not stale.
				continue;
			}

			$last_iso  = get_option( $meta['option'], '' );
			$last_iso  = is_string( $last_iso ) && '' !== $last_iso ? $last_iso : null;
			$last_ts   = null !== $last_iso ? strtotime( $last_iso ) : false;
			$reference = false !== $last_ts ? (int) $last_ts : (int) $next_ts;
			$is_stale  = ( $now - $reference ) > $meta['grace'];

			$out[ $hook ] = [
				'label'        => $meta['label'],
				'last_run_iso' => $last_iso,
				'next_run_iso' => gmdate( 'c', (int) $next_ts ),
				'is_stale'     => $is_stale,
			];
		}

		self::$memo = $out;
		return $out;
	}

	/**
	 * Hooks currently classified as stale, sorted alphabetically.
	 *
	 * Used by both the notice copy (to name the affected jobs) and the
	 * dismissal signature.
	 *
	 * @return list<string>
	 */
	public static function stale_hooks(): array {
		$stale = [];
		foreach ( self::job_status() as $hook => $status ) {
			if ( $status['is_stale'] ) {
				$stale[] = $hook;
			}
		}
		sort( $stale );
		return $stale;
	}

	/**
	 * Convenience: any job stale at all?
	 */
	public static function any_stale(): bool {
		return [] !== self::stale_hooks();
	}

	/**
	 * Last-run ISO timestamp option for one hook, or null if never set.
	 *
	 * @param string $hook Cron hook name (e.g. SaltRotationJob::HOOK).
	 */
	public static function last_run_for_hook( string $hook ): ?string {
		$map = self::hook_map();
		if ( ! isset( $map[ $hook ] ) ) {
			return null;
		}
		$value = get_option( $map[ $hook ]['option'], '' );
		return is_string( $value ) && '' !== $value ? $value : null;
	}

	/**
	 * Stable hash of the currently-stale hooks. Used as the dismissal key
	 * so a dismiss only suppresses *this* set of stale jobs — if a new
	 * job goes stale tomorrow, the signature changes and the notice
	 * comes back.
	 */
	public static function current_signature(): string {
		return sha1( implode( '|', self::stale_hooks() ) );
	}

	/**
	 * Persist the current signature against the active admin so the notice
	 * stays dismissed until the stale-job set changes.
	 */
	public static function dismiss_for_current_user(): void {
		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return;
		}
		update_user_meta( $user_id, self::DISMISS_META_KEY, self::current_signature() );
	}

	/**
	 * Reset the per-request memo. Test-only helper — production code
	 * never needs this because each request gets its own process.
	 */
	public static function flush_memo(): void {
		self::$memo = null;
	}

	/**
	 * True when the active admin has already dismissed this exact stale set.
	 */
	private static function is_dismissed_for_current_signature(): bool {
		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return false;
		}
		$stored = get_user_meta( $user_id, self::DISMISS_META_KEY, true );
		return is_string( $stored ) && self::current_signature() === $stored;
	}

	/**
	 * Suppress the notice on local/development environments — those are
	 * dev boxes where WP-Cron is intentionally disabled and the user does
	 * not need to be reminded daily that their dev install is dev-shaped.
	 */
	private static function is_dev_environment(): bool {
		if ( ! function_exists( 'wp_get_environment_type' ) ) {
			return false;
		}
		return in_array( wp_get_environment_type(), [ 'local', 'development' ], true );
	}
}
