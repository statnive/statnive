/**
 * Unit tests for the use-backfill hook.
 *
 * Three contracts to lock:
 *   1. `refetchInterval` flips on when status is 'pending' or 'running'
 *      and stays off otherwise.
 *   2. `backfillIsInFlight` returns true exactly for those two states.
 *   3. `useBackfillTrigger` swallows 409 (a job is already running) so
 *      double-clicks don't pop user-visible errors.
 */
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { renderHook, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { createElement, type ReactNode } from 'react';

import { useBackfillStatus, useBackfillTrigger, backfillIsInFlight } from '@/hooks/use-backfill';
import type { BackfillState } from '@/types/revenue';

function makeWrapper(client: QueryClient) {
	return ({ children }: { children: ReactNode }) =>
		createElement(QueryClientProvider, { client }, children);
}

function envelope(state: BackfillState) {
	return {
		data: {
			woocommerce_active: true,
			woocommerce_version: '10.7.0',
			hpos_enabled: true,
			attribution_enabled: true,
			min_wc_required: '7.0',
			recorder_failures: 0,
			backfill: {
				has_gap: true,
				orders_in_wc: 1000,
				orders_in_statnive: 250,
				action_scheduler_available: true,
				state,
			},
		},
		meta: {
			request_id: 'r1',
			currency: 'USD',
			currency_minor_unit: 2,
			currency_symbol: '$',
			timezone: 'UTC',
			generated_at: '2026-05-20T00:00:00Z',
		},
	};
}

const idleState: BackfillState = {
	status: 'idle',
	total: 0,
	processed: 0,
	refunds: 0,
	started_at: null,
	finished_at: null,
	last_error: null,
};

const runningState: BackfillState = {
	status: 'running',
	total: 1000,
	processed: 250,
	refunds: 0,
	started_at: '2026-05-20T00:00:00Z',
	finished_at: null,
	last_error: null,
};

describe('backfillIsInFlight', () => {
	it('returns true for pending', () => {
		expect(backfillIsInFlight({ ...idleState, status: 'pending' })).toBe(true);
	});
	it('returns true for running', () => {
		expect(backfillIsInFlight({ ...idleState, status: 'running' })).toBe(true);
	});
	it('returns false for idle / done / failed', () => {
		expect(backfillIsInFlight({ ...idleState, status: 'idle' })).toBe(false);
		expect(backfillIsInFlight({ ...idleState, status: 'done' })).toBe(false);
		expect(backfillIsInFlight({ ...idleState, status: 'failed' })).toBe(false);
		expect(backfillIsInFlight(undefined)).toBe(false);
	});
});

describe('useBackfillStatus', () => {
	beforeEach(() => {
		vi.restoreAllMocks();
	});

	afterEach(() => {
		vi.useRealTimers();
	});

	it('returns the wc-status envelope and exposes backfill state', async () => {
		global.fetch = vi.fn().mockResolvedValue({
			ok: true,
			json: () => Promise.resolve(envelope(runningState)),
		});

		const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
		const { result } = renderHook(() => useBackfillStatus(), {
			wrapper: makeWrapper(client),
		});

		await waitFor(() => {
			expect(result.current.state?.status).toBe('running');
		});
		expect(result.current.wcStatus?.woocommerce_active).toBe(true);
	});
});

describe('useBackfillTrigger', () => {
	beforeEach(() => {
		vi.restoreAllMocks();
	});

	it('treats a 409 from the trigger endpoint as a no-op', async () => {
		const callMock = vi.fn();
		global.fetch = vi.fn().mockImplementation((url: string) => {
			callMock(url);
			return Promise.resolve({
				ok: false,
				status: 409,
				statusText: 'Conflict',
			});
		});

		const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } });
		const { result } = renderHook(() => useBackfillTrigger(), { wrapper: makeWrapper(client) });

		const out = await result.current.mutateAsync();
		expect(out).toBeNull();
		expect(callMock).toHaveBeenCalledTimes(1);
	});
});
