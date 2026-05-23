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
const mockUseDbipCityActive = vi.fn(() => false);
vi.mock('@/hooks/use-geo-source', () => ({
	useGeoSource: () => mockUseGeoSource(),
	useDbipCityActive: () => mockUseDbipCityActive(),
}));

const mockMutate = vi.fn();
const mockInvalidate = vi.fn();
const mutationState = { isPending: false, isSuccess: false, isError: false };
vi.mock('@tanstack/react-query', async () => {
	const actual = await vi.importActual<Record<string, unknown>>('@tanstack/react-query');
	return {
		...actual,
		useMutation: () => ({
			mutate: mockMutate,
			...mutationState,
		}),
		useQueryClient: () => ({
			invalidateQueries: mockInvalidate,
		}),
	};
});

const mockApiPost = vi.fn();
vi.mock('@/lib/api-client', () => ({
	apiPost: (path: string) => mockApiPost(path),
	apiGet: vi.fn(),
	apiPut: vi.fn(),
	getCurrentIp: () => '127.0.0.1',
}));

import { GeographyPage } from '@/pages/geography';

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('GeographyPage', () => {
	beforeEach(() => {
		vi.restoreAllMocks();
		mockUseGeoSource.mockReturnValue('maxmind');
		mockUseDbipCityActive.mockReturnValue(false);
		mockMutate.mockClear();
		mockInvalidate.mockClear();
		mockApiPost.mockClear();
		mutationState.isPending = false;
		mutationState.isSuccess = false;
		mutationState.isError = false;
	});

	describe('empty state by geo source', () => {
		beforeEach(() => {
			mockUseDimensions.mockReturnValue({ data: [], isLoading: false });
		});

		it('explains the timezone fallback is active when source is timezone', () => {
			mockUseGeoSource.mockReturnValue('timezone');

			render(<GeographyPage />);

			expect(
				screen.getAllByText(/derived from each visitor’s browser timezone/),
			).toHaveLength(2);
		});

		it('shows "data will appear" when CDN headers are active but period is empty', () => {
			mockUseGeoSource.mockReturnValue('cdn_headers');

			render(<GeographyPage />);

			expect(
				screen.getAllByText(/Country detection via your CDN is active/),
			).toHaveLength(2);
		});

		it('flags resolution as disabled when source is none', () => {
			mockUseGeoSource.mockReturnValue('none');

			render(<GeographyPage />);

			expect(
				screen.getAllByText(/Geography resolution is currently disabled/),
			).toHaveLength(2);
		});

		it('shows the existing empty copy when MaxMind is configured but period is empty', () => {
			mockUseGeoSource.mockReturnValue('maxmind');

			render(<GeographyPage />);

			expect(
				screen.getAllByText(/No geography data for this period/),
			).toHaveLength(2);
		});

		it('shows DB-IP active copy when source is dbip_city and period is empty', () => {
			mockUseGeoSource.mockReturnValue('dbip_city');
			mockUseDbipCityActive.mockReturnValue(true);

			render(<GeographyPage />);

			expect(
				screen.getAllByText(/free DB-IP city database is active/),
			).toHaveLength(2);
		});
	});

	describe('DB-IP one-click CTA', () => {
		beforeEach(() => {
			mockUseDimensions.mockReturnValue({ data: [], isLoading: false });
		});

		it('renders the CTA when source is cdn_headers and DB-IP is not active', () => {
			mockUseGeoSource.mockReturnValue('cdn_headers');
			mockUseDbipCityActive.mockReturnValue(false);

			render(<GeographyPage />);

			expect(screen.getByText(/Want city-level data\?/)).toBeInTheDocument();
			expect(screen.getByRole('button', { name: /Enable city-level geography/ })).toBeInTheDocument();
		});

		it('hides the CTA once DB-IP is active', () => {
			mockUseGeoSource.mockReturnValue('dbip_city');
			mockUseDbipCityActive.mockReturnValue(true);

			render(<GeographyPage />);

			expect(screen.queryByText(/Want city-level data\?/)).not.toBeInTheDocument();
			expect(screen.queryByRole('button', { name: /Enable city-level geography/ })).not.toBeInTheDocument();
		});

		it('hides the CTA when MaxMind is the active source', () => {
			mockUseGeoSource.mockReturnValue('maxmind');
			mockUseDbipCityActive.mockReturnValue(false);

			render(<GeographyPage />);

			expect(screen.queryByText(/Want city-level data\?/)).not.toBeInTheDocument();
		});

		it('shows the DB-IP attribution footer only when source is dbip_city', () => {
			mockUseGeoSource.mockReturnValue('cdn_headers');
			mockUseDbipCityActive.mockReturnValue(false);

			const { rerender } = render(<GeographyPage />);
			expect(screen.queryByText(/GeoIP data © DB-IP/)).not.toBeInTheDocument();

			mockUseGeoSource.mockReturnValue('dbip_city');
			mockUseDbipCityActive.mockReturnValue(true);
			rerender(<GeographyPage />);

			expect(screen.getByText(/GeoIP data © DB-IP/)).toBeInTheDocument();
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
