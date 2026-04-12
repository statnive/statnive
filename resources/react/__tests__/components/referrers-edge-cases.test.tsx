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

describe('ReferrersPage edge cases', () => {
	beforeEach(() => {
		vi.restoreAllMocks();
	});

	it('shows "No UTM parameters tracked yet" when UTM data is empty', () => {
		mockUseSources.mockReturnValue({ data: [], isLoading: false });
		mockUseUtm.mockReturnValue({ data: [], isLoading: false });

		render(<ReferrersPage />);

		expect(screen.getByText('No UTM parameters tracked yet')).toBeInTheDocument();
	});

	it('shows fallback "Direct" for source with null name', () => {
		mockUseSources.mockReturnValue({
			data: [
				{ channel: 'Direct', name: null, domain: null, visitors: 100, sessions: 120, views: 300 },
			],
			isLoading: false,
		});
		mockUseUtm.mockReturnValue({ data: [], isLoading: false });

		render(<ReferrersPage />);

		// The source render function uses `row.name ?? 'Direct'`
		expect(screen.getAllByText('Direct').length).toBeGreaterThanOrEqual(1);
	});

	it('shows empty string fallback for source with empty channel', () => {
		mockUseSources.mockReturnValue({
			data: [
				{ channel: '', name: 'unknown-source.com', domain: 'unknown-source.com', visitors: 50, sessions: 60, views: 100 },
			],
			isLoading: false,
		});
		mockUseUtm.mockReturnValue({ data: [], isLoading: false });

		render(<ReferrersPage />);

		// The channel renders via `row.channel ?? ''` — empty string is rendered as-is
		expect(screen.getByText('unknown-source.com')).toBeInTheDocument();
	});

	it('renders without crash when source name is very long (200+ chars)', () => {
		const longName = 'a'.repeat(250);
		mockUseSources.mockReturnValue({
			data: [
				{ channel: 'Referral', name: longName, domain: longName, visitors: 10, sessions: 12, views: 20 },
			],
			isLoading: false,
		});
		mockUseUtm.mockReturnValue({ data: [], isLoading: false });

		const { container } = render(<ReferrersPage />);

		// Should render without throwing
		expect(container.querySelector('div')).toBeInTheDocument();
		expect(screen.getByText(longName)).toBeInTheDocument();
	});
});
