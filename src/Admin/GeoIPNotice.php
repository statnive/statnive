<?php

declare(strict_types=1);

namespace Statnive\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Statnive\Cron\DailyAggregationJob;
use Statnive\Cron\DataPurgeJob;
use Statnive\Cron\SaltRotationJob;

/**
 * Admin notices for GeoIP configuration and cron health.
 *
 * Shows dismissible, plugin-page-scoped notices when:
 *  - GeoIP is enabled but no MaxMind license key is configured.
 *  - Statnive's WP-Cron jobs are stale (see Statnive\Admin\CronHealth).
 *
 * The cron notice is paired with two `admin-post.php` handlers — one to
 * run the cleanup jobs synchronously ("Run cleanup now" button) and one
 * to record a per-user dismissal of the current stale-job signature.
 *
 * Maps to WP.org submission checklist §9 (cron detection), §25 (cause /
 * fix / auto-action copy), §28 (release-blocker: stale detection +
 * in-admin manual run + WP-CLI equivalent), and Common Issue #17 /
 * Guideline 11 (self-dismissing site-wide notices).
 */
final class GeoIPNotice {

	/**
	 * `admin-post.php` action that runs the three daily cron jobs.
	 */
	public const RUN_NOW_ACTION = 'statnive_run_cron_now';

	/**
	 * `admin-post.php` action that dismisses the cron notice for the
	 * current admin (signature-scoped — see CronHealth).
	 */
	public const DISMISS_ACTION = 'statnive_dismiss_cron_notice';

	/**
	 * Transient key carrying a one-shot success/failure flash message
	 * from `handle_run_now()` to the next admin pageview.
	 */
	private const FLASH_TRANSIENT_PREFIX = 'statnive_cron_flash_';

	/**
	 * Hook into WordPress admin notices and the two admin-post handlers.
	 *
	 * Both `admin_post_*` handlers are registered unconditionally — they
	 * verify nonce + capability themselves before doing anything.
	 */
	public static function init(): void {
		add_action( 'admin_notices', [ self::class, 'maybe_show_notices' ] );
		add_action( 'admin_post_' . self::RUN_NOW_ACTION, [ self::class, 'handle_run_now' ] );
		add_action( 'admin_post_' . self::DISMISS_ACTION, [ self::class, 'handle_dismiss' ] );
	}

	/**
	 * Display admin notices on Statnive pages only.
	 */
	public static function maybe_show_notices(): void {
		$screen = get_current_screen();
		if ( null === $screen || ! in_array( $screen->id, ReactHandler::HOOK_SUFFIXES, true ) ) {
			return;
		}

		// Surface the flash result of a "Run cleanup now" click before any
		// other notice — it is the user's most recent action.
		self::maybe_show_run_now_flash();

		// Cron health warning applies regardless of GeoIP state.
		self::maybe_show_cron_notice();

		if ( ! (bool) get_option( 'statnive_geoip_enabled', false ) ) {
			return;
		}

		self::maybe_show_license_notice();
	}

	/**
	 * Show notice when GeoIP is enabled without a MaxMind license key.
	 */
	private static function maybe_show_license_notice(): void {
		$license_key = get_option( 'statnive_maxmind_license_key', '' );
		if ( '' !== $license_key ) {
			return;
		}

		printf(
			'<div class="notice notice-warning is-dismissible"><p><strong>%s</strong></p><p>%s</p><p><em>%s</em></p><p>%s</p></div>',
			esc_html__( 'Statnive: GeoIP is enabled but no MaxMind license key is configured.', 'statnive' ),
			esc_html__( 'Impact: visitor country/region data will not appear in your reports. Page tracking, sources, devices and all other metrics continue to work normally.', 'statnive' ),
			esc_html__( 'What Statnive will do: retry the GeoIP database download every week. No additional alerts will be raised until the key is added or GeoIP is disabled.', 'statnive' ),
			wp_kses(
				sprintf(
					/* translators: 1: MaxMind signup URL, 2: MaxMind EULA URL */
					__( '<strong>To fix:</strong> <a href="%1$s" target="_blank" rel="noopener">get a free MaxMind license key</a> (requires accepting the <a href="%2$s" target="_blank" rel="noopener">GeoLite2 EULA</a>) and paste it into Settings → GeoIP. Or disable GeoIP in Settings to dismiss this notice.', 'statnive' ),
					'https://www.maxmind.com/en/geolite2/signup',
					'https://www.maxmind.com/en/geolite2/eula'
				),
				[
					'a'      => [
						'href'   => [],
						'target' => [],
						'rel'    => [],
					],
					'strong' => [],
				]
			)
		);
	}

