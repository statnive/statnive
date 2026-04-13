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

const mockUseSources = vi.fn();
vi.mock('@/hooks/use-sources', () => ({
	useSources: (...args: unknown[]) => mockUseSources(...args),
}));

const mockUseUtm = vi.fn();
vi.mock('@/hooks/use-utm', () => ({
	useUtm: (...args: unknown[]) => mockUseUtm(...args),
}));

import { ReferrersPage } from '@/pages/referrers';

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('ReferrersPage', () => {
	beforeEach(() => {
		vi.restoreAllMocks();
	});

	// REQ-1.15 — Referrer sources grouped by channel with summary cards
	it('renders channel summary cards with visitor and session counts', () => {
		mockUseSources.mockReturnValue({
			data: [
				{ channel: 'Organic Search', name: 'google.com', domain: 'google.com', visitors: 1200, sessions: 1450, views: 3200 },
				{ channel: 'Social Media', name: 'twitter.com', domain: 'twitter.com', visitors: 340, sessions: 380, views: 900 },
				{ channel: 'Direct', name: null, domain: null, visitors: 500, sessions: 600, views: 1500 },
				{ channel: 'Referral', name: 'partner.io', domain: 'partner.io', visitors: 150, sessions: 180, views: 400 },
				{ channel: 'Email', name: 'newsletter', domain: null, visitors: 80, sessions: 90, views: 200 },
			],
			isLoading: false,
		});
		mockUseUtm.mockReturnValue({ data: [], isLoading: false });

		render(<ReferrersPage />);

		// Channel names appear both in summary cards and in the source table rows
		expect(screen.getAllByText('Organic Search').length).toBeGreaterThanOrEqual(1);
		expect(screen.getAllByText('Direct').length).toBeGreaterThanOrEqual(1);
		expect(screen.getAllByText('Social Media').length).toBeGreaterThanOrEqual(1);
		expect(screen.getAllByText('Referral').length).toBeGreaterThanOrEqual(1);
		expect(screen.getAllByText('Email').length).toBeGreaterThanOrEqual(1);

		// Organic Search card shows 1,200 visitors and 1,450 sessions
		// "1,200" may appear in both the channel card and the source table
		expect(screen.getAllByText('1,200').length).toBeGreaterThanOrEqual(1);
		expect(screen.getByText('1,450 sessions')).toBeInTheDocument();
	});

	// REQ-1.16 — UTM campaign breakdown table
	it('renders UTM Campaigns table with campaign, source, medium, and visitors columns', () => {
		mockUseSources.mockReturnValue({ data: [], isLoading: false });
		mockUseUtm.mockReturnValue({
			data: [
				{ campaign: 'spring-sale', source: 'newsletter', medium: 'email', visitors: 280, sessions: 310 },
			],
			isLoading: false,
		});

		render(<ReferrersPage />);

		expect(screen.getByText('UTM Campaigns')).toBeInTheDocument();
		expect(screen.getByText('spring-sale')).toBeInTheDocument();
		expect(screen.getByText('newsletter')).toBeInTheDocument();
		expect(screen.getByText('email')).toBeInTheDocument();
		expect(screen.getByText('280')).toBeInTheDocument();
	});

	// REQ-1.17 — Empty state when no UTM data exists
	it('shows empty message when no UTM campaign data is recorded', () => {
		mockUseSources.mockReturnValue({ data: [], isLoading: false });
		mockUseUtm.mockReturnValue({ data: [], isLoading: false });

		render(<ReferrersPage />);

		expect(screen.getByText('No UTM parameters tracked yet. UTM-tagged links will appear here once visitors arrive through them.')).toBeInTheDocument();
	});
});
