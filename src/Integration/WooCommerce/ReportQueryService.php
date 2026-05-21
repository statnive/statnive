<?php

declare(strict_types=1);

namespace Statnive\Integration\WooCommerce;

use Statnive\Database\TableRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// All SQL in this file interpolates Statnive table names (constants from
// $wpdb->prefix + a hardcoded suffix) into prepared statements, never
// user input. The InterpolatedNotPrepared sniff doesn't have visibility
// into that, so silence it file-wide. The PluginCheck variant is the
// same finding under a different sniff name (Plugin Check action).
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter

/**
 * Read-only SQL queries that power the Revenue Report.
 *
 * Architectural rule: this class is the ONLY place the Revenue Report
 * reads from statnive_orders + statnive_order_* tables. Controllers
 * (RevenueController) ask for shapes; this service returns arrays.
 * Never queries WC tables — that's the Recorder's job.
 *
 * Every method:
 *   - Returns plain PHP arrays / scalars (controllers handle JSON).
 *   - Accepts `$from` + `$to` as YYYY-MM-DD strings (validated upstream).
 *   - Filters `deleted_at IS NULL` and `status IN ('processing','completed')`
 *     for revenue queries — the WC Analytics default that v1.0.0 matches.
 */
final class ReportQueryService {

	/**
	 * Status filter — matches WC Analytics default revenue policy.
	 */
	private const COUNTED_STATUSES = "('processing','completed')";

	/**
	 * The column expression revenue reports use to date-bucket an order.
	 *
	 * `date_paid_gmt` is the canonical "when did revenue happen" timestamp.
	 * For normal checkouts it equals `date_created_gmt`. For subscription
	 * renewals and BACS / cheque / cash-on-delivery orders that sit pending
	 * before payment clears, the two diverge — and the user's mental model
	 * of revenue lines up with `date_paid_gmt`, not the original order
	 * creation. We coalesce so on-hold / pending orders (no payment yet)
	 * still appear under their creation date.
	 *
	 * Perf note: wrapping in COALESCE prevents the query planner from using
	 * the (status, date_created_gmt) composite index for date pruning — the
	 * status prefix still filters first, so impact is bounded by the count
	 * of `processing` + `completed` orders. Acceptable for v1.0.0; a stored
	 * generated column + index on this expression is a v1.x perf-pass item.
	 */
	private const REVENUE_DATE = 'COALESCE(date_paid_gmt, date_created_gmt)';

	/**
	 * Same column, qualified with table alias `o` for joined queries.
	 */
	private const REVENUE_DATE_O = 'COALESCE(o.date_paid_gmt, o.date_created_gmt)';

	/**
	 * Headline KPI summary for §1 Revenue Report.
	 *
	 * @param string $from Inclusive start date YYYY-MM-DD.
	 * @param string $to   Inclusive end date YYYY-MM-DD.
	 * @return array<string, float|int>
	 */
	public static function summary( string $from, string $to ): array {
		global $wpdb;
		$orders = $wpdb->prefix . 'statnive_orders';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(*)                    AS orders_count,
					COALESCE(SUM(gross_total),0) AS gross_total,
					COALESCE(SUM(net_total),0)   AS net_total,
					COALESCE(SUM(refund_total),0) AS refund_total,
					COALESCE(SUM(tax_total),0)   AS tax_total,
					COALESCE(SUM(shipping_total),0) AS shipping_total
				 FROM {$orders}
				 WHERE deleted_at IS NULL
				   AND status IN " . self::COUNTED_STATUSES . '
				   AND DATE(' . self::REVENUE_DATE . ') BETWEEN %s AND %s',
				$from,
				$to
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		$row          = is_array( $row ) ? $row : [];
		$orders_count = (int) ( $row['orders_count'] ?? 0 );
		$gross        = (float) ( $row['gross_total'] ?? 0 );
		$net          = (float) ( $row['net_total'] ?? 0 );
		$refunds      = (float) ( $row['refund_total'] ?? 0 );

		$net_revenue = max( 0.0, $net - $refunds );
		$aov         = $orders_count > 0 ? $net_revenue / $orders_count : 0.0;
		$refund_rate = $gross > 0 ? $refunds / $gross : 0.0;

		return [
			'orders'         => $orders_count,
			'gross_revenue'  => $gross,
			'net_revenue'    => $net_revenue,
			'refund_total'   => $refunds,
			'aov'            => $aov,
			'refund_rate'    => $refund_rate,
			'tax_total'      => (float) ( $row['tax_total'] ?? 0 ),
			'shipping_total' => (float) ( $row['shipping_total'] ?? 0 ),
		];
	}