	/**
	 * Show the WP-Cron health notice when CronHealth flags stale jobs.
	 *
	 * Renders three paragraphs (what happened / how to fix / what
	 * Statnive will do automatically) followed by a form with a primary
	 * "Run cleanup now" button and a "Dismiss" button. Both buttons post
	 * to `admin-post.php` with their own nonces.
	 *
	 * Self-dismissal is implicit: when the run-now handler updates every
	 * `statnive_last_*` heartbeat, `CronHealth::should_warn()` returns
	 * false on the very next render and the notice disappears without
	 * the admin needing to click anything.
	 */
	private static function maybe_show_cron_notice(): void {
		if ( ! CronHealth::should_warn() ) {
			return;
		}

		$stale_lines = self::stale_lines( CronHealth::job_status() );

		echo '<div class="notice notice-warning"><p><strong>',
			esc_html__( 'Statnive: WordPress cron jobs are not running on time.', 'statnive' ),
			'</strong></p>';

		echo '<p>',
			esc_html__( 'These Statnive background jobs have not completed within their normal window:', 'statnive' ),
			'</p><ul style="list-style:disc;margin-left:1.5em;">';
		foreach ( $stale_lines as $line ) {
			echo '<li>', esc_html( $line ), '</li>';
		}
		echo '</ul>';

		echo '<p>',
			wp_kses(
				sprintf(
					/* translators: 1: example crontab line, 2: WP-CLI command */
					__( '<strong>To fix:</strong> add a system cron that pings <code>wp-cron.php</code> every five minutes — for example <code>%1$s</code> — or run <code>%2$s</code> via WP-CLI.', 'statnive' ),
					'*/5 * * * * curl -s ' . esc_url( home_url( '/wp-cron.php?doing_wp_cron' ) ) . ' &gt;/dev/null 2&gt;&amp;1',
					'wp statnive cron run'
				),
				[
					'strong' => [],
					'code'   => [],
				]
			),
			'</p>';

		echo '<p><em>',
			esc_html__( 'What Statnive will do automatically: this notice self-dismisses as soon as every job runs again within its normal window. Clicking "Run cleanup now" below records a fresh heartbeat, which clears the warning until the next scheduled cycle.', 'statnive' ),
			'</em></p>';

		echo '<p>';
		self::render_post_button(
			self::RUN_NOW_ACTION,
			__( 'Run cleanup now', 'statnive' ),
			true
		);
		self::render_post_button(
			self::DISMISS_ACTION,
			__( 'Dismiss', 'statnive' ),
			false,
			__( 'Dismiss this Statnive cron warning', 'statnive' )
		);
		echo '</p></div>';
	}

	/**
	 * Render an admin-post.php submit button as a self-contained inline
	 * form. Used twice in the cron-health notice — once for "Run cleanup
	 * now" (primary) and once for "Dismiss" (secondary).
	 *
	 * @param string  $action  `admin_post_*` action name (also nonce action).
	 * @param string  $label   Button label, raw text — escaped here.
	 * @param bool    $primary True to render as `button-primary`.
	 * @param ?string $aria    Optional aria-label, raw text — escaped here.
	 */
	private static function render_post_button( string $action, string $label, bool $primary, ?string $aria = null ): void {
		$button_class = $primary ? 'button button-primary' : 'button';
		$margin       = $primary ? 'margin-right:0.5em;' : '';

		printf(
			'<form method="post" action="%1$s" style="display:inline-block;%2$s">',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr( $margin )
		);
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( $action ) );
		wp_nonce_field( $action );

