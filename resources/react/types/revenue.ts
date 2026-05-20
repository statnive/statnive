/**
 * Response shapes for /statnive/v1/revenue/* endpoints.
 */

export interface RevenueMeta {
	request_id: string;
	currency: string;
	currency_minor_unit: number;
	currency_symbol: string;
	timezone: string;
	generated_at: string;
	period?: { start: string; end: string };
}

export interface RevenueEnvelope<T> {
	data: T;
	meta: RevenueMeta;
}

export interface WcStatus {
	woocommerce_active: boolean;
	woocommerce_version: string;
	hpos_enabled: boolean;
	attribution_enabled: boolean;
	min_wc_required: string;
	recorder_failures: number;
}

export interface RevenueSummary {
	orders: number;
	gross_revenue: number;
	net_revenue: number;
	refund_total: number;
	aov: number;
	refund_rate: number;
	tax_total: number;
	shipping_total: number;
}

export interface RevenueTimeseriesPoint {
	date: string;
	revenue: number;
	orders: number;
}

export interface RevenueChannelRow {
	channel: string;
	orders: number;
	revenue: number;
	aov: number;
}

export interface RevenueProductRow {
	product_id: number;
	product_name: string;
	units: number;
	revenue: number;
}

export interface RevenueFunnelStep {
	step: string;
	sessions: number;
}

export interface RevenueFunnelResponse {
	steps: RevenueFunnelStep[];
	overall_conversion: number;
}

export interface RevenueCouponRow {
	code: string;
	redemptions: number;
	discount_amount: number;
	revenue_with_coupon: number;
	net_after_discount: number;
}

export interface RevenueRefundResponse {
	trend: Array<{ date: string; rate: number }>;
	top: Array<{ product_id: number; product_name: string; units: number; amount: number }>;
}
