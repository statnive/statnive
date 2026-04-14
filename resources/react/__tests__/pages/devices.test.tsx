// Generated from BDD scenarios — Feature: Dashboard Detail Pages — Devices screen

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

// Mock Recharts to avoid rendering SVG in jsdom
vi.mock('recharts', () => {
	const React = require('react');
	const MockPie = ({ data, children }: { data?: Array<{ name: string; value: number }>; children?: React.ReactNode }) =>
		React.createElement('div', { 'data-testid': 'recharts-pie', 'data-items': JSON.stringify(data) }, children);
	const MockCell = () => null;
	return {
		ResponsiveContainer: ({ children }: { children: React.ReactNode }) =>
			React.createElement('div', { 'data-testid': 'recharts-responsive' }, children),
		PieChart: ({ children }: { children: React.ReactNode }) =>
			React.createElement('div', { 'data-testid': 'recharts-piechart' }, children),
		Pie: MockPie,
		Cell: MockCell,
		Tooltip: () => null,
		Legend: () => null,
	};
});

import { DevicesPage } from '@/pages/devices';

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('DevicesPage', () => {
	beforeEach(() => {
		vi.restoreAllMocks();
	});

	// REQ-1.20 — Device distribution pie chart + Bot vs Human pie chart
	it('renders pie charts with device distribution and bot vs human data', () => {
		mockUseDimensions.mockImplementation((dimension: string) => {
			if (dimension === 'devices') {
				return {
					data: [
						{ name: 'Desktop', visitors: 1400, sessions: 1800 },
						{ name: 'Mobile', visitors: 900, sessions: 1100 },
						{ name: 'Tablet', visitors: 200, sessions: 250 },
						{ name: 'Bot', visitors: 50, sessions: 50 },
					],
					isLoading: false,
				};
			}
			return { data: [], isLoading: false };
		});

		render(<DevicesPage />);

		// Pie charts render (title + sr-only caption = 2 matches each)
		expect(screen.getAllByText('Device Distribution').length).toBeGreaterThanOrEqual(1);
		expect(screen.getAllByText('Bot vs Human').length).toBeGreaterThanOrEqual(1);

		// SR-only table has device breakdown (human devices only)
		expect(screen.getByText('Desktop')).toBeInTheDocument();
		expect(screen.getByText('Mobile')).toBeInTheDocument();
		expect(screen.getByText('Tablet')).toBeInTheDocument();

		// Bot vs Human SR table
		expect(screen.getByText('Human')).toBeInTheDocument();
		expect(screen.getByText('Bot')).toBeInTheDocument();
	});

	// REQ-1.21 — Browser and OS tables side by side
	it('renders Browsers and Operating Systems tables with visitor counts', () => {
		mockUseDimensions.mockImplementation((dimension: string) => {
			if (dimension === 'devices') {
				return { data: [], isLoading: false };
			}
			if (dimension === 'browsers') {
				return {
					data: [
						{ name: 'Chrome', visitors: 1200, sessions: 1500 },
						{ name: 'Safari', visitors: 600, sessions: 750 },
					],
					isLoading: false,
				};
			}
			if (dimension === 'oss') {
				return {
					data: [
						{ name: 'Windows', visitors: 1000, sessions: 1300 },
						{ name: 'macOS', visitors: 800, sessions: 1000 },
					],
					isLoading: false,
				};
			}
			return { data: [], isLoading: false };
		});

		render(<DevicesPage />);

		expect(screen.getByText('Browsers')).toBeInTheDocument();
		expect(screen.getByText('Chrome')).toBeInTheDocument();
		expect(screen.getByText('1,200')).toBeInTheDocument();

		expect(screen.getByText('Operating Systems')).toBeInTheDocument();
		expect(screen.getByText('Windows')).toBeInTheDocument();
		// "1,000" appears for both visitors and sessions columns; use getAllByText
		expect(screen.getAllByText('1,000').length).toBeGreaterThanOrEqual(1);
	});
});
