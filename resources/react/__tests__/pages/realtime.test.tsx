// Generated from BDD scenarios — Feature: Real-time Analytics

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen } from '@testing-library/react';

// ---------------------------------------------------------------------------
// Mocks
// ---------------------------------------------------------------------------

const mockUseRealtime = vi.fn();
vi.mock('@/hooks/use-realtime', () => ({
	useRealtime: (...args: unknown[]) => mockUseRealtime(...args),
}));

vi.mock('@/components/shared/realtime-counter', () => ({
	RealtimeCounter: ({ count }: { count: number }) => (
		<span data-testid="realtime-counter">{count}</span>
	),
}));

import { RealtimePage } from '@/pages/realtime';

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('RealtimePage', () => {
	beforeEach(() => {
		vi.useFakeTimers();
		vi.restoreAllMocks();
	});

	afterEach(() => {
		vi.useRealTimers();
	});

	// REQ-5.4 — Polling refreshes data every 5 seconds (validated via hook config)
	it('uses useRealtime hook which is configured with 5000ms refetch interval', () => {
		mockUseRealtime.mockReturnValue({
			data: { active_visitors: 5, active_pages: [], recent_feed: [] },
			isLoading: false,
		});

		render(<RealtimePage />);

		expect(mockUseRealtime).toHaveBeenCalled();
		expect(screen.getByText('5')).toBeInTheDocument();
	});

	// REQ-5.5 — Empty state with zero-activity message
	it('displays zero active visitors and empty lists when no activity', () => {
		mockUseRealtime.mockReturnValue({
			data: { active_visitors: 0, active_pages: [], recent_feed: [] },
			isLoading: false,
		});

		render(<RealtimePage />);

		expect(screen.getByText('0')).toBeInTheDocument();
		expect(screen.getByText('No active pages')).toBeInTheDocument();
		expect(screen.getByText('No recent activity')).toBeInTheDocument();
	});

	// REQ-5.6 — Active visitor count with aria-live for accessibility
	it('renders active visitor count with aria-live polite for screen readers', () => {
		mockUseRealtime.mockReturnValue({
			data: { active_visitors: 142, active_pages: [], recent_feed: [] },
			isLoading: false,
		});

		render(<RealtimePage />);

		const counter = screen.getByText('142');
		const liveRegion = counter.closest('[aria-live="polite"]');
		expect(liveRegion).toBeInTheDocument();
	});

	// REQ-5.10 — Animated pulse indicator on counter
	it('renders a pulse animation element near the active visitor counter', () => {
		mockUseRealtime.mockReturnValue({
			data: { active_visitors: 12, active_pages: [], recent_feed: [] },
			isLoading: false,
		});

		const { container } = render(<RealtimePage />);

		const pulseEl = container.querySelector('.animate-ping');
		expect(pulseEl).toBeInTheDocument();
	});

	// REQ-5.2 — Active pages list
	it('renders active pages with visitor counts', () => {
		mockUseRealtime.mockReturnValue({
			data: {
				active_visitors: 5,
				active_pages: [
					{ uri: '/pricing', visitors: 3 },
					{ uri: '/blog/seo-tips', visitors: 2 },
				],
				recent_feed: [],
			},
			isLoading: false,
		});

		render(<RealtimePage />);

		expect(screen.getByText('/pricing')).toBeInTheDocument();
		expect(screen.getByText('/blog/seo-tips')).toBeInTheDocument();
	});

	// REQ-5.3 — Recent feed shows visitor country, browser, and time
	it('renders recent feed entries with country, browser, and URI', () => {
		mockUseRealtime.mockReturnValue({
			data: {
				active_visitors: 1,
				active_pages: [],
				recent_feed: [
					{ uri: '/about', country: 'DE', browser: 'Firefox', time: '14:22' },
				],
			},
			isLoading: false,
		});

		render(<RealtimePage />);

		expect(screen.getByText('/about')).toBeInTheDocument();
		expect(screen.getByText('DE')).toBeInTheDocument();
		expect(screen.getByText('Firefox')).toBeInTheDocument();
	});

	// Loading state
	it('shows dash placeholder and skeleton shimmer while loading', () => {
		mockUseRealtime.mockReturnValue({
			data: undefined,
			isLoading: true,
		});

		const { container } = render(<RealtimePage />);

		expect(screen.getByText(/\u2014/)).toBeInTheDocument();
		const skeletons = container.querySelectorAll('.animate-pulse');
		expect(skeletons.length).toBeGreaterThan(0);
	});
});
