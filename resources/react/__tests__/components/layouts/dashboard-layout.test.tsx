// Tests for DateRangePicker visibility and search param preservation in DashboardLayout.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { createElement, type ReactNode } from 'react';
import {
	createRouter,
	createRootRoute,
	createRoute,
	RouterProvider,
	Outlet,
	createMemoryHistory,
} from '@tanstack/react-router';
import type { DateRange } from '@/types/api';

// Mock lucide-react icons to simple spans to avoid SVG rendering issues in tests.
vi.mock('lucide-react', () => {
	const icon = (name: string) => {
		const Component = ({ className }: { className?: string }) =>
			createElement('span', { className, 'data-testid': `icon-${name}` });
		Component.displayName = name;
		return Component;
	};
	return {
		BarChart3: icon('BarChart3'),
		FileText: icon('FileText'),
		Share2: icon('Share2'),
		Globe: icon('Globe'),
		Monitor: icon('Monitor'),
		Languages: icon('Languages'),
		Activity: icon('Activity'),
		Settings: icon('Settings'),
	};
});

// Mock keyboard shortcuts and WP commands — not under test here.
vi.mock('@/hooks/use-keyboard-shortcuts', () => ({
	useKeyboardShortcuts: vi.fn(),
}));

vi.mock('@/lib/wp-commands', () => ({
	registerWpCommands: vi.fn(),
}));

import { DashboardLayout } from '@/components/layouts/dashboard-layout';

// ---------------------------------------------------------------------------
// Test router helper
// ---------------------------------------------------------------------------

const VALID_RANGES: DateRange[] = ['today', '7d', '30d', 'this-month', 'last-month', 'custom'];

function createTestApp(initialPath: string, initialSearch: Record<string, string> = {}) {
	const rootRoute = createRootRoute({
		validateSearch: (search: Record<string, unknown>): { range: DateRange } => ({
			range:
				typeof search.range === 'string' && VALID_RANGES.includes(search.range as DateRange)
					? (search.range as DateRange)
					: '7d',
		}),
		component: () =>
			createElement(DashboardLayout, null, createElement(Outlet)),
	});

	const pages = ['/', '/pages', '/referrers', '/geography', '/devices', '/languages', '/realtime', '/settings'];

	const childRoutes = pages.map((path) =>
		createRoute({
			getParentRoute: () => rootRoute,
			path,
			component: () => createElement('div', { 'data-testid': `page-${path.replace('/', '') || 'overview'}` }),
		}),
	);

	const routeTree = rootRoute.addChildren(childRoutes);

	const searchString = new URLSearchParams(initialSearch).toString();
	const entry = searchString ? `${initialPath}?${searchString}` : initialPath;
	const memoryHistory = createMemoryHistory({ initialEntries: [entry] });

	const router = createRouter({ routeTree, history: memoryHistory });

	return { router };
}

async function renderApp(initialPath: string, initialSearch: Record<string, string> = {}) {
	const { router } = createTestApp(initialPath, initialSearch);
	await router.load();
	const utils = render(createElement(RouterProvider, { router } as Parameters<typeof RouterProvider>[0]));
	return { ...utils, router };
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('DashboardLayout — DateRangePicker', () => {
	beforeEach(() => {
		vi.restoreAllMocks();
	});

	it('shows DateRangePicker on overview route', async () => {
		await renderApp('/');
		expect(screen.getByRole('group', { name: 'Date range' })).toBeInTheDocument();
	});

	it('shows DateRangePicker on referrers route', async () => {
		await renderApp('/referrers');
		expect(screen.getByRole('group', { name: 'Date range' })).toBeInTheDocument();
	});

	it('hides DateRangePicker on /realtime', async () => {
		await renderApp('/realtime');
		expect(screen.queryByRole('group', { name: 'Date range' })).not.toBeInTheDocument();
	});

	it('hides DateRangePicker on /settings', async () => {
		await renderApp('/settings');
		expect(screen.queryByRole('group', { name: 'Date range' })).not.toBeInTheDocument();
	});

	it('highlights 7 Days by default', async () => {
		await renderApp('/');
		const sevenDayBtn = screen.getByText('7 Days');
		expect(sevenDayBtn.className).toContain('bg-primary');
	});

	it('highlights the range specified in URL search params', async () => {
		await renderApp('/', { range: '30d' });
		const thirtyDayBtn = screen.getByText('30 Days');
		expect(thirtyDayBtn.className).toContain('bg-primary');

		const sevenDayBtn = screen.getByText('7 Days');
		expect(sevenDayBtn.className).not.toContain('bg-primary');
	});

	it('preserves range search param when clicking a nav tab', async () => {
		const { router } = await renderApp('/', { range: '30d' });
		const user = userEvent.setup();

		const pagesTab = screen.getByText('Pages');
		await user.click(pagesTab);

		// After navigating, the range should still be in the search params.
		expect(router.state.location.search).toEqual(
			expect.objectContaining({ range: '30d' }),
		);
	});
});
