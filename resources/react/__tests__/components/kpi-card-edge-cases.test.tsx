// Edge-case tests for KpiCard — change badge arithmetic, loading, and fallback states

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import { KpiCard } from '@/components/shared/kpi-card';

describe('KpiCard edge cases', () => {
	beforeEach(() => {
		vi.restoreAllMocks();
	});

	// Edge case: zero previous period — percentChange(100, 0) returns 100, not Infinity
	// The util caps 0→positive at 100, so the badge should show "↑ 100.0%"
	it('shows 100% change when previous period was zero and current is positive', () => {
		render(<KpiCard label="Visitors" value="100" change={100} />);

		const badge = screen.getByText(/100\.0%/);
		expect(badge).toBeInTheDocument();
		expect(badge.className).toContain('bg-revenue');
	});

	// Edge case: both periods zero — percentChange(0, 0) returns 0
	it('shows 0% change when both current and previous are zero', () => {
		render(<KpiCard label="Visitors" value="0" change={0} />);

		const badge = screen.getByText(/0\.0%/);
		expect(badge).toBeInTheDocument();
		// Zero is treated as non-negative, so green badge
		expect(badge.className).toContain('bg-revenue');
	});

	// Edge case: negative to positive transition (e.g., change = 150 meaning +150%)
	it('renders positive badge for large positive change value', () => {
		render(<KpiCard label="Sessions" value="250" change={150} />);

		const badge = screen.getByText(/150\.0%/);
		expect(badge).toBeInTheDocument();
		expect(badge.className).toContain('bg-revenue');
		expect(badge.textContent).toContain('↑');
	});

	// Edge case: negative change renders red badge with down arrow
	it('renders negative badge with down arrow for negative change', () => {
		render(<KpiCard label="Pageviews" value="50" change={-33.3} />);

		const badge = screen.getByText(/33\.3%/);
		expect(badge).toBeInTheDocument();
		expect(badge.className).toContain('bg-destructive');
		expect(badge.textContent).toContain('↓');
	});

	// Loading state renders skeleton placeholders, not data
	it('renders loading skeleton with animate-pulse elements', () => {
		const { container } = render(
			<KpiCard label="Visitors" value="999" change={10} isLoading />,
		);

		// Should NOT render actual value or label when loading
		expect(screen.queryByText('999')).not.toBeInTheDocument();
		expect(screen.queryByText('Visitors')).not.toBeInTheDocument();

		// Should render skeleton pulse divs
		const skeletons = container.querySelectorAll('.animate-pulse');
		expect(skeletons.length).toBe(2);
	});

	// No change badge when change is undefined
	it('does not render a change badge when change prop is undefined', () => {
		render(<KpiCard label="Avg Duration" value="2:30" />);

		expect(screen.getByText('Avg Duration')).toBeInTheDocument();
		expect(screen.getByText('2:30')).toBeInTheDocument();
		// No arrow or percentage text
		expect(screen.queryByText(/↑/)).not.toBeInTheDocument();
		expect(screen.queryByText(/↓/)).not.toBeInTheDocument();
	});

	// Accessibility: change badge has descriptive aria-label
	it('provides an aria-label on the change badge describing direction and magnitude', () => {
		render(<KpiCard label="Visitors" value="500" change={16.7} />);

		const badge = screen.getByLabelText(/Change up .* versus previous period/);
		expect(badge).toBeInTheDocument();
	});

	it('provides a "down" aria-label for negative change', () => {
		render(<KpiCard label="Visitors" value="500" change={-25} />);

		const badge = screen.getByLabelText(/Change down .* versus previous period/);
		expect(badge).toBeInTheDocument();
	});
});