	/**
	 * Daily timeseries of revenue + orders.
	 *
	 * @param string $from Start date.
	 * @param string $to   End date.
	 * @return array<int, array{date: string, revenue: float, orders: int}>
	 */
	public static function timeseries( string $from, string $to ): array {
		global $wpdb;
		$orders = $wpdb->prefix . 'statnive_orders';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT
					DATE(' . self::REVENUE_DATE . ') AS day,
					COALESCE(SUM(net_total - refund_total),0) AS revenue,
					COUNT(*) AS orders
				 FROM ' . $orders . '
				 WHERE deleted_at IS NULL
				   AND status IN ' . self::COUNTED_STATUSES . '
				   AND DATE(' . self::REVENUE_DATE . ') BETWEEN %s AND %s
				 GROUP BY DATE(' . self::REVENUE_DATE . ')
				 ORDER BY day ASC',
				$from,
				$to
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		return array_map(
			static fn( array $r ): array => [
				'date'    => (string) $r['day'],
				'revenue' => max( 0.0, (float) $r['revenue'] ),
				'orders'  => (int) $r['orders'],
			],
			is_array( $rows ) ? $rows : []
		);
	}

	/**
	 * Revenue by attributed channel.
	 *
	 * @param string $from Start date.
	 * @param string $to   End date.
	 * @return array<int, array<string, scalar>>
	 */
	public static function by_channel( string $from, string $to ): array {
		global $wpdb;
		$orders = $wpdb->prefix . 'statnive_orders';
		$attr   = $wpdb->prefix . 'statnive_order_attribution';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					COALESCE(a.channel,'Direct') AS channel,
					COUNT(*) AS orders,
					COALESCE(SUM(o.net_total - o.refund_total),0) AS revenue
				 FROM {$orders} o
				 LEFT JOIN {$attr} a ON a.order_id = o.wc_order_id
				 WHERE o.deleted_at IS NULL
				   AND o.status IN " . self::COUNTED_STATUSES . '
				   AND DATE(' . self::REVENUE_DATE_O . ') BETWEEN %s AND %s
				 GROUP BY a.channel
				 ORDER BY revenue DESC',
				$from,
				$to
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		return array_map(
			static fn( array $r ): array => [
				'channel' => (string) $r['channel'],
				'orders'  => (int) $r['orders'],
				'revenue' => max( 0.0, (float) $r['revenue'] ),
				'aov'     => (int) $r['orders'] > 0 ? max( 0.0, (float) $r['revenue'] ) / (int) $r['orders'] : 0.0,
			],
			is_array( $rows ) ? $rows : []
		);
	}

	/**
	 * Revenue by UTM source/medium/campaign (case-insensitive grouping).
	 *
	 * @param string $from  Start date.
	 * @param string $to    End date.
	 * @param int    $limit Max rows.
	 * @return array<int, array<string, scalar>>
	 */
	public static function by_utm( string $from, string $to, int $limit = 25 ): array {
		global $wpdb;
		$orders = $wpdb->prefix . 'statnive_orders';
		$attr   = $wpdb->prefix . 'statnive_order_attribution';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					COALESCE(a.utm_source_lower,'')   AS source,
					COALESCE(a.utm_medium_lower,'')   AS medium,
					COALESCE(a.utm_campaign_lower,'') AS campaign,
					COUNT(*) AS orders,
					COALESCE(SUM(o.net_total - o.refund_total),0) AS revenue
				 FROM {$orders} o
				 LEFT JOIN {$attr} a ON a.order_id = o.wc_order_id
				 WHERE o.deleted_at IS NULL
				   AND o.status IN " . self::COUNTED_STATUSES . '
				   AND DATE(' . self::REVENUE_DATE_O . ') BETWEEN %s AND %s
				   AND (a.utm_source_lower IS NOT NULL OR a.utm_medium_lower IS NOT NULL OR a.utm_campaign_lower IS NOT NULL)
				 GROUP BY a.utm_source_lower, a.utm_medium_lower, a.utm_campaign_lower
				 ORDER BY revenue DESC
				 LIMIT %d',
				$from,
				$to,
				$limit
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		return array_map(
			static fn( array $r ): array => [
				'source'   => (string) $r['source'],
				'medium'   => (string) $r['medium'],
				'campaign' => (string) $r['campaign'],
				'orders'   => (int) $r['orders'],
				'revenue'  => max( 0.0, (float) $r['revenue'] ),
			],
			is_array( $rows ) ? $rows : []
		);
	}

