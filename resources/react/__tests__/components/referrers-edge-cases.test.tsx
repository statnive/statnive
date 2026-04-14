// Edge-case tests for ReferrersPage — empty states, null values, long strings

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
// Tests
// ---------------------------------------------------------------------------

describe('ReferrersPage edge cases', () => {
	beforeEach(() => {
		vi.restoreAllMocks();
	});

	it('shows UTM empty-state message when UTM data is empty', () => {
		mockUseGroupedSources.mockReturnValue({ data: [], isLoading: false });
		mockUseUtm.mockReturnValue({ data: [], isLoading: false });

		render(<ReferrersPage />);

		expect(screen.getByText('No UTM parameters tracked yet. UTM-tagged links will appear here once visitors arrive through them.')).toBeInTheDocument();
	});

	it('shows fallback "Direct" for source with empty name in Direct channel', () => {
		mockUseGroupedSources.mockReturnValue({
			data: [
				{
					channel: 'Direct',
					visitors: 100,
					sessions: 120,
					views: 300,
					sources: [
						{ name: '', domain: '', visitors: 100, sessions: 120, views: 300 },
					],
				},
			],
			isLoading: false,
		});
		mockUseUtm.mockReturnValue({ data: [], isLoading: false });

		render(<ReferrersPage />);

		// The source render function uses `source.name || 'Direct'`
		expect(screen.getAllByText('Direct').length).toBeGreaterThanOrEqual(2);
	});

	it('renders without crash when source name is very long (200+ chars)', () => {
		const longName = 'a'.repeat(250);
		mockUseGroupedSources.mockReturnValue({
			data: [
				{
					channel: 'Referral',
					visitors: 10,
					sessions: 12,
					views: 20,
					sources: [
						{ name: longName, domain: longName, visitors: 10, sessions: 12, views: 20 },
					],
				},
			],
			isLoading: false,
		});
		mockUseUtm.mockReturnValue({ data: [], isLoading: false });

		const { container } = render(<ReferrersPage />);

		// Should render without throwing
		expect(container.querySelector('div')).toBeInTheDocument();
		expect(screen.getByText(longName)).toBeInTheDocument();
	});

	it('renders correctly with a single channel containing one source', () => {
		mockUseGroupedSources.mockReturnValue({
			data: [
				{
					channel: 'Email',
					visitors: 50,
					sessions: 55,
					views: 100,
					sources: [
						{ name: 'newsletter', domain: '', visitors: 50, sessions: 55, views: 100 },
					],
				},
			],
			isLoading: false,
		});
		mockUseUtm.mockReturnValue({ data: [], isLoading: false });

		render(<ReferrersPage />);

		expect(screen.getAllByText('Email').length).toBeGreaterThanOrEqual(2);
		expect(screen.getByText('newsletter')).toBeInTheDocument();
		expect(screen.getByText('50 visitors · 55 sessions')).toBeInTheDocument();
	});
});
