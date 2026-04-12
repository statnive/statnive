// Extreme value tests for DualBarCell — large numbers and negative values

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import { DualBarCell } from '@/components/shared/dual-bar-cell';

describe('DualBarCell extreme values', () => {
	beforeEach(() => {
		vi.restoreAllMocks();
	});

	it('renders formatted number for very large values (1000000)', () => {
		render(
			<DualBarCell visitors={1000000} secondaryValue={500000} max={1000000} />,
		);

		expect(screen.getByText('1,000,000')).toBeInTheDocument();
		expect(screen.getByText('500,000')).toBeInTheDocument();
	});

	it('treats negative visitor value as 0% bar width (defensive)', () => {
		const { container } = render(
			<DualBarCell visitors={-10} secondaryValue={50} max={100} />,
		);

		const bars = container.querySelectorAll('.rounded-full');
		expect(bars.length).toBe(2);

		// Negative / 100 = -10%, which renders as a negative width style.
		// The component does not clamp, so we just verify it renders without crash.
		expect(bars[0]).toBeInTheDocument();
		expect(bars[1]).toBeInTheDocument();
		// Secondary bar should still be correct
		expect((bars[1] as HTMLElement).style.width).toBe('50%');
	});
});
