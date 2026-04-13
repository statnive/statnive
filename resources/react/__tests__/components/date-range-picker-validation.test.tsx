// Validation and interaction tests for DateRangePicker — labels, presets, styling, keyboard

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { DateRangePicker } from '@/components/shared/date-range-picker';

describe('DateRangePicker validation', () => {
	beforeEach(() => {
		vi.restoreAllMocks();
	});

	// All 5 preset buttons render with expected labels
	it('renders all 5 preset buttons with correct labels', () => {
		const onChange = vi.fn();

		render(<DateRangePicker value="7d" onChange={onChange} />);

		const expectedLabels = ['Today', '7 Days', '30 Days', 'This Month', 'Last Month'];
		expectedLabels.forEach((label) => {
			expect(screen.getByText(label)).toBeInTheDocument();
		});

		const buttons = screen.getAllByRole('button');
		expect(buttons.length).toBe(5);
	});

	// Each preset calls onChange with its specific DateRange value
	it.each([
		{ label: 'Today', expected: 'today' },
		{ label: '7 Days', expected: '7d' },
		{ label: '30 Days', expected: '30d' },
		{ label: 'This Month', expected: 'this-month' },
		{ label: 'Last Month', expected: 'last-month' },
	])('clicking "$label" calls onChange with "$expected"', async ({ label, expected }) => {
		const onChange = vi.fn();
		const user = userEvent.setup();

		render(<DateRangePicker value="7d" onChange={onChange} />);

		await user.click(screen.getByText(label));
		expect(onChange).toHaveBeenCalledWith(expected);
		expect(onChange).toHaveBeenCalledTimes(1);
	});

	// Active preset button has bg-primary class, inactive buttons do not
	it.each([
		{ active: 'today' as const, activeLabel: 'Today' },
		{ active: '30d' as const, activeLabel: '30 Days' },
		{ active: 'last-month' as const, activeLabel: 'Last Month' },
	])('applies bg-primary styling only to the "$activeLabel" button when active', ({ active, activeLabel }) => {
		const onChange = vi.fn();

		render(<DateRangePicker value={active} onChange={onChange} />);

		const activeButton = screen.getByText(activeLabel);
		expect(activeButton.className).toContain('bg-primary');

		// All other buttons should NOT have bg-primary
		const allButtons = screen.getAllByRole('button');
		allButtons.forEach((btn) => {
			if (btn.textContent !== activeLabel) {
				expect(btn.className).not.toContain('bg-primary');
			}
		});
	});

	// Keyboard navigation: Tab moves through buttons, Enter/Space activates
	it('supports keyboard Tab navigation across all preset buttons', async () => {
		const onChange = vi.fn();
		const user = userEvent.setup();

		render(<DateRangePicker value="7d" onChange={onChange} />);

		const buttons = screen.getAllByRole('button');

		// Tab into the first button
		await user.tab();
		expect(buttons[0]).toHaveFocus();

		// Tab through remaining buttons
		await user.tab();
		expect(buttons[1]).toHaveFocus();

		await user.tab();
		expect(buttons[2]).toHaveFocus();

		await user.tab();
		expect(buttons[3]).toHaveFocus();

		await user.tab();
		expect(buttons[4]).toHaveFocus();
	});

	it('activates a preset button via Enter key', async () => {
		const onChange = vi.fn();
		const user = userEvent.setup();

		render(<DateRangePicker value="7d" onChange={onChange} />);

		// Tab to first button (Today) and press Enter
		await user.tab();
		await user.keyboard('{Enter}');

		expect(onChange).toHaveBeenCalledWith('today');
	});

	it('activates a preset button via Space key', async () => {
		const onChange = vi.fn();
		const user = userEvent.setup();

		render(<DateRangePicker value="7d" onChange={onChange} />);

		// Tab to first button (Today) and press Space
		await user.tab();
		await user.keyboard(' ');

		expect(onChange).toHaveBeenCalledWith('today');
	});

	// Inactive buttons have muted styling
	it('applies muted-foreground text styling to inactive preset buttons', () => {
		const onChange = vi.fn();

		render(<DateRangePicker value="today" onChange={onChange} />);

		const inactiveButton = screen.getByText('7 Days');
		expect(inactiveButton.className).toContain('text-muted-foreground');
	});
});
