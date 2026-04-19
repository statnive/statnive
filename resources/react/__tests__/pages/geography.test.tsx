// Generated from BDD scenarios — Feature: Dashboard Detail Pages — Geography screen

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

const mockUseDimensions = vi.fn();
vi.mock('@/hooks/use-dimensions', () => ({
	useDimensions: (...args: unknown[]) => mockUseDimensions(...args),
}));

const mockUseGeoSource = vi.fn(() => 'maxmind' as const);
vi.mock('@/hooks/use-geo-source', () => ({
	useGeoSource: () => mockUseGeoSource(),
}));

import { GeographyPage } from '@/pages/geography';

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('GeographyPage', () => {
	beforeEach(() => {
		vi.restoreAllMocks();
		mockUseGeoSource.mockReturnValue('maxmind');
	});

	describe('empty state by geo source', () => {
		beforeEach(() => {
			mockUseDimensions.mockReturnValue({ data: [], isLoading: false });
		});

		it('prompts to configure a CDN or MaxMind when source is none', () => {
			mockUseGeoSource.mockReturnValue('none');

			render(<GeographyPage />);

			expect(
				screen.getAllByText(/Geography needs an approximate-country source/),
			).toHaveLength(2);
		});

		it('shows "data will appear" when CDN headers are active but period is empty', () => {
			mockUseGeoSource.mockReturnValue('cdn_headers');

			render(<GeographyPage />);

			expect(
				screen.getAllByText(/Country detection via your CDN is active/),
			).toHaveLength(2);
		});

		it('shows the existing empty copy when MaxMind is configured but period is empty', () => {
			mockUseGeoSource.mockReturnValue('maxmind');

			render(<GeographyPage />);

			expect(
				screen.getAllByText(/No geography data for this period/),
			).toHaveLength(2);
		});
	});

	// REQ-1.18 — Countries table with visitor and session counts
	it('renders Countries table with country code, name, visitors, and sessions', () => {
		mockUseDimensions.mockImplementation((dimension: string) => {
			if (dimension === 'countries') {
				return {
					data: [
						{ code: 'US', name: 'United States', visitors: 1500, sessions: 2100 },
						{ code: 'DE', name: 'Germany', visitors: 340, sessions: 410 },
					],
					isLoading: false,
				};
			}
			return { data: [], isLoading: false };
		});

		render(<GeographyPage />);

		expect(screen.getByText('Countries')).toBeInTheDocument();
		expect(screen.getByText(/US — United States/)).toBeInTheDocument();
		expect(screen.getByText('1,500')).toBeInTheDocument();
		expect(screen.getByText('2,100')).toBeInTheDocument();
		expect(screen.getByText(/DE — Germany/)).toBeInTheDocument();
		expect(screen.getByText('340')).toBeInTheDocument();
		expect(screen.getByText('410')).toBeInTheDocument();
	});

	// REQ-1.19 — Cities table with parent country
	it('renders Cities table with city name, parent country, and visitors', () => {
		mockUseDimensions.mockImplementation((dimension: string) => {
			if (dimension === 'cities') {
				return {
					data: [
						{ city_name: 'Berlin', country: 'Germany', visitors: 180, sessions: 220 },
						{ city_name: 'Munich', country: 'Germany', visitors: 95, sessions: 110 },
					],
					isLoading: false,
				};
			}
			return { data: [], isLoading: false };
		});

		render(<GeographyPage />);

		expect(screen.getByText('Cities')).toBeInTheDocument();
		expect(screen.getByText('Berlin')).toBeInTheDocument();
		expect(screen.getByText('Munich')).toBeInTheDocument();
		const germanyCells = screen.getAllByText('Germany');
		expect(germanyCells.length).toBe(2);
		expect(screen.getByText('180')).toBeInTheDocument();
		expect(screen.getByText('95')).toBeInTheDocument();
	});
});
