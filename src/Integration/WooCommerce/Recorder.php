<?php

declare(strict_types=1);

namespace Statnive\Integration\WooCommerce;

use Statnive\Database\TableRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce → Statnive order capture.
 *
 * Listens on status-transition + refund + delete hooks (NOT
 * `woocommerce_thankyou`, which is unreliable). Idempotent UPSERT keyed
 * on `wc_order_id` so duplicate hook firings produce at most one row.
 *
 * Critical invariants (verified by code review and CI grep):
 *
 *   1. READ-ONLY against WooCommerce. Only calls `$order->get_*()`
 *      getters. NEVER calls `$order->set_*()`, `$order->save()`,
 *      `$order->update_meta_data()`, or any other mutator.
 *   2. NEVER queries `wp_posts` / `wp_postmeta` for orders. Uses
 *      `wc_get_order()` only.
 *   3. NEVER writes to WC tables. Only `statnive_*` tables.
 *   4. Every public hook callback is routed through SafeHook so any
 *      Throwable is caught and the WC order flow continues normally.
 *   5. Honours the `statnive_should_track` filter the way the rest of
 *      Statnive does — when tracking is disabled by the site owner,
 *      orders are skipped entirely.
 *
 * Status policy (matches WC Analytics defaults):
 *   - processing / completed → counted in revenue
 *   - on-hold / pending      → recorded with status, NOT counted
 *   - cancelled / failed     → status updated, NOT counted
 *   - refunded               → refund row + status flag (PR 5 wires
 *                              the refund hook itself)
 */
final class Recorder {

	/**
	 * Capture (or refresh) the order row when WC marks it processing/completed.
	 *
	 * Idempotent — calling this twice for the same order produces one row,
	 * with the second call updating mutable fields.
	 *
	 * @param int $wc_order_id WooCommerce order ID.
	 */
	public static function on_paid_or_paying( int $wc_order_id ): void {
		$order = self::resolve_order( $wc_order_id );
		if ( null === $order ) {
			return;
		}
		self::upsert_order_row( $order );
		self::upsert_attribution_row( $order );
		self::upsert_items( $order );
		self::upsert_coupons( $order );
	}

	/**
	 * Pre-create the order row at the moment the order is first persisted.
	 *
	 * Status is whatever WC set (commonly `pending` or `on-hold` for BACS).
	 * Revenue is NOT counted until status transitions to processing/completed.
	 *
	 * @param int $wc_order_id WooCommerce order ID.
	 */
	public static function on_new_order( int $wc_order_id ): void {
		$order = self::resolve_order( $wc_order_id );
		if ( null === $order ) {
			return;
		}
		self::upsert_order_row( $order );
		self::upsert_attribution_row( $order );
		self::upsert_items( $order );
		self::upsert_coupons( $order );
	}

	/**
	 * Mark the order as cancelled / failed; preserve the row for audit.
	 *
	 * @param int $wc_order_id WooCommerce order ID.
	 */
	public static function on_cancelled_or_failed( int $wc_order_id ): void {
		$order = self::resolve_order( $wc_order_id );
		if ( null === $order ) {
			return;
		}
		self::upsert_order_row( $order );
	}