		if ( null !== $aria ) {
			printf(
				'<button type="submit" class="%1$s" aria-label="%2$s">%3$s</button>',
				esc_attr( $button_class ),
				esc_attr( $aria ),
				esc_html( $label )
			);
		} else {
			printf(
				'<button type="submit" class="%1$s">%2$s</button>',
				esc_attr( $button_class ),
				esc_html( $label )
			);
		}
		echo '</form>';
	}

	/**
	 * Render the one-shot flash from a previous "Run cleanup now" click.
	 *
	 * The transient is consumed (deleted) on read so the message never
	 * sticks around past one pageview.
	 */
	private static function maybe_show_run_now_flash(): void {
		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return;
		}

		$key   = self::FLASH_TRANSIENT_PREFIX . $user_id;
		$flash = get_transient( $key );
		if ( ! is_array( $flash ) || ! isset( $flash['message'] ) ) {
			return;
		}
		delete_transient( $key );

		$class = ( ! empty( $flash['ok'] ) ) ? 'notice-success' : 'notice-error';
		printf(
			'<div class="notice %s is-dismissible"><p>%s</p></div>',
			esc_attr( $class ),
			esc_html( (string) $flash['message'] )
		);
	}

	/**
	 * Format CronHealth::job_status() into human-readable lines for the
	 * stale-jobs bullet list.
	 *
	 * Uses the site's date format and timezone so the timestamps match
	 * what admins see elsewhere in wp-admin.
	 *
	 * @param array<string, array{label: string, last_run_iso: ?string, next_run_iso: ?string, is_stale: bool}> $status CronHealth::job_status() output.
	 * @return list<string>
	 */
	private static function stale_lines( array $status ): array {
		$date_format = (string) get_option( 'date_format', 'Y-m-d' );
		$time_format = (string) get_option( 'time_format', 'H:i' );
		$lines       = [];

		foreach ( $status as $info ) {
			if ( ! $info['is_stale'] ) {
				continue;
			}

			if ( null === $info['last_run_iso'] ) {
				$lines[] = sprintf(
					/* translators: %s = job label, e.g. "Daily data aggregation" */
					__( '%s — has not run yet.', 'statnive' ),
					(string) $info['label']
				);
				continue;
			}

			$ts = strtotime( (string) $info['last_run_iso'] );
			if ( false === $ts ) {
				$lines[] = (string) $info['label'];
				continue;
			}

			$lines[] = sprintf(
				/* translators: 1: job label, 2: localised date/time of last successful run */
				__( '%1$s — last ran %2$s.', 'statnive' ),
				(string) $info['label'],
				date_i18n( $date_format . ' ' . $time_format, $ts )
			);
		}

		return $lines;
	}

	/**
	 * `admin_post_statnive_run_cron_now` handler.
	 *
	 * Runs the three daily Statnive jobs synchronously, sets a flash
	 * transient with the outcome, and redirects back to the Statnive
	 * dashboard. The fresh `statnive_last_*` heartbeats written by each
	 * job's `run()` method automatically clear the warning on render.
	 */
	public static function handle_run_now(): void {
		check_admin_referer( self::RUN_NOW_ACTION );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to run Statnive cron jobs.', 'statnive' ), '', [ 'response' => 403 ] );
		}

		$start = microtime( true );
		try {
			SaltRotationJob::run();
			DailyAggregationJob::run();
			DataPurgeJob::run();
			$ok      = true;
			$message = __( 'Statnive cron jobs ran successfully. The warning will reappear if cron stops firing again.', 'statnive' );
		} catch ( \Throwable $e ) {
			$ok      = false;
			$message = sprintf(
				/* translators: %s = error message */
				__( 'Statnive cron run failed: %s', 'statnive' ),
				$e->getMessage()
			);
		}

		$user_id = get_current_user_id();
		if ( $user_id > 0 ) {
			set_transient(
				self::FLASH_TRANSIENT_PREFIX . $user_id,
				[
					'ok'         => $ok,
					'message'    => $message,
					'duration_s' => round( microtime( true ) - $start, 3 ),
				],
				MINUTE_IN_SECONDS
			);
		}

		self::redirect_back();
	}

	/**
	 * `admin_post_statnive_dismiss_cron_notice` handler.
	 *
	 * Stores the current stale-job signature against the active admin so
	 * the notice stays hidden until either (a) the stale-job set changes
	 * or (b) cron catches up and the notice would have self-dismissed
	 * anyway.
	 */
	public static function handle_dismiss(): void {
		check_admin_referer( self::DISMISS_ACTION );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to dismiss this notice.', 'statnive' ), '', [ 'response' => 403 ] );
		}

		CronHealth::dismiss_for_current_user();
		self::redirect_back();
	}

	/**
	 * Safe redirect back to the referring admin page, falling back to
	 * the Statnive dashboard if the referer is missing.
	 */
	private static function redirect_back(): void {
		$fallback = admin_url( 'admin.php?page=statnive' );
		$referer  = wp_get_referer();
		wp_safe_redirect( ( false !== $referer && '' !== $referer ) ? $referer : $fallback );
		exit;
	}
}
