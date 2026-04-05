// Generated from BDD scenarios — Feature: Dashboard Overview — Date range picker presets (REQ-1.5)

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { DateRangePicker } from '@/components/shared/date-range-picker';

describe('DateRangePicker', () => {
	beforeEach(() => {
		vi.restoreAllMocks();
	});

	// REQ-1.5 — Date range picker presets resolve correct date boundaries
	it.each([
		{ preset: 'Today', value: 'today' },
		{ preset: '7 Days', value: '7d' },
		{ preset: '30 Days', value: '30d' },
		{ preset: 'This Month', value: 'this-month' },
		{ preset: 'Last Month', value: 'last-month' },
	])('calls onChange with "$value" when "$preset" button is clicked', async ({ preset, value }) => {
		const onChange = vi.fn();
		const user = userEvent.setup();

		render(<DateRangePicker value="7d" onChange={onChange} />);

		const button = screen.getByText(preset);
		expect(button).toBeInTheDocument();

		await user.click(button);
		expect(onChange).toHaveBeenCalledWith(value);
	});

	// Visual active state — the component uses CSS classes (bg-primary) for the
	// active button rather than aria-pressed. We verify via class name.
	it('highlights the currently selected preset button with primary style', () => {
		const onChange = vi.fn();

		render(<DateRangePicker value="today" onChange={onChange} />);

		const todayButton = screen.getByText('Today');
		expect(todayButton.className).toContain('bg-primary');

		const sevenDayButton = screen.getByText('7 Days');
		expect(sevenDayButton.className).not.toContain('bg-primary');
	});

	// Accessibility: group role and label
	it('renders with role="group" and aria-label="Date range"', () => {
		const onChange = vi.fn();

		render(<DateRangePicker value="7d" onChange={onChange} />);

		const group = screen.getByRole('group', { name: 'Date range' });
		expect(group).toBeInTheDocument();
	});

	// All 5 buttons are keyboard-focusable
	it('renders all 5 preset buttons as focusable elements', () => {
		const onChange = vi.fn();

		render(<DateRangePicker value="7d" onChange={onChange} />);

		const buttons = screen.getAllByRole('button');
		expect(buttons.length).toBe(5);
		buttons.forEach((btn) => {
			expect(btn).not.toHaveAttribute('tabindex', '-1');
		});
	});
});