	/**
	 * Soft-delete the Statnive row when WC deletes the order.
	 *
	 * Never DELETEs; sets `deleted_at` so historical reports remain
	 * consistent at the time they were originally rendered.
	 *
	 * @param int $wc_order_id WooCommerce order ID.
	 */
	public static function on_delete( int $wc_order_id ): void {
		global $wpdb;
		$table = TableRegistry::get( 'orders' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}statnive_orders SET deleted_at = %s WHERE wc_order_id = %d",
				gmdate( 'Y-m-d H:i:s' ),
				$wc_order_id
			)
		);
		unset( $table );
	}

	/**
	 * Record a refund row + roll-up onto the parent order.
	 *
	 * Hooked on `woocommerce_order_refunded( $order_id, $refund_id )`.
	 * Storage policy: amounts are POSITIVE in our tables (WC's refund
	 * items are stored negative; we abs() everything on read).
	 *
	 * @param int $wc_order_id  Parent WC order ID.
	 * @param int $wc_refund_id WC refund ID.
	 */
	public static function on_refund( int $wc_order_id, int $wc_refund_id ): void {
		if ( ! function_exists( 'wc_get_order' ) || ! Detector::is_active() ) {
			return;
		}
		$refund = wc_get_order( $wc_refund_id );
		if ( ! $refund instanceof \WC_Order_Refund ) {
			return;
		}
		$order = self::resolve_order( $wc_order_id );
		if ( null === $order ) {
			return;
		}

		// Ensure parent order row + items exist before we roll refunds onto them.
		self::upsert_order_row( $order );
		self::upsert_items( $order );

		$amount   = abs( (float) $refund->get_amount() );
		$reason   = (string) $refund->get_reason();
		$date     = $refund->get_date_created();
		$date_gmt = $date ? gmdate( 'Y-m-d H:i:s', $date->getTimestamp() ) : gmdate( 'Y-m-d H:i:s' );
		$user_id  = (int) $refund->get_refunded_by();

		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		// Idempotent on wc_refund_id — partial refunds aren't mutable.
		$wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO {$wpdb->prefix}statnive_order_refunds
					(order_id, wc_refund_id, amount, reason, date_gmt, refunded_by_user)
				 VALUES (%d, %d, %f, %s, %s, %d)",
				$wc_order_id,
				$wc_refund_id,
				$amount,
				substr( $reason, 0, 255 ),
				$date_gmt,
				$user_id
			)
		);

		// Roll up per-line refund amounts onto order_items.refund_*.
		foreach ( $refund->get_items( 'line_item' ) as $refund_item ) {
			if ( ! method_exists( $refund_item, 'get_meta' ) ) {
				continue;
			}
			$source_item_id = (int) $refund_item->get_meta( '_refunded_item_id' );
			if ( 0 === $source_item_id ) {
				continue;
			}
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->prefix}statnive_order_items
					 SET refund_quantity = refund_quantity + %d,
						 refund_amount   = refund_amount   + %f
					 WHERE order_id = %d AND wc_order_item_id = %d",
					abs( (int) $refund_item->get_quantity() ),
					abs( (float) $refund_item->get_total() ),
					$wc_order_id,
					$source_item_id
				)
			);
		}

		// Roll up the order-level refund total.
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}statnive_orders
				 SET refund_total = (
						SELECT COALESCE(SUM(amount),0)
						FROM {$wpdb->prefix}statnive_order_refunds
						WHERE order_id = %d
					 ),
					 date_updated_gmt = %s
				 WHERE wc_order_id = %d",
				$wc_order_id,
				gmdate( 'Y-m-d H:i:s' ),
				$wc_order_id
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Replace the line-item snapshot for an order.
	 *
	 * On UPSERT we delete-then-insert because items can be reordered or
	 * removed during admin edits; tracking those diffs is more work than
	 * just refreshing the whole snapshot per order on every hook firing.
	 *
	 * Preserves refund_quantity + refund_amount across reinsert by
	 * matching on (order_id, wc_order_item_id) UNIQUE.
	 *
	 * @param \WC_Order $order Read-only WooCommerce order object.
	 */
	private static function upsert_items( \WC_Order $order ): void {
		global $wpdb;
		$order_id = (int) $order->get_id();
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		foreach ( $order->get_items( 'line_item' ) as $wc_item_id => $item ) {
			if ( ! method_exists( $item, 'get_product' ) ) {
				continue;
			}
			$product       = $item->get_product();
			$product_id    = $item instanceof \WC_Order_Item_Product ? (int) $item->get_product_id() : 0;
			$variation_id  = $item instanceof \WC_Order_Item_Product ? (int) $item->get_variation_id() : 0;
			$parent        = $variation_id > 0 ? $product_id : ( 0 === $product_id ? 0 : $product_id );
			$sku           = $product ? (string) $product->get_sku() : '';
			$snapshot_name = (string) $item->get_name();

			$wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$wpdb->prefix}statnive_order_items
						(order_id, wc_order_item_id, product_id, variation_id, parent_product_id,
						 sku, product_name, quantity, subtotal, total, tax_total)
					 VALUES (%d, %d, %d, %d, %d, %s, %s, %d, %f, %f, %f)
					 ON DUPLICATE KEY UPDATE
						product_id        = VALUES(product_id),
						variation_id      = VALUES(variation_id),
						parent_product_id = VALUES(parent_product_id),
						sku               = VALUES(sku),
						product_name      = VALUES(product_name),
						quantity          = VALUES(quantity),
						subtotal          = VALUES(subtotal),
						total             = VALUES(total),
						tax_total         = VALUES(tax_total)",
					$order_id,
					(int) $wc_item_id,
					$product_id,
					$variation_id > 0 ? $variation_id : null,
					$parent,
					substr( $sku, 0, 100 ),
					substr( $snapshot_name, 0, 255 ),
					(int) $item->get_quantity(),
					(float) $item->get_subtotal(),
					(float) $item->get_total(),
					(float) $item->get_total_tax()
				)
			);
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Replace the coupon snapshot for an order.
	 *
	 * Uses $order->get_coupon_codes() (the canonical accessor per the WC
	 * 3.7 deprecation of get_used_coupons()) joined to coupon line items
	 * for per-coupon discount amounts.
	 *
	 * @param \WC_Order $order Read-only WooCommerce order object.
	 */
	private static function upsert_coupons( \WC_Order $order ): void {
		global $wpdb;
		$order_id = (int) $order->get_id();

		// Build code => discount map from the coupon line items (most accurate).
		$by_code = [];
		foreach ( $order->get_items( 'coupon' ) as $coupon_item ) {
			if ( ! method_exists( $coupon_item, 'get_code' ) ) {
				continue;
			}
			$code            = (string) $coupon_item->get_code();
			$discount_amount = (float) $coupon_item->get_discount();
			$discount_tax    = (float) $coupon_item->get_discount_tax();
			$by_code[ strtolower( $code ) ] = [
				'code'            => $code,
				'discount_amount' => $discount_amount,
				'discount_tax'    => $discount_tax,
			];
		}

		// Fall back to get_coupon_codes() for any code that didn't surface
		// as a line item (older WC versions).
		foreach ( $order->get_coupon_codes() as $code ) {
			$key = strtolower( (string) $code );
			if ( ! isset( $by_code[ $key ] ) ) {
				$by_code[ $key ] = [
					'code'            => (string) $code,
					'discount_amount' => 0.0,
					'discount_tax'    => 0.0,
				];
			}
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		foreach ( $by_code as $code_lower => $row ) {
			$coupon_id = 0;
			if ( function_exists( 'wc_get_coupon_id_by_code' ) ) {
				$coupon_id = (int) wc_get_coupon_id_by_code( $row['code'] );
			}
			$wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$wpdb->prefix}statnive_order_coupons
						(order_id, coupon_id, code, code_lower, discount_amount, discount_tax)
					 VALUES (%d, %d, %s, %s, %f, %f)
					 ON DUPLICATE KEY UPDATE
						coupon_id       = VALUES(coupon_id),
						code            = VALUES(code),
						discount_amount = VALUES(discount_amount),
						discount_tax    = VALUES(discount_tax)",
					$order_id,
					$coupon_id > 0 ? $coupon_id : null,
					substr( $row['code'], 0, 100 ),
					substr( $code_lower, 0, 100 ),
					(float) $row['discount_amount'],
					(float) $row['discount_tax']
				)
			);
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Read the WC order or return null when the integration should bail.
	 *
	 * Read-only — wc_get_order() returns the order object built from
	 * either the wp_posts/postmeta or HPOS wc_orders table, transparently.
	 *
	 * @param int $wc_order_id WooCommerce order ID.
	 */
	private static function resolve_order( int $wc_order_id ): ?\WC_Order {
		if ( ! function_exists( 'wc_get_order' ) ) {
			return null;
		}
		if ( ! Detector::is_active() ) {
			return null;
		}

		/**
		 * Filter: allow opt-out per request / per site.
		 *
		 * Matches the convention used by HitController, EventController,
		 * etc. so site owners only have one toggle to learn.
		 */
		if ( ! (bool) apply_filters( 'statnive_should_track', true ) ) {
			return null;
		}

		$order = wc_get_order( $wc_order_id );
		if ( ! $order instanceof \WC_Order ) {
			return null;
		}

		// Skip refund "orders" — they're handled by the refund hook (PR 5).
		if ( $order->get_type() === 'shop_order_refund' ) {
			return null;
		}

		return $order;
	}

	/**
	 * UPSERT the `statnive_orders` row from a WC_Order.
	 *
	 * Idempotent on wc_order_id. Existing rows have mutable fields
	 * (status, totals, dates) refreshed; the `deleted_at` column is
	 * preserved by NOT including it in the UPDATE clause.
	 *
	 * @param \WC_Order $order Read-only WooCommerce order object.
	 */
	private static function upsert_order_row( \WC_Order $order ): void {
		global $wpdb;
		$table = $wpdb->prefix . 'statnive_orders';

		$gross    = (float) $order->get_total();
		$tax      = (float) $order->get_total_tax();
		$shipping = (float) $order->get_shipping_total();
		$discount = (float) $order->get_total_discount();
		$net      = max( 0.0, $gross - $tax - $shipping );

		$email_hash = EmailHash::hash( (string) $order->get_billing_email() );

		$date_created = $order->get_date_created();
		$date_paid    = $order->get_date_paid();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->prefix}statnive_orders
					(wc_order_id, wc_order_key, parent_order_id, status, currency,
					 gross_total, net_total, tax_total, shipping_total, discount_total,
					 item_count, customer_email_hash, customer_user_id,
					 is_first_purchase, date_created_gmt, date_paid_gmt,
					 date_updated_gmt, created_via, payment_method)
				 VALUES (%d, %s, %d, %s, %s,
					 %f, %f, %f, %f, %f,
					 %d, %s, %d,
					 %d, %s, %s,
					 %s, %s, %s)
				 ON DUPLICATE KEY UPDATE
					status            = VALUES(status),
					currency          = VALUES(currency),
					gross_total       = VALUES(gross_total),
					net_total         = VALUES(net_total),
					tax_total         = VALUES(tax_total),
					shipping_total    = VALUES(shipping_total),
					discount_total    = VALUES(discount_total),
					item_count        = VALUES(item_count),
					customer_email_hash = COALESCE(VALUES(customer_email_hash), customer_email_hash),
					customer_user_id  = VALUES(customer_user_id),
					date_paid_gmt     = VALUES(date_paid_gmt),
					date_updated_gmt  = VALUES(date_updated_gmt),
					payment_method    = VALUES(payment_method)",
				(int) $order->get_id(),
				(string) $order->get_order_key(),
				(int) $order->get_parent_id(),
				(string) $order->get_status(),
				(string) $order->get_currency(),
				$gross,
				$net,
				$tax,
				$shipping,
				$discount,
				(int) $order->get_item_count(),
				null === $email_hash ? '' : $email_hash,
				(int) $order->get_customer_id(),
				self::detect_first_purchase( $email_hash, (int) $order->get_id() ),
				$date_created ? gmdate( 'Y-m-d H:i:s', $date_created->getTimestamp() ) : gmdate( 'Y-m-d H:i:s' ),
				$date_paid ? gmdate( 'Y-m-d H:i:s', $date_paid->getTimestamp() ) : null,
				gmdate( 'Y-m-d H:i:s' ),
				(string) $order->get_created_via(),
				(string) $order->get_payment_method()
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		unset( $table );
	}

	/**
	 * UPSERT the `statnive_order_attribution` row (1:1 with orders).
	 *
	 * @param \WC_Order $order Read-only WooCommerce order object.
	 */
	private static function upsert_attribution_row( \WC_Order $order ): void {
		global $wpdb;
		$row   = AttributionResolver::resolve( $order );
		$table = $wpdb->prefix . 'statnive_order_attribution';
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->replace( $table, $row );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Is this the customer's first purchase on this site (by hashed email)?
	 *
	 * @param string|null $email_hash The current order's customer email hash.
	 * @param int         $order_id   The current order's ID — excluded from
	 *                                the lookup so re-running the upsert
	 *                                doesn't flip a true into a false.
	 */
	private static function detect_first_purchase( ?string $email_hash, int $order_id ): int {
		if ( null === $email_hash ) {
			return 0;
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$prior = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}statnive_orders
				 WHERE customer_email_hash = %s
				   AND wc_order_id != %d
				   AND deleted_at IS NULL
				   AND status IN ('processing','completed')",
				$email_hash,
				$order_id
			)
		);
		return 0 === $prior ? 1 : 0;
	}
}