	/**
	 * Revenue by first-touch landing page.
	 *
	 * @param string $from  Start date.
	 * @param string $to    End date.
	 * @param int    $limit Max rows.
	 * @return array<int, array<string, scalar>>
	 */
	public static function by_landing( string $from, string $to, int $limit = 25 ): array {
		global $wpdb;
		$orders = $wpdb->prefix . 'statnive_orders';
		$attr   = $wpdb->prefix . 'statnive_order_attribution';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					COALESCE(a.first_landing_path,'') AS landing_page,
					COUNT(*) AS orders,
					COALESCE(SUM(o.net_total - o.refund_total),0) AS revenue
				 FROM {$orders} o
				 LEFT JOIN {$attr} a ON a.order_id = o.wc_order_id
				 WHERE o.deleted_at IS NULL
				   AND o.status IN " . self::COUNTED_STATUSES . '
				   AND DATE(' . self::REVENUE_DATE_O . ') BETWEEN %s AND %s
				   AND a.first_landing_path IS NOT NULL
				 GROUP BY a.first_landing_path
				 ORDER BY revenue DESC
				 LIMIT %d',
				$from,
				$to,
				$limit
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		return array_map(
			static fn( array $r ): array => [
				'landing_page' => (string) $r['landing_page'],
				'orders'       => (int) $r['orders'],
				'revenue'      => max( 0.0, (float) $r['revenue'] ),
			],
			is_array( $rows ) ? $rows : []
		);
	}

	/**
	 * Top products by revenue (grouped by parent product, refunds applied).
	 *
	 * @param string $from  Start date.
	 * @param string $to    End date.
	 * @param int    $limit Max rows.
	 * @return array<int, array<string, scalar>>
	 */
	public static function top_products( string $from, string $to, int $limit = 10 ): array {
		global $wpdb;
		$orders = $wpdb->prefix . 'statnive_orders';
		$items  = $wpdb->prefix . 'statnive_order_items';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					i.parent_product_id AS product_id,
					MAX(i.product_name) AS product_name,
					SUM(i.quantity) - SUM(i.refund_quantity) AS units,
					SUM(i.total) - SUM(i.refund_amount) AS revenue
				 FROM {$items} i
				 JOIN {$orders} o ON o.wc_order_id = i.order_id
				 WHERE o.deleted_at IS NULL
				   AND o.status IN " . self::COUNTED_STATUSES . '
				   AND DATE(' . self::REVENUE_DATE_O . ') BETWEEN %s AND %s
				   AND i.parent_product_id > 0
				 GROUP BY i.parent_product_id
				 ORDER BY revenue DESC
				 LIMIT %d',
				$from,
				$to,
				$limit
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		return array_map(
			static fn( array $r ): array => [
				'product_id'   => (int) $r['product_id'],
				'product_name' => (string) $r['product_name'],
				'units'        => max( 0, (int) $r['units'] ),
				'revenue'      => max( 0.0, (float) $r['revenue'] ),
			],
			is_array( $rows ) ? $rows : []
		);
	}

	/**
	 * 4-step funnel: product view → ATC → checkout start → purchase.
	 *
	 * Funnel counts distinct sessions per step from statnive_events.
	 * Purchase is derived from statnive_orders.
	 *
	 * @param string $from Start date.
	 * @param string $to   End date.
	 * @return array{steps: array<int, array{step: string, sessions: int}>, overall_conversion: float}
	 */
	public static function funnel( string $from, string $to ): array {
		global $wpdb;
		$events = $wpdb->prefix . 'statnive_events';
		$orders = $wpdb->prefix . 'statnive_orders';

		$names = [ 'wc_product_view', 'wc_add_to_cart', 'wc_checkout_start' ];
		$steps = [];
		foreach ( $names as $name ) {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$count = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(DISTINCT COALESCE(session_id, ID))
					 FROM {$events}
					 WHERE event_name = %s
					   AND DATE(created_at) BETWEEN %s AND %s",
					$name,
					$from,
					$to
				)
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$steps[] = [
				'step'     => $name,
				'sessions' => $count,
			];
		}

		// Purchase derived from orders table.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$purchases = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$orders}
				 WHERE deleted_at IS NULL
				   AND status IN " . self::COUNTED_STATUSES . '
				   AND DATE(' . self::REVENUE_DATE . ') BETWEEN %s AND %s',
				$from,
				$to
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		$steps[] = [
			'step'     => 'wc_purchase',
			'sessions' => $purchases,
		];

		// Overall conversion: orders / widest funnel-mouth count we have.
		// In a healthy funnel step 0 (product views) is the widest, so the
		// result is the canonical conv rate. When the tracker hasn't captured
		// product views yet but orders are present from the backfill, step 0
		// is 0 — fall back to whatever non-zero step IS the widest so the %
		// isn't a misleading "0.00". When every step is 0, return null so the
		// UI renders "—".
		$last   = $steps[ count( $steps ) - 1 ]['sessions'];
		$widest = max( array_column( $steps, 'sessions' ) );
		$conv   = $widest > 0 ? ( $last / $widest ) : null;

