// DST edge-case test for useDateRange — 7d range produces exactly 7 days

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import { createElement } from 'react';
import {
	createRouter,
	createRootRoute,
	createRoute,
	RouterProvider,
	createMemoryHistory,
} from '@tanstack/react-router';
import type { DateRange } from '@/types/api';
import { useDateRange } from '@/hooks/use-date-range';

// ---------------------------------------------------------------------------
// Test router helper (follows pattern from use-date-range.test.ts)
// ---------------------------------------------------------------------------

const VALID_RANGES: DateRange[] = ['today', '7d', '30d', 'this-month', 'last-month', 'custom'];

function renderWithRouter(initialSearch: Record<string, string> = {}) {
	function TestComponent() {
		const result = useDateRange();

		return createElement('div', { 'data-testid': 'hook-output' },
			createElement('span', { 'data-testid': 'from' }, result.params.from),
			createElement('span', { 'data-testid': 'to' }, result.params.to),
		);
	}

	const rootRoute = createRootRoute({
		validateSearch: (search: Record<string, unknown>): { range: DateRange } => ({
			range:
				typeof search.range === 'string' && VALID_RANGES.includes(search.range as DateRange)
					? (search.range as DateRange)
					: '7d',
		}),
	});

	const indexRoute = createRoute({
		getParentRoute: () => rootRoute,
		path: '/',
		component: TestComponent,
	});

	const routeTree = rootRoute.addChildren([indexRoute]);

	const searchString = new URLSearchParams(initialSearch).toString();
	const initialEntry = searchString ? `/?${searchString}` : '/';
	const memoryHistory = createMemoryHistory({ initialEntries: [initialEntry] });

	const router = createRouter({ routeTree, history: memoryHistory });

	return { router };
}

async function renderTest(initialSearch: Record<string, string> = {}) {
	const { router } = renderWithRouter(initialSearch);
	await router.load();
	const utils = render(createElement(RouterProvider, { router } as Parameters<typeof RouterProvider>[0]));
	return utils;
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('useDateRange DST edge case', () => {
	beforeEach(() => {
		vi.restoreAllMocks();
	});

	it('7d range produces exactly 7 days between from and to (inclusive)', async () => {
		await renderTest({ range: '7d' });

		const fromStr = screen.getByTestId('from').textContent!;
		const toStr = screen.getByTestId('to').textContent!;

		const fromDate = new Date(fromStr + 'T00:00:00');
		const toDate = new Date(toStr + 'T00:00:00');

		// Calculate difference in days (inclusive: to - from + 1 = 7)
		const diffMs = toDate.getTime() - fromDate.getTime();
		const diffDays = Math.round(diffMs / (1000 * 60 * 60 * 24)) + 1;

		expect(diffDays).toBe(7);
	});
});
