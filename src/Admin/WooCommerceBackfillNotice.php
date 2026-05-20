<?php

declare(strict_types=1);

namespace Statnive\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Statnive\Capability;
use Statnive\Integration\WooCommerce\BackfillService;
use Statnive\Integration\WooCommerce\Detector;

/**
 * Statnive-page-scoped admin notice for the WooCommerce backfill job.
 *
 * The job itself auto-starts (see BackfillService::auto_start_if_needed)
 * — this notice exists to explain what's happening and to surface a
 * one-click retry on failure. There is no "Backfill now" prompt during
 * normal flow: the user just sees "Statnive is importing your existing
 * WooCommerce orders…" and progress.
 *
 * Notice states:
 *   - idle / no gap         → hidden
 *   - pending / running     → informational (no buttons)
 *   - done                  → green flash, dismissible per-user (30 days)
 *   - failed                → red, [Retry] button + CLI fallback string
 */
final class WooCommerceBackfillNotice {

	/**
	 * Admin-post action that re-triggers a failed backfill.
	 */
	public const RETRY_ACTION = 'statnive_wc_backfill_retry';

	/**
	 * Admin-post action that dismisses the "done" notice for the
	 * current user (per-user 30-day transient).
	 */
	public const DISMISS_ACTION = 'statnive_wc_backfill_dismiss';

	/**
	 * Per-user dismissal transient prefix (transient keys append the user ID).
	 */
	private const DISMISS_TRANSIENT_PREFIX = 'statnive_wc_backfill_dismiss_';

	/**
	 * One-shot flash from the retry handler to the next admin pageview.
	 */
	private const FLASH_TRANSIENT_PREFIX = 'statnive_wc_backfill_flash_';

	/**
	 * Register hook callbacks.
	 */
	public static function init(): void {
		add_action( 'admin_notices', [ self::class, 'maybe_show_notice' ] );
		add_action( 'admin_post_' . self::RETRY_ACTION, [ self::class, 'handle_retry' ] );
		add_action( 'admin_post_' . self::DISMISS_ACTION, [ self::class, 'handle_dismiss' ] );
	}

	/**
	 * Render the backfill notice when applicable.
	 *
	 * Scoped to Statnive pages only — checks current_screen against
	 * {@see ReactHandler::HOOK_SUFFIXES}.
	 */
	public static function maybe_show_notice(): void {
		$screen = get_current_screen();
		if ( null === $screen || ! in_array( $screen->id, ReactHandler::HOOK_SUFFIXES, true ) ) {
			return;
		}
		if ( ! Capability::can_view_reports() ) {
			return;
		}
		if ( ! Detector::is_active() ) {
			return;
		}

		self::maybe_show_flash();

		$payload = BackfillService::status_payload();
		$state   = $payload['state'];
		$status  = (string) $state['status'];

		switch ( $status ) {
			case BackfillService::STATUS_PENDING:
			case BackfillService::STATUS_RUNNING:
				if ( ! (bool) $payload['has_gap'] && BackfillService::STATUS_RUNNING !== $status ) {
					return;
				}
				self::render_running( $state );
				return;
			case BackfillService::STATUS_DONE:
				if ( self::is_dismissed_for_current_user() ) {
					return;
				}
				self::render_done( $state );
				return;
			case BackfillService::STATUS_FAILED:
				self::render_failed( $state );
				return;
			default:
				if ( ! (bool) $payload['has_gap'] ) {
					return;
				}
				if ( ! (bool) $payload['action_scheduler_available'] ) {
					self::render_no_scheduler();
					return;
				}
				// Idle with a gap: the auto-trigger has either fired
				// already (state should be pending) or will on the next
				// admin_init. Render the pending copy as a placeholder.
				self::render_running(
					array_merge( $state, [ 'status' => BackfillService::STATUS_PENDING ] )
				);
		}
	}

	/**
	 * "Statnive is importing your existing WooCommerce orders" — running variant.
	 *
	 * @param array<string, mixed> $state Current backfill state.
	 */
	private static function render_running( array $state ): void {
		$processed = max( 0, (int) ( $state['processed'] ?? 0 ) );
		$total     = max( 0, (int) ( $state['total'] ?? 0 ) );
		$percent   = $total > 0 ? min( 100, (int) round( ( $processed / $total ) * 100 ) ) : 0;

		echo '<div class="notice notice-info"><p><strong>';
		esc_html_e( 'Statnive is importing your existing WooCommerce orders.', 'statnive' );
		echo '</strong></p><p>';
		if ( $total > 0 ) {
			printf(
				/* translators: 1: processed count, 2: total count, 3: percent done */
				esc_html__( 'Imported %1$s of %2$s orders (%3$d%%). Refresh the Revenue Report to see updated totals — data updates live as the import runs.', 'statnive' ),
				esc_html( number_format_i18n( $processed ) ),
				esc_html( number_format_i18n( $total ) ),
				(int) $percent
			);
		} else {
			esc_html_e( 'Starting up — this happens automatically once and runs in the background.', 'statnive' );
		}
		echo '</p></div>';
	}

