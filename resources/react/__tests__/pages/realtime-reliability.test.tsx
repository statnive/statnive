// Resilience tests for RealtimePage — edge cases and extreme values

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

describe('RealtimePage reliability', () => {
	beforeEach(() => {
		vi.useFakeTimers();
		vi.restoreAllMocks();
	});

	afterEach(() => {
		vi.useRealTimers();
	});

	it('renders large visitor count (999999) formatted with commas', () => {
		mockUseRealtime.mockReturnValue({
			data: { active_visitors: 999999, active_pages: [], recent_feed: [] },
			isLoading: false,
		});

		render(<RealtimePage />);

		expect(screen.getByText('999,999')).toBeInTheDocument();
	});

	it('shows "No active pages" when active_pages array is empty', () => {
		mockUseRealtime.mockReturnValue({
			data: { active_visitors: 5, active_pages: [], recent_feed: [] },
			isLoading: false,
		});

		render(<RealtimePage />);

		expect(screen.getByText('No active pages')).toBeInTheDocument();
	});

	it('renders without crash when data is undefined', () => {
		mockUseRealtime.mockReturnValue({
			data: undefined,
			isLoading: false,
		});

		const { container } = render(<RealtimePage />);

		// Should render the component without throwing
		expect(container.querySelector('div')).toBeInTheDocument();
		// Falls back to 0 for active_visitors
		expect(screen.getByText('0')).toBeInTheDocument();
	});
});
