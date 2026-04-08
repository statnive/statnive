import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import { DualBarCell } from '@/components/shared/dual-bar-cell';

describe('DualBarCell', () => {
	it('renders visitor and secondary metric values', () => {
		render(
			<DualBarCell
				visitors={500}
				secondaryValue={200}
				max={1000}
			/>,
		);

		expect(screen.getByText('500')).toBeInTheDocument();
		expect(screen.getByText('200')).toBeInTheDocument();
	});

	it('renders bars with proportional widths', () => {
		const { container } = render(
			<DualBarCell
				visitors={750}
				secondaryValue={250}
				max={1000}
			/>,
		);

		const bars = container.querySelectorAll('[class*="rounded-full"]');
		expect(bars.length).toBeGreaterThanOrEqual(2);
	});

	it('handles zero max value without crashing', () => {
		render(
			<DualBarCell
				visitors={0}
				secondaryValue={0}
				max={0}
			/>,
		);

		// Both bars show "0" — use getAllByText for multiple matches.
		expect(screen.getAllByText('0')).toHaveLength(2);
	});

	it('renders equal widths for equal values on a shared scale', () => {
		// Regression: previously each bar scaled against its own max, so
		// visitors=1 and sessions=1 produced different widths whenever the
		// global maxes differed. With a single shared max they must match.
		const { container } = render(
			<DualBarCell visitors={1} secondaryValue={1} max={28} />,
		);
		const bars = container.querySelectorAll('[style*="width"]');
		expect(bars).toHaveLength(2);
		expect(bars[0].getAttribute('style')).toBe(bars[1].getAttribute('style'));
	});
});
