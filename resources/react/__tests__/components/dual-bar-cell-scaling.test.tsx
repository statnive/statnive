// Scaling and edge-case tests for DualBarCell — bar width calculations

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import { DualBarCell } from '@/components/shared/dual-bar-cell';

describe('DualBarCell scaling', () => {
	beforeEach(() => {
		vi.restoreAllMocks();
	});

	// Equal values should produce equal bar widths
	it('renders equal width bars when visitors and secondaryValue are equal', () => {
		const { container } = render(
			<DualBarCell visitors={500} secondaryValue={500} max={500} />,
		);

		const bars = container.querySelectorAll('.rounded-full');
		expect(bars.length).toBe(2);
		expect((bars[0] as HTMLElement).style.width).toBe('100%');
		expect((bars[1] as HTMLElement).style.width).toBe('100%');
	});

	// Extreme disparity: 1 visitor vs 10000 sessions with max = 10000
	it('renders proportionally correct bars for extreme value disparity', () => {
		const { container } = render(
			<DualBarCell visitors={1} secondaryValue={10000} max={10000} />,
		);

		const bars = container.querySelectorAll('.rounded-full');
		expect(bars.length).toBe(2);
		// Visitor bar: 1/10000 = 0.01%
		expect((bars[0] as HTMLElement).style.width).toBe('0.01%');
		// Secondary bar: 10000/10000 = 100%
		expect((bars[1] as HTMLElement).style.width).toBe('100%');
	});

	// Both zero values should produce 0% width bars
	it('renders 0% width bars when both values are zero', () => {
		const { container } = render(
			<DualBarCell visitors={0} secondaryValue={0} max={100} />,
		);

		const bars = container.querySelectorAll('.rounded-full');
		expect(bars.length).toBe(2);
		expect((bars[0] as HTMLElement).style.width).toBe('0%');
		expect((bars[1] as HTMLElement).style.width).toBe('0%');
	});

	// max=0 edge case — division by zero guard should produce 0% widths
	it('renders 0% width bars when max is 0 (avoids division by zero)', () => {
		const { container } = render(
			<DualBarCell visitors={50} secondaryValue={30} max={0} />,
		);

		const bars = container.querySelectorAll('.rounded-full');
		expect(bars.length).toBe(2);
		expect((bars[0] as HTMLElement).style.width).toBe('0%');
		expect((bars[1] as HTMLElement).style.width).toBe('0%');
	});

	// Formatted numbers appear as text labels
	it('displays formatted visitor and secondary value labels', () => {
		render(
			<DualBarCell visitors={1234} secondaryValue={5678} max={5678} />,
		);

		expect(screen.getByText('1,234')).toBeInTheDocument();
		expect(screen.getByText('5,678')).toBeInTheDocument();
	});

	// Secondary label prefix (e.g., "$") is prepended to the secondary value
	it('prepends secondaryLabel to the secondary value text', () => {
		render(
			<DualBarCell
				visitors={100}
				secondaryValue={250}
				secondaryLabel="$"
				max={250}
			/>,
		);

		expect(screen.getByText('$250')).toBeInTheDocument();
	});

	// Half-width bar for values at 50% of max
	it('renders 50% width bar when value is half of max', () => {
		const { container } = render(
			<DualBarCell visitors={50} secondaryValue={25} max={100} />,
		);

		const bars = container.querySelectorAll('.rounded-full');
		expect((bars[0] as HTMLElement).style.width).toBe('50%');
		expect((bars[1] as HTMLElement).style.width).toBe('25%');
	});
});
