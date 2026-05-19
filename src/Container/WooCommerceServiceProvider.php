<?php

declare(strict_types=1);

namespace Statnive\Container;

use Statnive\Integration\WooCommerce\FunnelEvents;
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

		add_action( 'woocommerce_new_order', SafeHook::wrap( [ Recorder::class, 'on_new_order' ] ) );

		add_action( 'woocommerce_order_status_processing', SafeHook::wrap( [ Recorder::class, 'on_paid_or_paying' ] ) );
		add_action( 'woocommerce_order_status_completed', SafeHook::wrap( [ Recorder::class, 'on_paid_or_paying' ] ) );
		add_action( 'woocommerce_order_status_on-hold', SafeHook::wrap( [ Recorder::class, 'on_paid_or_paying' ] ) );

		add_action( 'woocommerce_order_status_cancelled', SafeHook::wrap( [ Recorder::class, 'on_cancelled_or_failed' ] ) );
		add_action( 'woocommerce_order_status_failed', SafeHook::wrap( [ Recorder::class, 'on_cancelled_or_failed' ] ) );

		add_action( 'woocommerce_delete_order', SafeHook::wrap( [ Recorder::class, 'on_delete' ] ) );

		// Refund hooks (PR 5). woocommerce_order_refunded is canonical;
		// woocommerce_update_order is known not to fire on partial
		// refunds (WC issue #22072) so we don't rely on it.
		add_action( 'woocommerce_order_refunded', SafeHook::wrap( [ Recorder::class, 'on_refund' ] ), 10, 2 );

		// woocommerce_order_status_refunded also marks the parent order
		// status; route through the same paid_or_paying upsert so totals
		// stay in sync.
		add_action( 'woocommerce_order_status_refunded', SafeHook::wrap( [ Recorder::class, 'on_paid_or_paying' ] ) );

		// Funnel events (PR 6) — write into statnive_events. wc_purchase
		// is derived from statnive_orders at query time and so has no
		// dedicated hook here.
		add_action( 'template_redirect', SafeHook::wrap( [ FunnelEvents::class, 'on_product_view' ] ) );
		add_action( 'woocommerce_add_to_cart', SafeHook::wrap( [ FunnelEvents::class, 'on_add_to_cart' ] ), 10, 4 );

		// Classic shortcode checkout.
		add_action( 'woocommerce_checkout_order_processed', SafeHook::wrap( [ FunnelEvents::class, 'on_classic_checkout_start' ] ) );

		// Block checkout — Store API equivalent.
		add_action( 'woocommerce_store_api_checkout_order_processed', SafeHook::wrap( [ FunnelEvents::class, 'on_blocks_checkout_start' ] ) );
	}
}
