// Generated from BDD scenarios — Feature: Dashboard Overview (05-dashboard-overview.feature)

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/** Generate date strings relative to today for test stability. */
function daysAgo(n: number): string {
	const d = new Date();
	d.setDate(d.getDate() - n);
	return d.toISOString().slice(0, 10); // "YYYY-MM-DD"
}

const today = daysAgo(0);
const weekAgo = daysAgo(6);
const twoWeeksAgo = daysAgo(13);
const dayBeforeWeekAgo = daysAgo(7);

// ---------------------------------------------------------------------------
// Mocks — every hook used by OverviewPage is replaced with controllable stubs.
// ---------------------------------------------------------------------------

const mockSetDateRange = vi.fn();

vi.mock('@/hooks/use-date-range', () => ({
	useDateRange: vi.fn(() => ({
		range: '7d',
		params: { from: weekAgo, to: today },
		previousParams: { from: twoWeeksAgo, to: dayBeforeWeekAgo },
		setDateRange: mockSetDateRange,
	})),
}));

const mockUseSummary = vi.fn();
vi.mock('@/hooks/use-summary', () => ({
	useSummary: (...args: unknown[]) => mockUseSummary(...args),
}));

const mockUseSources = vi.fn();
vi.mock('@/hooks/use-sources', () => ({
	useSources: (...args: unknown[]) => mockUseSources(...args),
}));

const mockUsePages = vi.fn();
vi.mock('@/hooks/use-pages', () => ({
	usePages: (...args: unknown[]) => mockUsePages(...args),
}));

// Recharts is notoriously hard to test — stub the chart to expose data attributes.
vi.mock('@/components/charts/time-series-chart', () => ({
	TimeSeriesChart: ({ data }: { data: unknown[] }) => (
		<div data-testid="time-series-chart" data-points={data.length} />
	),
}));

import { OverviewPage } from '@/pages/overview';

// ---------------------------------------------------------------------------
// Mock data helpers
// ---------------------------------------------------------------------------

function summaryHook(totals: Record<string, number> | null, isLoading = false) {
	return { data: totals ? { totals, daily: [] } : null, isLoading };
}

function sourcesHook(data: unknown[] | null, isLoading = false) {
	return { data, isLoading };
}

