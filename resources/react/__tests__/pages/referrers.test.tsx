// Generated from BDD scenarios — Feature: Dashboard Detail Pages — Referrers screen

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function daysAgo(n: number): string {
	const d = new Date();
	d.setDate(d.getDate() - n);
	return d.toISOString().slice(0, 10);
}

const today = daysAgo(0);
const weekAgo = daysAgo(6);
const twoWeeksAgo = daysAgo(13);
const dayBeforeWeekAgo = daysAgo(7);

// ---------------------------------------------------------------------------
// Mocks
// ---------------------------------------------------------------------------

vi.mock('@/hooks/use-date-range', () => ({
	useDateRange: vi.fn(() => ({
		range: '7d',
		params: { from: weekAgo, to: today },
		previousParams: { from: twoWeeksAgo, to: dayBeforeWeekAgo },
		setDateRange: vi.fn(),
	})),
}));

const mockUseGroupedSources = vi.fn();
vi.mock('@/hooks/use-sources', () => ({
	useSources: vi.fn(() => ({ data: [], isLoading: false })),
	useGroupedSources: (...args: unknown[]) => mockUseGroupedSources(...args),
}));

const mockUseUtm = vi.fn();
vi.mock('@/hooks/use-utm', () => ({
	useUtm: (...args: unknown[]) => mockUseUtm(...args),
}));

import { ReferrersPage } from '@/pages/referrers';

// ---------------------------------------------------------------------------
// Fixtures
// ---------------------------------------------------------------------------