		return [
			'steps'              => $steps,
			'overall_conversion' => $conv,
		];
	}

	/**
	 * Coupon performance (case-insensitive grouping).
	 *
	 * @param string $from  Start date.
	 * @param string $to    End date.
	 * @param int    $limit Max rows.
	 * @return array<int, array<string, scalar>>
	 */
	public static function coupons( string $from, string $to, int $limit = 25 ): array {
		global $wpdb;
		$orders  = $wpdb->prefix . 'statnive_orders';
		$coupons = $wpdb->prefix . 'statnive_order_coupons';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					c.code_lower       AS code,
					MAX(c.code)        AS code_display,
					COUNT(DISTINCT c.order_id) AS redemptions,
					SUM(c.discount_amount) AS discount_amount,
					SUM(o.gross_total)     AS revenue_with_coupon,
					SUM(o.net_total - o.refund_total) AS net_after_discount
				 FROM {$coupons} c
				 JOIN {$orders} o ON o.wc_order_id = c.order_id
				 WHERE o.deleted_at IS NULL
				   AND o.status IN " . self::COUNTED_STATUSES . '
				   AND DATE(' . self::REVENUE_DATE_O . ') BETWEEN %s AND %s
				 GROUP BY c.code_lower
				 ORDER BY redemptions DESC
				 LIMIT %d',
				$from,
				$to,
				$limit
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		return array_map(
			static fn( array $r ): array => [
				'code'                => (string) $r['code_display'],
				'redemptions'         => (int) $r['redemptions'],
				'discount_amount'     => (float) $r['discount_amount'],
				'revenue_with_coupon' => (float) $r['revenue_with_coupon'],
				'net_after_discount'  => max( 0.0, (float) $r['net_after_discount'] ),
			],
			is_array( $rows ) ? $rows : []
		);
	}

	/**
	 * Refund trend + top refunded products.
	 *
	 * @param string $from Start date.
	 * @param string $to   End date.
	 * @return array{trend: array<int, array{date: string, rate: float}>, top: array<int, array<string, scalar>>}
	 */
	public static function refunds( string $from, string $to ): array {
		global $wpdb;
		$orders  = $wpdb->prefix . 'statnive_orders';
		$refunds = $wpdb->prefix . 'statnive_order_refunds';
		$items   = $wpdb->prefix . 'statnive_order_items';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$trend_rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT DATE(r.date_gmt) AS day,
				   SUM(r.amount) / NULLIF(
					 (SELECT SUM(o2.gross_total) FROM ' . $orders . ' o2
					  WHERE o2.deleted_at IS NULL
					    AND o2.status IN ' . self::COUNTED_STATUSES . '
					    AND DATE(COALESCE(o2.date_paid_gmt, o2.date_created_gmt)) = DATE(r.date_gmt)),
					 0
				   ) AS rate
				 FROM ' . $refunds . ' r
				 WHERE DATE(r.date_gmt) BETWEEN %s AND %s
				 GROUP BY DATE(r.date_gmt)
				 ORDER BY day ASC',
				$from,
				$to
			),
			ARRAY_A
		);

		$top_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					i.parent_product_id AS product_id,
					MAX(i.product_name) AS product_name,
					SUM(i.refund_quantity) AS units,
					SUM(i.refund_amount)   AS amount
				 FROM {$items} i
				 JOIN {$orders} o ON o.wc_order_id = i.order_id
				 WHERE o.deleted_at IS NULL
				   AND DATE(' . self::REVENUE_DATE_O . ') BETWEEN %s AND %s
				   AND i.refund_amount > 0
				 GROUP BY i.parent_product_id
				 ORDER BY amount DESC
				 LIMIT 10",
				$from,
				$to
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		return [
			'trend' => array_map(
				static fn( array $r ): array => [
					'date' => (string) $r['day'],
					'rate' => (float) ( $r['rate'] ?? 0 ),
				],
				is_array( $trend_rows ) ? $trend_rows : []
			),
			'top'   => array_map(
				static fn( array $r ): array => [
					'product_id'   => (int) $r['product_id'],
					'product_name' => (string) $r['product_name'],
					'units'        => max( 0, (int) $r['units'] ),
					'amount'       => max( 0.0, (float) $r['amount'] ),
				],
				is_array( $top_rows ) ? $top_rows : []
			),
		];
	}
}