function pagesHook(data: unknown[] | null, isLoading = false) {
	return { data, isLoading };
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('OverviewPage', () => {
	beforeEach(() => {
		vi.restoreAllMocks();
	});

	// REQ-1.1 — KPI cards render with formatted metrics for the current period
	it('renders 4 KPI cards with formatted visitor, session, pageview, and avg duration values', () => {
		mockUseSummary
			.mockReturnValueOnce(
				summaryHook({ visitors: 2450, sessions: 3120, views: 8740, total_duration: 9360, bounces: 0, bounce_rate: 0 }),
			)
			.mockReturnValueOnce(
				summaryHook({ visitors: 2100, sessions: 2800, views: 7500, total_duration: 8400, bounces: 0, bounce_rate: 0 }),
			);
		mockUseSources.mockReturnValue(sourcesHook([]));
		mockUsePages.mockReturnValue(pagesHook([]));

		render(<OverviewPage />);

		// KPI labels may also appear in table column headers, so use getAllByText
		expect(screen.getAllByText('Visitors').length).toBeGreaterThanOrEqual(1);
		expect(screen.getAllByText('Sessions').length).toBeGreaterThanOrEqual(1);
		expect(screen.getByText('Pageviews')).toBeInTheDocument();
		expect(screen.getByText('Avg Duration')).toBeInTheDocument();

		// Comma-separated formatting
		expect(screen.getByText('2,450')).toBeInTheDocument();
		expect(screen.getByText('3,120')).toBeInTheDocument();
		expect(screen.getByText('8,740')).toBeInTheDocument();
	});

	// REQ-1.2 — Time series chart renders visitor and session lines
	it('renders the time series chart component with daily data', () => {
		const daily = [
			{ date: daysAgo(6), visitors: 350, sessions: 420, views: 1100, total_duration: 1200, bounces: 50 },
			{ date: daysAgo(5), visitors: 380, sessions: 460, views: 1250, total_duration: 1300, bounces: 45 },
		];
		mockUseSummary
			.mockReturnValueOnce({
				data: { totals: { visitors: 730, sessions: 880, views: 2350, total_duration: 2500, bounces: 95, bounce_rate: 13.0 }, daily },
				isLoading: false,
			})
			.mockReturnValueOnce(summaryHook(null));
		mockUseSources.mockReturnValue(sourcesHook([]));
		mockUsePages.mockReturnValue(pagesHook([]));

		render(<OverviewPage />);

		const chart = screen.getByTestId('time-series-chart');
		expect(chart).toBeInTheDocument();
		expect(chart.dataset.points).toBe('2');
	});

	// REQ-1.3 — Top pages table displays ranked pages with view counts
	it('renders top pages table with URI, visitors, and views', () => {
		mockUseSummary
			.mockReturnValueOnce(summaryHook({ visitors: 1000, sessions: 1200, views: 3000, total_duration: 6000, bounces: 0, bounce_rate: 0 }))
			.mockReturnValueOnce(summaryHook(null));
		mockUseSources.mockReturnValue(sourcesHook([]));
		mockUsePages.mockReturnValue(
			pagesHook([
				{ uri: '/blog/seo-guide', title: 'SEO Guide', visitors: 320, views: 540, total_duration: 19200, bounces: 50 },
				{ uri: '/pricing', title: 'Pricing', visitors: 200, views: 310, total_duration: 4000, bounces: 30 },
			]),
		);

		render(<OverviewPage />);

		expect(screen.getByText('Top Pages')).toBeInTheDocument();
		expect(screen.getByText('SEO Guide')).toBeInTheDocument();
		expect(screen.getByText('320')).toBeInTheDocument();
		expect(screen.getByText('540')).toBeInTheDocument();
	});

	// REQ-1.4 — Top referrers table shows sources grouped by channel
	it('renders top sources table with channel labels and dual-bar visualization', () => {
		mockUseSummary
			.mockReturnValueOnce(summaryHook({ visitors: 1000, sessions: 1200, views: 3000, total_duration: 6000, bounces: 0, bounce_rate: 0 }))
			.mockReturnValueOnce(summaryHook(null));
		mockUseSources.mockReturnValue(
			sourcesHook([
				{ channel: 'Organic Search', name: 'google.com', domain: 'google.com', visitors: 890, sessions: 1020, views: 2300 },
			]),
		);
		mockUsePages.mockReturnValue(pagesHook([]));

		render(<OverviewPage />);

		expect(screen.getByText('Top Sources')).toBeInTheDocument();
		expect(screen.getByText('google.com')).toBeInTheDocument();
		expect(screen.getByText('Organic Search')).toBeInTheDocument();
	});

	// REQ-1.6 — Comparison mode shows percentage delta with directional arrows (positive)
	it('displays a positive percentage change badge on Visitors KPI card', () => {
		// current: 2450, previous: 2100 => +16.67%
		mockUseSummary
			.mockReturnValueOnce(
				summaryHook({ visitors: 2450, sessions: 3000, views: 8000, total_duration: 9000, bounces: 0, bounce_rate: 0 }),
			)
			.mockReturnValueOnce(
				summaryHook({ visitors: 2100, sessions: 2700, views: 7200, total_duration: 8100, bounces: 0, bounce_rate: 0 }),
			);
		mockUseSources.mockReturnValue(sourcesHook([]));
		mockUsePages.mockReturnValue(pagesHook([]));

		render(<OverviewPage />);

		// formatPercentChange renders "↑ 16.7%" — KPI badge has green background for positive
		const badge = screen.getByText(/16\.7%/);
		expect(badge).toBeInTheDocument();
		expect(badge.className).toContain('bg-green');
	});

	// REQ-1.7 — Comparison mode shows negative delta for declining metrics
	it('displays a negative percentage change badge when visitors decline', () => {
		// current: 1800, previous: 2400 => -25.0%
		mockUseSummary
			.mockReturnValueOnce(
				summaryHook({ visitors: 1800, sessions: 2200, views: 5000, total_duration: 6000, bounces: 0, bounce_rate: 0 }),
			)
			.mockReturnValueOnce(
				summaryHook({ visitors: 2400, sessions: 3000, views: 7000, total_duration: 8000, bounces: 0, bounce_rate: 0 }),
			);
		mockUseSources.mockReturnValue(sourcesHook([]));
		mockUsePages.mockReturnValue(pagesHook([]));

		render(<OverviewPage />);

		const badge = screen.getByText(/25\.0%/);
		expect(badge).toBeInTheDocument();
		// Negative change badge has red background
		expect(badge.className).toContain('bg-red');
	});

	// REQ-1.8 — Empty state displays appropriate message for new installs
	it('shows empty state messages when no data exists', () => {
		mockUseSummary
			.mockReturnValueOnce(
				summaryHook({ visitors: 0, sessions: 0, views: 0, total_duration: 0, bounces: 0, bounce_rate: 0 }),
			)
			.mockReturnValueOnce(summaryHook(null));
		mockUseSources.mockReturnValue(sourcesHook([]));
		mockUsePages.mockReturnValue(pagesHook([]));

		render(<OverviewPage />);

		// Multiple KPI cards show "0", so check at least one exists
		expect(screen.getAllByText('0').length).toBeGreaterThanOrEqual(1);
		expect(screen.getByText(/No traffic sources recorded yet/)).toBeInTheDocument();
		expect(screen.getByText(/No page data recorded yet/)).toBeInTheDocument();
	});

	// REQ-1.9 — Skeleton loading shimmer displays while data is fetching
	it('shows loading placeholders while data is loading', () => {
		mockUseSummary.mockReturnValue(summaryHook(null, true));
		mockUseSources.mockReturnValue(sourcesHook(null, true));
		mockUsePages.mockReturnValue(pagesHook(null, true));

		const { container } = render(<OverviewPage />);

		const skeletons = container.querySelectorAll('.animate-pulse');
		expect(skeletons.length).toBeGreaterThan(0);
	});
});
