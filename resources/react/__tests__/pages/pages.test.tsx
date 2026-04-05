// Generated from BDD scenarios — Feature: Dashboard Detail Pages — Pages screen

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

const mockUsePages = vi.fn();
vi.mock('@/hooks/use-pages', () => ({
	usePages: (...args: unknown[]) => mockUsePages(...args),
}));

const mockUseEntryPages = vi.fn();
const mockUseExitPages = vi.fn();
vi.mock('@/hooks/use-entry-exit-pages', () => ({
	useEntryPages: (...args: unknown[]) => mockUseEntryPages(...args),
	useExitPages: (...args: unknown[]) => mockUseExitPages(...args),
}));

import { PagesPage } from '@/pages/pages';

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('PagesPage', () => {
	beforeEach(() => {
		vi.restoreAllMocks();
	});

	// REQ-1.13 — Pages screen shows top content table with URI, title, visitors, views, and duration
	it('renders the Top Content table with Page, Visitors, Views, and Avg Duration columns', () => {
		mockUsePages.mockReturnValue({
			data: [
				{ uri: '/blog/seo-guide', title: 'SEO Guide', visitors: 320, views: 540, total_duration: 19200, bounces: 20 },
			],
			isLoading: false,
		});
		mockUseEntryPages.mockReturnValue({ data: [], isLoading: false });
		mockUseExitPages.mockReturnValue({ data: [], isLoading: false });

		render(<PagesPage />);

		expect(screen.getByText('Top Content')).toBeInTheDocument();
		expect(screen.getByText('SEO Guide')).toBeInTheDocument();
		expect(screen.getByText('/blog/seo-guide')).toBeInTheDocument();
		expect(screen.getByText('320')).toBeInTheDocument();
		expect(screen.getByText('540')).toBeInTheDocument();
		// 19200 / 320 = 60s => "1m"
		expect(screen.getByText('1m')).toBeInTheDocument();
	});

	// REQ-1.13 — Search input presence
	it('renders a search input with placeholder "Search pages..."', () => {
		mockUsePages.mockReturnValue({ data: [], isLoading: false });
		mockUseEntryPages.mockReturnValue({ data: [], isLoading: false });
		mockUseExitPages.mockReturnValue({ data: [], isLoading: false });

		render(<PagesPage />);

		expect(screen.getByPlaceholderText('Search pages...')).toBeInTheDocument();
	});

	// REQ-1.14 — Entry pages and exit pages in side-by-side tables
	it('renders Entry Pages and Exit Pages tables with count columns', () => {
		mockUsePages.mockReturnValue({ data: [], isLoading: false });
		mockUseEntryPages.mockReturnValue({
			data: [
				{ uri: '/', title: 'Home', count: 890, visitors: 750 },
			],
			isLoading: false,
		});
		mockUseExitPages.mockReturnValue({
			data: [
				{ uri: '/checkout/thank-you', title: 'Thank You', count: 420, visitors: 380 },
			],
			isLoading: false,
		});

		render(<PagesPage />);

		expect(screen.getByText('Entry Pages')).toBeInTheDocument();
		expect(screen.getByText('Exit Pages')).toBeInTheDocument();
		expect(screen.getByText('Home')).toBeInTheDocument();
		expect(screen.getByText('890')).toBeInTheDocument();
		expect(screen.getByText('Thank You')).toBeInTheDocument();
		expect(screen.getByText('420')).toBeInTheDocument();
	});
});
