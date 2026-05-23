/**
 * TanStack Query bindings for the WC backfill UX.
 *
 * The backfill auto-starts server-side on the first admin pageview where
 * a gap is detected — this client never needs to "start" it. The hook
 * exists to (a) surface progress to the UI and (b) provide a manual
 * re-trigger for failure recovery.
 *
 * Polling cadence:
 *   - status === 'pending' | 'running' → /wc-status every 5s
 *   - any other status                  → no polling
 *   - revenue queries refetch every 10s while running so the Report
 *     shows partial data filling in (see revenue.tsx wiring)
 */
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { apiGet, apiPost } from '@/lib/api-client';
import type {
	BackfillState,
	BackfillStatus,
	BackfillTriggerResponse,
	RevenueEnvelope,
	WcStatus,
} from '@/types/revenue';

export const WC_STATUS_KEY = ['revenue', 'wc-status'] as const;

const IN_FLIGHT: BackfillStatus[] = ['pending', 'running'];

export function backfillIsInFlight(state: BackfillState | undefined): boolean {
	return !!state && IN_FLIGHT.includes(state.status);
}

/**
 * Read `/wc-status` with status-aware polling.
 *
 * Returns `undefined` while the first request is still loading; consumers
 * should handle that.
 */
export function useBackfillStatus(): {
	wcStatus: WcStatus | undefined;
	state: BackfillState | undefined;
	isLoading: boolean;
	isError: boolean;
} {
	const query = useQuery<RevenueEnvelope<WcStatus>>({
		queryKey: WC_STATUS_KEY,
		queryFn: () => apiGet<RevenueEnvelope<WcStatus>>('revenue/wc-status'),
		staleTime: 5 * 60 * 1000,
		refetchInterval: (q) => {
			const data = q.state.data?.data;
			const status = data?.backfill?.state?.status;
			return status === 'pending' || status === 'running' ? 5000 : false;
		},
	});

	return {
		wcStatus: query.data?.data,
		state: query.data?.data?.backfill?.state,
		isLoading: query.isLoading,
		isError: query.isError,
	};
}

/**
 * Whether the backfill is currently pending or running.
 * Convenience for components that want to flip refetch intervals.
 */
export function useBackfillInFlight(): boolean {
	const { state } = useBackfillStatus();
	return backfillIsInFlight(state);
}

/**
 * Manual re-trigger mutation. POSTs to /revenue/backfill.
 *
 * On 200/202 success — invalidates the wc-status query so the new state
 * appears immediately.
 *
 * On 409 — swallowed silently (means a job is already running, which is
 * fine). Refetches the status either way.
 */
export function useBackfillTrigger() {
	const queryClient = useQueryClient();
	return useMutation<BackfillTriggerResponse | null, Error, void>({
		mutationFn: async () => {
			try {
				const res = await apiPost<RevenueEnvelope<BackfillTriggerResponse>>('revenue/backfill', {});
				return res.data;
			} catch (err) {
				// apiPost throws for non-2xx. A 409 means the job is already
				// running; we want to refresh the status and treat it as a
				// no-op success from the user's perspective.
				if (err instanceof Error && /\b409\b/.test(err.message)) {
					return null;
				}
				throw err;
			}
		},
		onSettled: () => {
			void queryClient.invalidateQueries({ queryKey: WC_STATUS_KEY });
		},
	});
}
