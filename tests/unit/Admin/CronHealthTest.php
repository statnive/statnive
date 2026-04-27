<?php

declare(strict_types=1);

namespace Statnive\Tests\Unit\Admin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Statnive\Admin\CronHealth;

defined( 'ABSPATH' ) || define( 'ABSPATH', dirname( __DIR__, 6 ) . '/' );

/**
 * Unit tests for the WP-Cron staleness detector.
 *
 * Covers WP.org submission checklist §9 / §25 / §28 / Common Issue #17:
 * the detector must distinguish "managed host with system cron running"
 * (the real-world false-positive case) from "cron is genuinely stale",
 * and per-user dismissal must re-arm when the stale-job set changes.
 */
#[CoversClass(CronHealth::class)]
final class CronHealthTest extends TestCase {

	/**
	 * Reset every WordPress-stub global before each test so cases do not
	 * bleed into each other through `$GLOBALS['statnive_test_*']`.
	 */
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['statnive_test_options']    = [];
		$GLOBALS['statnive_test_scheduled']  = [];
		$GLOBALS['statnive_test_user_meta']  = [];
		$GLOBALS['statnive_test_env_type']   = 'production';
		$GLOBALS['statnive_test_user_id']    = 1;
		CronHealth::flush_memo();
	}

	/**
	 * Each test mutates the scheduled/options globals after setUp(), so
	 * any earlier `CronHealth::*` call inside the same test would have
	 * captured an empty memo. Flush before re-querying.
	 */
	private function flush(): void {
		CronHealth::flush_memo();
	}

	/**
	 * Schedule every Statnive cron hook with a future next-run timestamp,
	 * mimicking the post-activation state on a real install.
	 */
	private function schedule_all_hooks(): void {
		$GLOBALS['statnive_test_scheduled'] = [
			'statnive_daily_salt_rotation' => time() + HOUR_IN_SECONDS,
			'statnive_daily_aggregation'   => time() + HOUR_IN_SECONDS,
			'statnive_daily_data_purge'    => time() + HOUR_IN_SECONDS,
			'statnive_weekly_geoip_update' => time() + DAY_IN_SECONDS,
		];
	}

	/**
	 * Write a fresh heartbeat for every Statnive job — equivalent to
	 * "WP-Cron just fired all four jobs successfully".
	 */
	private function write_fresh_heartbeats(): void {
		$now = gmdate( 'c' );
		$GLOBALS['statnive_test_options']['statnive_last_salt_rotation'] = $now;
		$GLOBALS['statnive_test_options']['statnive_last_aggregation']   = $now;
		$GLOBALS['statnive_test_options']['statnive_last_purge']         = $now;
		$GLOBALS['statnive_test_options']['statnive_last_geoip_update']  = $now;
	}

	/**
	 * Managed-host case: DISABLE_WP_CRON is true on the host, but a system
	 * cron fires `wp-cron.php` on time — every heartbeat is fresh, so the
	 * notice must NOT fire. This is the regression the new detector
	 * exists to prevent.
	 */
	public function test_does_not_warn_when_all_heartbeats_are_fresh(): void {
		$this->schedule_all_hooks();
		$this->write_fresh_heartbeats();

		self::assertFalse( CronHealth::any_stale() );
		self::assertFalse( CronHealth::should_warn() );
	}

	/**
	 * Genuinely stale: the daily aggregation heartbeat is 48 hours old
	 * (well past the 36-hour grace window). The notice must fire.
	 */
	public function test_warns_when_a_daily_heartbeat_is_past_grace(): void {
		$this->schedule_all_hooks();
		$this->write_fresh_heartbeats();
		$GLOBALS['statnive_test_options']['statnive_last_aggregation'] =
			gmdate( 'c', time() - ( 48 * HOUR_IN_SECONDS ) );

		self::assertTrue( CronHealth::any_stale() );
		self::assertContains( 'statnive_daily_aggregation', CronHealth::stale_hooks() );
		self::assertTrue( CronHealth::should_warn() );
	}

	/**
	 * New install before the first cron fire: heartbeats missing but
	 * next_scheduled is in the future. The detector treats "queued and
	 * waiting" as healthy so a fresh installation is silent.
	 */
	public function test_does_not_warn_for_brand_new_install(): void {
		$this->schedule_all_hooks();
		// No last_run options written yet — fresh install.

		self::assertFalse( CronHealth::any_stale() );
		self::assertFalse( CronHealth::should_warn() );
	}

	/**
	 * Cron has been quiet for too long: heartbeat missing and the
	 * scheduled timestamp is more than the grace window in the past.
	 * That is the "DISABLE_WP_CRON + no system cron" production case.
	 */
	public function test_warns_when_schedule_is_in_the_past_and_no_heartbeat(): void {
		$past = time() - ( 72 * HOUR_IN_SECONDS );
		$GLOBALS['statnive_test_scheduled'] = [
			'statnive_daily_salt_rotation' => $past,
			'statnive_daily_aggregation'   => $past,
			'statnive_daily_data_purge'    => $past,
			'statnive_weekly_geoip_update' => $past,
		];

		self::assertTrue( CronHealth::any_stale() );
		self::assertTrue( CronHealth::should_warn() );
	}

	/**
	 * Dev-environment suppression: even with every job stale, the notice
	 * stays silent on local/development environments — that is where
	 * `DISABLE_WP_CRON` is most commonly set legitimately.
	 */
	public function test_local_environment_silences_warning(): void {
		$past = time() - ( 72 * HOUR_IN_SECONDS );
		$GLOBALS['statnive_test_scheduled']['statnive_daily_aggregation'] = $past;
		$GLOBALS['statnive_test_env_type']                                 = 'local';

		self::assertTrue( CronHealth::any_stale(), 'Stale detection still works under local env.' );
		self::assertFalse( CronHealth::should_warn(), 'Notice must be suppressed on local env.' );
	}

	/**
	 * Same suppression rule applies to the `development` environment.
	 */
	public function test_development_environment_silences_warning(): void {
		$past = time() - ( 72 * HOUR_IN_SECONDS );
		$GLOBALS['statnive_test_scheduled']['statnive_daily_aggregation'] = $past;
		$GLOBALS['statnive_test_env_type']                                 = 'development';

		self::assertFalse( CronHealth::should_warn() );
	}

	/**
	 * The `production` environment must NOT suppress — that would defeat
	 * the entire purpose of the notice.
	 */
	public function test_production_environment_does_not_silence_warning(): void {
		$past = time() - ( 72 * HOUR_IN_SECONDS );
		$GLOBALS['statnive_test_scheduled']['statnive_daily_aggregation'] = $past;
		$GLOBALS['statnive_test_env_type']                                 = 'production';

		self::assertTrue( CronHealth::should_warn() );
	}

	/**
	 * Dismiss flow: once the user dismisses, `should_warn()` must return
	 * false for the same set of stale jobs.
	 */
	public function test_dismissal_silences_warning_for_same_stale_set(): void {
		$past = time() - ( 72 * HOUR_IN_SECONDS );
		$GLOBALS['statnive_test_scheduled']['statnive_daily_aggregation'] = $past;

		self::assertTrue( CronHealth::should_warn() );

		CronHealth::dismiss_for_current_user();

		self::assertFalse( CronHealth::should_warn(), 'Dismissal must silence the same stale set.' );
	}

	/**
	 * Re-arm on signature change: if a *new* job goes stale after the
	 * user already dismissed, the signature changes and the notice
	 * comes back on the next render.
	 */
	public function test_dismissal_does_not_silence_a_new_stale_job(): void {
		$past = time() - ( 72 * HOUR_IN_SECONDS );
		$GLOBALS['statnive_test_scheduled']['statnive_daily_aggregation'] = $past;

		self::assertTrue( CronHealth::should_warn() );
		CronHealth::dismiss_for_current_user();
		self::assertFalse( CronHealth::should_warn() );

		// A second job goes stale — signature changes, dismissal no longer applies.
		$GLOBALS['statnive_test_scheduled']['statnive_daily_data_purge'] = $past;
		$this->flush();

		self::assertTrue( CronHealth::should_warn(), 'New stale job must re-arm the warning.' );
	}

	/**
	 * Dismissal is per-user. A second admin must still see the notice
	 * even after the first one has dismissed it.
	 */
	public function test_dismissal_is_scoped_to_current_user(): void {
		$past = time() - ( 72 * HOUR_IN_SECONDS );
		$GLOBALS['statnive_test_scheduled']['statnive_daily_aggregation'] = $past;

		$GLOBALS['statnive_test_user_id'] = 1;
		CronHealth::dismiss_for_current_user();
		self::assertFalse( CronHealth::should_warn() );

		// Memo is per-request, but the dismissal check reads user_meta on
		// every call — switching the user mid-request must change the
		// outcome without a flush.
		$GLOBALS['statnive_test_user_id'] = 2;
		self::assertTrue( CronHealth::should_warn(), 'Other admins must still be warned.' );
	}

	/**
	 * Hooks that are not currently scheduled (e.g. GeoIP weekly update
	 * before the user opts in) must be excluded from the stale set —
	 * otherwise opt-out features would warn forever.
	 */
	public function test_unscheduled_hooks_are_never_stale(): void {
		// Schedule only three of the four hooks; GeoIP intentionally absent.
		$GLOBALS['statnive_test_scheduled'] = [
			'statnive_daily_salt_rotation' => time() + HOUR_IN_SECONDS,
			'statnive_daily_aggregation'   => time() + HOUR_IN_SECONDS,
			'statnive_daily_data_purge'    => time() + HOUR_IN_SECONDS,
		];

		self::assertArrayNotHasKey( 'statnive_weekly_geoip_update', CronHealth::job_status() );
		self::assertFalse( CronHealth::any_stale() );
	}

	/**
	 * `last_run_for_hook()` reads the right option name for each hook.
	 * Regression guard for the hook→option mapping.
	 */
	public function test_last_run_for_hook_reads_each_canonical_option(): void {
		$GLOBALS['statnive_test_options'] = [
			'statnive_last_salt_rotation' => '2026-04-25T00:00:00+00:00',
			'statnive_last_aggregation'   => '2026-04-26T00:15:00+00:00',
			'statnive_last_purge'         => '2026-04-26T00:30:00+00:00',
			'statnive_last_geoip_update'  => '2026-04-21T00:00:00+00:00',
		];

		self::assertSame( '2026-04-25T00:00:00+00:00', CronHealth::last_run_for_hook( 'statnive_daily_salt_rotation' ) );
		self::assertSame( '2026-04-26T00:15:00+00:00', CronHealth::last_run_for_hook( 'statnive_daily_aggregation' ) );
		self::assertSame( '2026-04-26T00:30:00+00:00', CronHealth::last_run_for_hook( 'statnive_daily_data_purge' ) );
		self::assertSame( '2026-04-21T00:00:00+00:00', CronHealth::last_run_for_hook( 'statnive_weekly_geoip_update' ) );
		self::assertNull( CronHealth::last_run_for_hook( 'statnive_unknown_hook' ) );
	}

	/**
	 * Weekly grace must be longer than daily grace: a 48-hour-old GeoIP
	 * heartbeat is healthy, but a 48-hour-old daily aggregation is not.
	 */
	public function test_weekly_grace_is_more_lenient_than_daily(): void {
		$two_days_ago = gmdate( 'c', time() - ( 48 * HOUR_IN_SECONDS ) );

		$GLOBALS['statnive_test_scheduled'] = [
			'statnive_daily_aggregation'   => time() + HOUR_IN_SECONDS,
			'statnive_weekly_geoip_update' => time() + DAY_IN_SECONDS,
		];
		$GLOBALS['statnive_test_options']['statnive_last_aggregation']  = $two_days_ago;
		$GLOBALS['statnive_test_options']['statnive_last_geoip_update'] = $two_days_ago;

		$status = CronHealth::job_status();

		self::assertTrue( $status['statnive_daily_aggregation']['is_stale'], 'Daily 48h heartbeat is stale (>36h grace).' );
		self::assertFalse( $status['statnive_weekly_geoip_update']['is_stale'], 'Weekly 48h heartbeat is fresh (9d grace).' );
	}
}
