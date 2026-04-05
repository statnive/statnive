import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import { DualBarCell } from '@/components/shared/dual-bar-cell';

describe('DualBarCell', () => {
	it('renders visitor and secondary metric values', () => {
		render(
			<DualBarCell
				visitors={500}
				secondaryValue={200}
				maxVisitors={1000}
				maxSecondary={500}
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
				maxVisitors={1000}
				maxSecondary={500}
			/>,
		);

		const bars = container.querySelectorAll('[class*="rounded-full"]');
		expect(bars.length).toBeGreaterThanOrEqual(2);
	});

	it('handles zero max values without crashing', () => {
		render(
			<DualBarCell
				visitors={0}
				secondaryValue={0}
				maxVisitors={0}
				maxSecondary={0}
			/>,
		);

		// Both bars show "0" — use getAllByText for multiple matches.
		expect(screen.getAllByText('0')).toHaveLength(2);
	});
});
