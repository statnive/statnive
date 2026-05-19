<?php

declare(strict_types=1);

namespace Statnive\Integration\WooCommerce;

use Statnive\Service\EventService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Captures the 4-step WooCommerce funnel into the existing `statnive_events`
 * table.
 *
 * Step 1  wc_product_view      — template_redirect + is_product()
 * Step 2  wc_add_to_cart       — woocommerce_add_to_cart
 * Step 3a wc_checkout_start    — woocommerce_checkout_order_processed (classic)
 * Step 3b wc_checkout_start    — woocommerce_store_api_checkout_order_processed (block)
 * Step 4  wc_purchase          — derived from statnive_orders at query time
 *
 * Purchase is derived (not stored as an event) because the order row in
 * statnive_orders already carries the ground truth — there's no value in
 * a duplicate event row.
 *
 * READ-ONLY against WooCommerce: every callback only reads scalar args
 * passed by the hook, never queries WC tables or writes anywhere except
 * the statnive_events table via EventService::record().
 */
final class FunnelEvents {

	private const EVENT_PRODUCT_VIEW   = 'wc_product_view';
	private const EVENT_ADD_TO_CART    = 'wc_add_to_cart';
	private const EVENT_CHECKOUT_START = 'wc_checkout_start';

	/**
	 * Product view — `template_redirect` listener.
	 *
	 * Defers any work until `is_product()` is true to avoid touching
	 * non-product front-end traffic. WC core is required for is_product()
	 * to be defined.
	 */
	public static function on_product_view(): void {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}
		if ( ! Detector::is_active() ) {
			return;
		}
		if ( ! (bool) apply_filters( 'statnive_should_track', true ) ) {
			return;
		}

		$product_id = self::current_product_id();
		if ( 0 === $product_id ) {
			return;
		}

		EventService::record(
			self::EVENT_PRODUCT_VIEW,
			[ 'product_id' => $product_id ]
		);
	}

	/**
	 * Add to cart — `woocommerce_add_to_cart` listener.
	 *
	 * @param string $cart_item_key Cart item key (unused for storage).
	 * @param int    $product_id    Added product ID.
	 * @param int    $quantity      Quantity added.
	 * @param int    $variation_id  Variation ID (0 for simple products).
	 */
	public static function on_add_to_cart( $cart_item_key, $product_id, $quantity, $variation_id = 0 ): void {
		unset( $cart_item_key );
		if ( ! Detector::is_active() ) {
			return;
		}
		if ( ! (bool) apply_filters( 'statnive_should_track', true ) ) {
			return;
		}

		EventService::record(
			self::EVENT_ADD_TO_CART,
			[
				'product_id'   => (int) $product_id,
				'variation_id' => (int) $variation_id,
				'quantity'     => (int) $quantity,
			]
		);
	}

	/**
	 * Classic checkout start — `woocommerce_checkout_order_processed` listener.
	 *
	 * Fires once at order finalisation on the classic shortcode checkout.
	 * Does NOT fire on the block checkout — see {@see on_blocks_checkout_start}.
	 *
	 * @param int $order_id WC order ID.
	 */
	public static function on_classic_checkout_start( $order_id ): void {
		if ( ! Detector::is_active() ) {
			return;
		}
		if ( ! (bool) apply_filters( 'statnive_should_track', true ) ) {
			return;
		}
		EventService::record(
			self::EVENT_CHECKOUT_START,
			[
				'order_id' => (int) $order_id,
				'channel'  => 'classic',
			]
		);
	}

	/**
	 * Block checkout start — `woocommerce_store_api_checkout_order_processed`.
	 *
	 * Replaces the deprecated `woocommerce_blocks_checkout_order_processed`
	 * since WC Blocks 7.2.0.
	 *
	 * @param \WC_Order $order WC order, freshly drafted by the Store API.
	 */
	public static function on_blocks_checkout_start( $order ): void {
		if ( ! Detector::is_active() ) {
			return;
		}
		if ( ! (bool) apply_filters( 'statnive_should_track', true ) ) {
			return;
		}
		$order_id = ( $order instanceof \WC_Order ) ? (int) $order->get_id() : 0;
		EventService::record(
			self::EVENT_CHECKOUT_START,
			[
				'order_id' => $order_id,
				'channel'  => 'blocks',
			]
		);
	}

	/**
	 * Resolve the current product ID inside an `is_product()` template.
	 *
	 * Single source of truth so any future refactor (e.g. supporting
	 * grouped or external products explicitly) lives in one place.
	 */
	private static function current_product_id(): int {
		global $product, $post;
		if ( is_object( $product ) && method_exists( $product, 'get_id' ) ) {
			return (int) $product->get_id();
		}
		if ( $post instanceof \WP_Post ) {
			return (int) $post->ID;
		}
		return 0;
	}
}
