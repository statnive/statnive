<?php

declare(strict_types=1);

namespace Statnive\Integration\WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exception-isolating wrapper for WooCommerce hook callbacks.
 *
 * Statnive listens on WC status-transition + refund + cart hooks. Any
 * uncaught exception thrown from one of those callbacks would propagate
 * into WooCommerce's own code path — and at order-creation time that
 * propagation can corrupt the order, block the customer's checkout, or
 * leave WC in a half-finished state. None of that is acceptable.
 *
 * Every Statnive WC listener routes through `SafeHook::wrap()`, which:
 *   1. Catches all `Throwable`s.
 *   2. Increments the `statnive_failed_requests` counter (shared with
 *      HitController and the diagnostics surface) so site owners and the
 *      `/statnive/v1/diagnostics` endpoint can see degradation.
 *   3. Optionally logs via `error_log()` when WP_DEBUG_LOG is on.
 *   4. Swallows the exception so WC never sees it.
 *
 * The wrapper is intentionally a thin helper; callbacks should still aim
 * to not throw at all.
 */
final class SafeHook {

	private const FAILURE_OPTION = 'statnive_failed_requests';

	/**
	 * Wrap a callable so any throwable is caught and recorded.
	 *
	 * Usage:
	 *
	 *     add_action(
	 *         'woocommerce_order_status_processing',
	 *         SafeHook::wrap( [ Recorder::class, 'onPaidOrPaying' ] )
	 *     );
	 *
	 * @param callable $callback The original listener.
	 * @return callable A wrapped callback with the same signature.
	 */
	public static function wrap( callable $callback ): callable {
		return static function ( ...$args ) use ( $callback ) {
			try {
				return $callback( ...$args );
			} catch ( \Throwable $e ) {
				self::record_failure( $e );
				return null;
			}
		};
	}

	/**
	 * Record a captured exception for diagnostics.
	 *
	 * @param \Throwable $e Exception thrown by the wrapped callback.
	 */
	private static function record_failure( \Throwable $e ): void {
		$count = (int) get_option( self::FAILURE_OPTION, 0 );
		update_option( self::FAILURE_OPTION, $count + 1, false );

		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- diagnostic logging, gated on WP_DEBUG_LOG.
			error_log( sprintf( 'Statnive WC hook failure: %s in %s:%d', $e->getMessage(), $e->getFile(), $e->getLine() ) );
		}
	}
}