	/**
	 * "Imported N orders. Your Revenue Report is up to date."
	 *
	 * @param array<string, mixed> $state Current backfill state.
	 */
	private static function render_done( array $state ): void {
		$processed = max( 0, (int) ( $state['processed'] ?? 0 ) );

		echo '<div class="notice notice-success is-dismissible"><p><strong>';
		printf(
			/* translators: %s: number of imported orders */
			esc_html__( 'Statnive imported %s WooCommerce orders. Your Revenue Report is up to date.', 'statnive' ),
			esc_html( number_format_i18n( $processed ) )
		);
		echo '</strong></p><p>';
		self::render_post_button(
			self::DISMISS_ACTION,
			__( 'Dismiss', 'statnive' ),
			false,
			__( 'Dismiss this Statnive import success notice', 'statnive' )
		);
		echo '</p></div>';
	}

	/**
	 * Failure variant with retry + CLI fallback.
	 *
	 * @param array<string, mixed> $state Current backfill state.
	 */
	private static function render_failed( array $state ): void {
		$last_error = isset( $state['last_error'] ) ? (string) $state['last_error'] : '';

		echo '<div class="notice notice-error"><p><strong>';
		esc_html_e( "Statnive couldn't finish importing your WooCommerce orders.", 'statnive' );
		echo '</strong></p>';

		if ( '' !== $last_error ) {
			echo '<p><code>', esc_html( $last_error ), '</code></p>';
		}

		echo '<p>';
		self::render_post_button( self::RETRY_ACTION, __( 'Try again', 'statnive' ), true );
		echo '</p><p><em>';
		printf(
			/* translators: %s: literal WP-CLI command, not translatable */
			esc_html__( 'You can also run %s from the command line for a manual import.', 'statnive' ),
			'<code>wp statnive wc-backfill</code>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
		echo '</em></p></div>';
	}

	/**
	 * Action Scheduler unavailable variant.
	 */
	private static function render_no_scheduler(): void {
		echo '<div class="notice notice-warning"><p><strong>';
		esc_html_e( "Statnive can't import your existing WooCommerce orders automatically on this host.", 'statnive' );
		echo '</strong></p><p>';
		printf(
			/* translators: %s: literal WP-CLI command */
			esc_html__( 'Background scheduling (Action Scheduler) is not available. Run %s from the command line to import historical orders.', 'statnive' ),
			'<code>wp statnive wc-backfill</code>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
		echo '</p></div>';
	}

	/**
	 * Render an `admin-post.php` button inside its own inline form.
	 *
	 * @param string  $action  Action name (also nonce action).
	 * @param string  $label   Visible label.
	 * @param bool    $primary True to use button-primary styling.
	 * @param ?string $aria    Optional aria-label.
	 */
	private static function render_post_button( string $action, string $label, bool $primary, ?string $aria = null ): void {
		$button_class = $primary ? 'button button-primary' : 'button';

		printf(
			'<form method="post" action="%s" style="display:inline-block;margin-right:0.5em;">',
			esc_url( admin_url( 'admin-post.php' ) )
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
	 * Render the one-shot flash from a retry click, if any.
	 */
	private static function maybe_show_flash(): void {
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

		$class = ! empty( $flash['ok'] ) ? 'notice-success' : 'notice-error';
		printf(
			'<div class="notice %s is-dismissible"><p>%s</p></div>',
			esc_attr( $class ),
			esc_html( (string) $flash['message'] )
		);
	}

	/**
	 * Per-user "Done" dismissal check (30-day transient).
	 */
	private static function is_dismissed_for_current_user(): bool {
		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return false;
		}
		return false !== get_transient( self::DISMISS_TRANSIENT_PREFIX . $user_id );
	}

	/**
	 * Admin-post handler that re-triggers a failed backfill.
	 */
	public static function handle_retry(): void {
		check_admin_referer( self::RETRY_ACTION );
		if ( ! Capability::can_view_reports() ) {
			wp_die(
				esc_html__( 'You do not have permission to run the WooCommerce import.', 'statnive' ),
				'',
				[ 'response' => 403 ]
			);
		}

		$result  = BackfillService::start();
		$ok      = (bool) $result['ok'];
		$message = $ok
			? __( 'Statnive is importing your existing WooCommerce orders.', 'statnive' )
			: __( 'Statnive could not start the import. Please try again later.', 'statnive' );

		$user_id = get_current_user_id();
		if ( $user_id > 0 ) {
			set_transient(
				self::FLASH_TRANSIENT_PREFIX . $user_id,
				[
					'ok'      => $ok,
					'message' => $message,
				],
				MINUTE_IN_SECONDS
			);
		}

		self::redirect_back();
	}

	/**
	 * Admin-post handler that dismisses the "done" notice for the user.
	 */
	public static function handle_dismiss(): void {
		check_admin_referer( self::DISMISS_ACTION );
		if ( ! Capability::can_view_reports() ) {
			wp_die(
				esc_html__( 'You do not have permission to dismiss this notice.', 'statnive' ),
				'',
				[ 'response' => 403 ]
			);
		}

		$user_id = get_current_user_id();
		if ( $user_id > 0 ) {
			set_transient(
				self::DISMISS_TRANSIENT_PREFIX . $user_id,
				1,
				30 * DAY_IN_SECONDS
			);
		}

		self::redirect_back();
	}

	/**
	 * Safe-redirect back to the referring admin page.
	 */
	private static function redirect_back(): void {
		$fallback = admin_url( 'admin.php?page=statnive-revenue' );
		$referer  = wp_get_referer();
		wp_safe_redirect( ( false !== $referer && '' !== $referer ) ? $referer : $fallback );
		exit;
	}
}
