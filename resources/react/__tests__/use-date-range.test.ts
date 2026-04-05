import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, act } from '@testing-library/react';
import { createElement, useRef, useEffect } from 'react';
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
// Test router helper — renders a route component that exposes hook results
// ---------------------------------------------------------------------------

const VALID_RANGES: DateRange[] = ['today', '7d', '30d', 'this-month', 'last-month', 'custom'];

interface HookResult {
	range: DateRange;
	params: { from: string; to: string };
	previousParams: { from: string; to: string };
	setDateRange: (range: DateRange) => void;
}

/**
 * Creates a test router with a route component that calls useDateRange()
 * and exposes results via data-testid attributes + a ref callback.
 */
function renderWithRouter(
	initialSearch: Record<string, string> = {},
	onResult?: (result: HookResult) => void,
) {
	let latestResult: HookResult;

	function TestComponent() {
		const result = useDateRange();
		latestResult = result;

		// Call onResult on each render if provided.
		const onResultRef = useRef(onResult);
		onResultRef.current = onResult;
		useEffect(() => {
			onResultRef.current?.(result);
		});

		return createElement('div', { 'data-testid': 'hook-output' },
			createElement('span', { 'data-testid': 'range' }, result.range),
			createElement('span', { 'data-testid': 'from' }, result.params.from),
			createElement('span', { 'data-testid': 'to' }, result.params.to),
			createElement('span', { 'data-testid': 'prev-from' }, result.previousParams.from),
			createElement('span', { 'data-testid': 'prev-to' }, result.previousParams.to),
			createElement('button', {
				'data-testid': 'set-range',
				onClick: () => result.setDateRange('today'),
			}, 'Set Today'),
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

	return { router, getResult: () => latestResult! };
}

async function renderTest(initialSearch: Record<string, string> = {}) {
	const { router, getResult } = renderWithRouter(initialSearch);
	await router.load();
	const utils = render(createElement(RouterProvider, { router } as Parameters<typeof RouterProvider>[0]));
	return { ...utils, router, getResult };
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('useDateRange', () => {
	beforeEach(() => {
		vi.restoreAllMocks();
	});

	it('defaults to 7d range when URL has no range param', async () => {
		await renderTest();
		expect(screen.getByTestId('range').textContent).toBe('7d');
	});

	it('reads range from URL search params', async () => {
		await renderTest({ range: '30d' });
		expect(screen.getByTestId('range').textContent).toBe('30d');
	});

	it('falls back to 7d for invalid range in URL', async () => {
		await renderTest({ range: 'bogus' });
		expect(screen.getByTestId('range').textContent).toBe('7d');
	});

	it('provides from and to date strings in ISO format', async () => {
		await renderTest();
		expect(screen.getByTestId('from').textContent).toMatch(/^\d{4}-\d{2}-\d{2}$/);
		expect(screen.getByTestId('to').textContent).toMatch(/^\d{4}-\d{2}-\d{2}$/);
	});

	it('provides previous period that ends before current period starts', async () => {
		await renderTest();
		const prevTo = screen.getByTestId('prev-to').textContent!;
		const from = screen.getByTestId('from').textContent!;
		expect(prevTo).toMatch(/^\d{4}-\d{2}-\d{2}$/);
		expect(prevTo < from).toBe(true);
	});

	it('today range has same from and to', async () => {
		await renderTest({ range: 'today' });
		const from = screen.getByTestId('from').textContent;
		const to = screen.getByTestId('to').textContent;
		expect(from).toBe(to);
	});

	it('setDateRange updates the router search params', async () => {
		const { router } = await renderTest();
		expect(screen.getByTestId('range').textContent).toBe('7d');

		await act(async () => {
			screen.getByTestId('set-range').click();
		});

		expect(router.state.location.search).toEqual(
			expect.objectContaining({ range: 'today' }),
		);
	});
});
