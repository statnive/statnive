<?php

declare(strict_types=1);

namespace Statnive\Container;

use Statnive\Integration\WooCommerce\Recorder;
use Statnive\Integration\WooCommerce\SafeHook;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wires WooCommerce status-transition + refund + delete hooks to the
 * Statnive Recorder. Every callback routes through SafeHook so any
 * Throwable in Statnive code can never propagate into WC's order flow.
 *
 * No-op when WooCommerce is absent — Statnive activates without WC.
 */
final class WooCommerceServiceProvider implements ServiceProvider {

	/**
	 * No services registered in the container — this provider is pure
	 * hook wiring. Static services (Recorder, AttributionResolver) are
	 * stateless and addressed directly.
	 *
	 * @param ServiceContainer $container Container instance (unused).
	 */
	public function register( ServiceContainer $container ): void {
		unset( $container );
	}

	/**
	 * Wire WooCommerce hooks. Every callback is exception-isolated.
	 *
	 * Order-flow trigger map:
	 *   woocommerce_new_order              → pre-create row (status whatever WC set)
	 *   woocommerce_order_status_processing → UPSERT, status counted
	 *   woocommerce_order_status_completed  → UPSERT, status counted
	 *   woocommerce_order_status_on-hold    → UPSERT, status NOT counted
	 *   woocommerce_order_status_cancelled  → UPSERT, status NOT counted
	 *   woocommerce_order_status_failed     → UPSERT, status NOT counted
	 *   woocommerce_delete_order            → soft-delete
	 *
	 * Refund + items + coupons hooks land in PR 5; funnel-event hooks
	 * land in PR 6.
	 *
	 * @param ServiceContainer $container Container instance (unused).
	 */
	public function boot( ServiceContainer $container ): void {
		unset( $container );

		add_action( 'woocommerce_new_order', SafeHook::wrap( [ Recorder::class, 'onNewOrder' ] ) );

		add_action( 'woocommerce_order_status_processing', SafeHook::wrap( [ Recorder::class, 'onPaidOrPaying' ] ) );
		add_action( 'woocommerce_order_status_completed', SafeHook::wrap( [ Recorder::class, 'onPaidOrPaying' ] ) );
		add_action( 'woocommerce_order_status_on-hold', SafeHook::wrap( [ Recorder::class, 'onPaidOrPaying' ] ) );

		add_action( 'woocommerce_order_status_cancelled', SafeHook::wrap( [ Recorder::class, 'onCancelledOrFailed' ] ) );
		add_action( 'woocommerce_order_status_failed', SafeHook::wrap( [ Recorder::class, 'onCancelledOrFailed' ] ) );

		add_action( 'woocommerce_delete_order', SafeHook::wrap( [ Recorder::class, 'onDelete' ] ) );
	}
}
