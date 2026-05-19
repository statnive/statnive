/**
 * TanStack Query hooks for /statnive/v1/revenue/* endpoints.
 *
 * One named export per endpoint. Each hook returns the full envelope
 * { data, meta } so the page can read both the payload and the meta
 * (currency, request_id, period) without re-derivation.
 */
import { useQuery } from '@tanstack/react-query';
import { apiGet } from '@/lib/api-client';
import type {
	RevenueChannelRow,
	RevenueCouponRow,
	RevenueEnvelope,
	RevenueFunnelResponse,
	RevenueProductRow,
	RevenueRefundResponse,
	RevenueSummary,
	RevenueTimeseriesPoint,
	WcStatus,
} from '@/types/revenue';

const STALE_MS = 60_000;

const dateParams = (from: string, to: string) => ({ from, to });

export function useWcStatus() {
	return useQuery<RevenueEnvelope<WcStatus>>({
		queryKey: ['revenue', 'wc-status'],
		queryFn: () => apiGet<RevenueEnvelope<WcStatus>>('revenue/wc-status'),
		staleTime: 5 * 60 * 1000,
	});
}

export function useRevenueSummary(from: string, to: string) {
	return useQuery<RevenueEnvelope<RevenueSummary>>({
		queryKey: ['revenue', 'summary', from, to],
		queryFn: () => apiGet<RevenueEnvelope<RevenueSummary>>('revenue/summary', dateParams(from, to)),
		staleTime: STALE_MS,
	});
}

export function useRevenueTimeseries(from: string, to: string) {
	return useQuery<RevenueEnvelope<RevenueTimeseriesPoint[]>>({
		queryKey: ['revenue', 'timeseries', from, to],
		queryFn: () => apiGet<RevenueEnvelope<RevenueTimeseriesPoint[]>>('revenue/timeseries', dateParams(from, to)),
		staleTime: STALE_MS,
	});
}

export function useRevenueByChannel(from: string, to: string) {
	return useQuery<RevenueEnvelope<RevenueChannelRow[]>>({
		queryKey: ['revenue', 'by-channel', from, to],
		queryFn: () => apiGet<RevenueEnvelope<RevenueChannelRow[]>>('revenue/by-channel', dateParams(from, to)),
		staleTime: STALE_MS,
	});
}

export function useTopProducts(from: string, to: string, limit = 10) {
	return useQuery<RevenueEnvelope<RevenueProductRow[]>>({
		queryKey: ['revenue', 'products', from, to, limit],
		queryFn: () =>
			apiGet<RevenueEnvelope<RevenueProductRow[]>>('revenue/products', {
				...dateParams(from, to),
				limit: String(limit),
			}),
		staleTime: STALE_MS,
	});
}

export function useFunnel(from: string, to: string) {
	return useQuery<RevenueEnvelope<RevenueFunnelResponse>>({
		queryKey: ['revenue', 'funnel', from, to],
		queryFn: () => apiGet<RevenueEnvelope<RevenueFunnelResponse>>('revenue/funnel', dateParams(from, to)),
		staleTime: STALE_MS,
	});
}

export function useCoupons(from: string, to: string, limit = 25) {
	return useQuery<RevenueEnvelope<RevenueCouponRow[]>>({
		queryKey: ['revenue', 'coupons', from, to, limit],
		queryFn: () =>
			apiGet<RevenueEnvelope<RevenueCouponRow[]>>('revenue/coupons', {
				...dateParams(from, to),
				limit: String(limit),
			}),
		staleTime: STALE_MS,
	});
}

export function useRefunds(from: string, to: string) {
	return useQuery<RevenueEnvelope<RevenueRefundResponse>>({
		queryKey: ['revenue', 'refunds', from, to],
		queryFn: () => apiGet<RevenueEnvelope<RevenueRefundResponse>>('revenue/refunds', dateParams(from, to)),
		staleTime: STALE_MS,
	});
}
