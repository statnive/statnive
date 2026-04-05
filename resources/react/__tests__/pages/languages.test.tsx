// Generated from BDD scenarios — Feature: Dashboard Detail Pages — Languages screen

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

import { LanguagesPage } from '@/pages/languages';

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('LanguagesPage', () => {
	beforeEach(() => {
		vi.restoreAllMocks();
	});

	// REQ-1.22 — Language distribution table
	it('renders Visitor Languages table with language, visitors, and sessions', () => {
		mockUseDimensions.mockReturnValue({
			data: [
				{ name: 'English', visitors: 1800, sessions: 2300 },
				{ name: 'German', visitors: 420, sessions: 510 },
			],
			isLoading: false,
		});

		render(<LanguagesPage />);

		expect(screen.getByText('Visitor Languages')).toBeInTheDocument();
		expect(screen.getByText('English')).toBeInTheDocument();
		expect(screen.getByText('1,800')).toBeInTheDocument();
		expect(screen.getByText('2,300')).toBeInTheDocument();
		expect(screen.getByText('German')).toBeInTheDocument();
		expect(screen.getByText('420')).toBeInTheDocument();
		expect(screen.getByText('510')).toBeInTheDocument();
	});
});
