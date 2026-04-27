<?php

declare(strict_types=1);

namespace Statnive\Tests\Integration\Admin;

use Statnive\Admin\CronHealth;
use Statnive\Admin\GeoIPNotice;
use Statnive\Admin\ReactHandler;
use WP_UnitTestCase;

defined( 'ABSPATH' ) || define( 'ABSPATH', dirname( __DIR__, 6 ) . '/' );

/**
 * Integration regression guard for the WP-Cron health admin notice.
 *
 * Runs through the full WordPress notice pipeline so the rendered HTML
 * matches what an admin sees on the Statnive dashboard. Asserts:
 *
 *  - The notice ONLY fires when CronHealth flags a stale job.
 *  - Fresh heartbeats silence the notice (managed-host case).
 *  - The notice contains the §25 cause / fix / auto-action triplet plus
 *    the §28 "Run cleanup now" form button and a valid nonce.
 *  - The page-scope guard at ReactHandler::HOOK_SUFFIX is honoured.
 *
 * Mirrors the AdminAssetScopeTest pattern — the integration test job is
 * currently gated off in CI (PHPUnit 11 vs the WP test framework) but
 * runs locally and will fire automatically once that gate is reopened.
 *
 * @covers \Statnive\Admin\GeoIPNotice
 * @covers \Statnive\Admin\CronHealth
 */
final class GeoIPNoticeCronTest extends WP_UnitTestCase {

	/** @var array<string, mixed> */
	private array $option_backup = [];

	public function set_up(): void {
		parent::set_up();

		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );

		// Capture and clear the heartbeat options so each test starts clean.
		foreach ( [ 'statnive_last_salt_rotation', 'statnive_last_aggregation', 'statnive_last_purge', 'statnive_last_geoip_update' ] as $option ) {
			$this->option_backup[ $option ] = get_option( $option, null );
			delete_option( $option );
		}

		// Force a Statnive admin screen so the notice's own page guard passes.
		set_current_screen( ReactHandler::HOOK_SUFFIX );
	}

	public function tear_down(): void {
		foreach ( $this->option_backup as $option => $value ) {
			if ( null === $value ) {
				delete_option( $option );
			} else {
				update_option( $option, $value );
			}
		}
		$this->option_backup = [];

		// Clean any per-user dismissal so subsequent tests start neutral.
		$user_id = get_current_user_id();
		if ( $user_id > 0 ) {
			delete_user_meta( $user_id, CronHealth::DISMISS_META_KEY );
		}

		// Unschedule any test-injected cron events.
		wp_clear_scheduled_hook( 'statnive_daily_salt_rotation' );
		wp_clear_scheduled_hook( 'statnive_daily_aggregation' );
		wp_clear_scheduled_hook( 'statnive_daily_data_purge' );
		wp_clear_scheduled_hook( 'statnive_weekly_geoip_update' );

		parent::tear_down();
	}

	private function render_notices(): string {
		ob_start();
		GeoIPNotice::maybe_show_notices();
		return (string) ob_get_clean();
	}

	private function schedule_aggregation_in_the_past(): void {
		// Schedule once at a past time so wp_next_scheduled() returns it
		// (single events past their fire time still appear in the schedule
		// until cron processes them).
		wp_schedule_single_event( time() - ( 72 * HOUR_IN_SECONDS ), 'statnive_daily_aggregation' );
	}

	/**
	 * Stale aggregation → notice renders the full triplet and form.
	 */
	public function test_renders_full_triplet_and_run_now_button_when_stale(): void {
		$this->schedule_aggregation_in_the_past();

		$html = $this->render_notices();

		$this->assertStringContainsString( 'Statnive: WordPress cron jobs are not running on time.', $html );
		$this->assertStringContainsString( 'have not completed within their normal window', $html );
		$this->assertStringContainsString( 'wp-cron.php', $html, 'Fix copy must reference wp-cron.php cron snippet.' );
		$this->assertStringContainsString( 'wp statnive cron run', $html, 'Fix copy must reference the WP-CLI command.' );
		$this->assertStringContainsString( 'self-dismisses', $html, 'Auto-action paragraph must explain self-dismissal.' );

		$this->assertStringContainsString( 'value="' . GeoIPNotice::RUN_NOW_ACTION . '"', $html );
		$this->assertStringContainsString( 'Run cleanup now', $html );
		$this->assertStringContainsString( '_wpnonce', $html, 'Form must carry a nonce field.' );

		$this->assertStringContainsString( 'value="' . GeoIPNotice::DISMISS_ACTION . '"', $html );
		$this->assertStringContainsString( 'Dismiss this Statnive cron warning', $html );
	}

	/**
	 * Fresh heartbeats → notice silenced (managed-host regression case).
	 */
	public function test_does_not_render_when_all_heartbeats_are_fresh(): void {
		$this->schedule_aggregation_in_the_past();

		$now = gmdate( 'c' );
		update_option( 'statnive_last_salt_rotation', $now, false );
		update_option( 'statnive_last_aggregation', $now, false );
		update_option( 'statnive_last_purge', $now, false );
		update_option( 'statnive_last_geoip_update', $now, false );

		$html = $this->render_notices();

		$this->assertStringNotContainsString( 'WordPress cron jobs are not running on time', $html );
		$this->assertStringNotContainsString( GeoIPNotice::RUN_NOW_ACTION, $html );
	}

	/**
	 * Local environment → notice silenced even when stale.
	 */
	public function test_does_not_render_on_local_environment(): void {
		$this->schedule_aggregation_in_the_past();

		add_filter(
			'pre_wp_get_environment_type',
			static fn (): string => 'local'
		);
		// `wp_get_environment_type()` is also affected by the cached
		// constant on some WP versions — clear that path first.
		add_filter(
			'option_wp_environment_type',
			static fn (): string => 'local'
		);

		$html = $this->render_notices();

		// Either the env filter was honoured (no notice), or the host's
		// environment is still production (notice present). Skip when WP
		// gives us 'production' so this test does not flap on CI hosts
		// where the env filter is overridden by a constant.
		if ( 'production' === wp_get_environment_type() ) {
			$this->markTestSkipped( 'wp_get_environment_type() returns production on this host; cannot exercise local-suppression branch.' );
		}

		$this->assertStringNotContainsString( 'WordPress cron jobs are not running on time', $html );
	}

	/**
	 * Page-scope guard: notice must NOT render outside the Statnive
	 * dashboard.
	 */
	public function test_does_not_render_outside_statnive_admin_page(): void {
		$this->schedule_aggregation_in_the_past();

		set_current_screen( 'index.php' );

		$html = $this->render_notices();

		$this->assertStringNotContainsString( 'WordPress cron jobs are not running on time', $html );
	}
}