const groupedChannels = [
	{
		channel: 'Organic Search',
		visitors: 1200,
		sessions: 1450,
		views: 3200,
		sources: [
			{ name: 'google.com', domain: 'google.com', visitors: 1000, sessions: 1200, views: 2800 },
			{ name: 'bing.com', domain: 'bing.com', visitors: 200, sessions: 250, views: 400 },
		],
	},
	{
		channel: 'Direct',
		visitors: 500,
		sessions: 600,
		views: 1500,
		sources: [
			{ name: '', domain: '', visitors: 500, sessions: 600, views: 1500 },
		],
	},
	{
		channel: 'Social Media',
		visitors: 340,
		sessions: 380,
		views: 900,
		sources: [
			{ name: 'twitter.com', domain: 'twitter.com', visitors: 340, sessions: 380, views: 900 },
		],
	},
	{
		channel: 'Referral',
		visitors: 150,
		sessions: 180,
		views: 400,
		sources: [
			{ name: 'partner.io', domain: 'partner.io', visitors: 150, sessions: 180, views: 400 },
		],
	},
	{
		channel: 'Email',
		visitors: 80,
		sessions: 90,
		views: 200,
		sources: [
			{ name: 'newsletter', domain: '', visitors: 80, sessions: 90, views: 200 },
		],
	},
];

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('ReferrersPage', () => {
	beforeEach(() => {
		vi.restoreAllMocks();
	});

	// REQ-1.15 — Channel summary cards with accurate server-side totals
	it('renders channel summary cards with visitor and session counts', () => {
		mockUseGroupedSources.mockReturnValue({
			data: groupedChannels,
			isLoading: false,
		});
		mockUseUtm.mockReturnValue({ data: [], isLoading: false });

		render(<ReferrersPage />);

		// All 5 channel names appear in summary cards and grouped table headers
		expect(screen.getAllByText('Organic Search').length).toBeGreaterThanOrEqual(2);
		expect(screen.getAllByText('Direct').length).toBeGreaterThanOrEqual(2);
		expect(screen.getAllByText('Social Media').length).toBeGreaterThanOrEqual(2);
		expect(screen.getAllByText('Referral').length).toBeGreaterThanOrEqual(2);
		expect(screen.getAllByText('Email').length).toBeGreaterThanOrEqual(2);

		// Organic Search card: 1,200 visitors, 1,450 sessions
		expect(screen.getAllByText('1,200').length).toBeGreaterThanOrEqual(1);
		expect(screen.getByText('1,450 sessions')).toBeInTheDocument();
	});

	// US-002 — Sources grouped under channel headers
	it('groups sources under channel headings with per-channel totals', () => {
		mockUseGroupedSources.mockReturnValue({
			data: groupedChannels,
			isLoading: false,
		});
		mockUseUtm.mockReturnValue({ data: [], isLoading: false });

		render(<ReferrersPage />);

		// Individual sources appear under their channel
		expect(screen.getByText('google.com')).toBeInTheDocument();
		expect(screen.getByText('bing.com')).toBeInTheDocument();
		expect(screen.getByText('twitter.com')).toBeInTheDocument();
		expect(screen.getByText('partner.io')).toBeInTheDocument();
		expect(screen.getByText('newsletter')).toBeInTheDocument();

		// Channel header totals (format: "X visitors · Y sessions")
		expect(screen.getByText('1,200 visitors · 1,450 sessions')).toBeInTheDocument();
		expect(screen.getByText('500 visitors · 600 sessions')).toBeInTheDocument();
	});

	// US-002 — Empty channels are hidden
	it('hides channels with no sources in the date range', () => {
		mockUseGroupedSources.mockReturnValue({
			data: [groupedChannels[0]], // Only Organic Search
			isLoading: false,
		});
		mockUseUtm.mockReturnValue({ data: [], isLoading: false });

		render(<ReferrersPage />);

		expect(screen.getAllByText('Organic Search').length).toBeGreaterThanOrEqual(1);
		// Other channels should not appear in the table
		expect(screen.queryByText('500 visitors · 600 sessions')).not.toBeInTheDocument();
	});

	// US-002 — Loading state
	it('shows loading skeleton while sources data is loading', () => {
		mockUseGroupedSources.mockReturnValue({
			data: undefined,
			isLoading: true,
		});
		mockUseUtm.mockReturnValue({ data: [], isLoading: false });

		const { container } = render(<ReferrersPage />);

		expect(container.querySelectorAll('.animate-pulse').length).toBeGreaterThan(0);
	});

	// US-002 — Empty state
	it('shows empty message when no source data exists', () => {
		mockUseGroupedSources.mockReturnValue({
			data: [],
			isLoading: false,
		});
		mockUseUtm.mockReturnValue({ data: [], isLoading: false });

		render(<ReferrersPage />);

		expect(screen.getByText(/No referrer data for this period/)).toBeInTheDocument();
	});

	// REQ-1.16 — UTM campaign breakdown table
	it('renders UTM Campaigns table with campaign, source, medium, and visitors columns', () => {
		mockUseGroupedSources.mockReturnValue({ data: [], isLoading: false });
		mockUseUtm.mockReturnValue({
			data: [
				{ campaign: 'spring-sale', source: 'newsletter', medium: 'email', visitors: 280, sessions: 310 },
			],
			isLoading: false,
		});

		render(<ReferrersPage />);

		expect(screen.getByText('UTM Campaigns')).toBeInTheDocument();
		expect(screen.getByText('spring-sale')).toBeInTheDocument();
		// "newsletter" appears both as UTM source and potentially as a source name
		expect(screen.getAllByText('newsletter').length).toBeGreaterThanOrEqual(1);
		expect(screen.getByText('email')).toBeInTheDocument();
		expect(screen.getByText('280')).toBeInTheDocument();
	});

	// REQ-1.17 — Empty state when no UTM data exists
	it('shows empty message when no UTM campaign data is recorded', () => {
		mockUseGroupedSources.mockReturnValue({ data: [], isLoading: false });
		mockUseUtm.mockReturnValue({ data: [], isLoading: false });

		render(<ReferrersPage />);

		expect(screen.getByText('No UTM parameters tracked yet. UTM-tagged links will appear here once visitors arrive through them.')).toBeInTheDocument();
	});
});
