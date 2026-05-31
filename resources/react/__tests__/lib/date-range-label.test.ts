import { describe, it, expect } from 'vitest';
import { formatDateRangeLabel, formatPriorRangeLabel } from '@/lib/date-range-label';

const params = { from: '2026-05-25', to: '2026-05-31' };

describe('formatDateRangeLabel', () => {
	it('returns the right phrase for each preset', () => {
		expect(formatDateRangeLabel('today', params)).toBe('today');
		expect(formatDateRangeLabel('7d', params)).toBe('in the last 7 days');
		expect(formatDateRangeLabel('30d', params)).toBe('in the last 30 days');
		expect(formatDateRangeLabel('this-month', params)).toBe('this month');
		expect(formatDateRangeLabel('last-month', params)).toBe('last month');
	});

	it('falls back to Intl-formatted date range for custom', () => {
		const label = formatDateRangeLabel('custom', { from: '2026-05-28', to: '2026-05-31' });
		// Locale-dependent exact format; just assert both endpoints + the dash separator.
		expect(label).toMatch(/2026/);
		expect(label).toContain('–');
		expect(label.split('–').length).toBe(2);
	});
});

describe('formatPriorRangeLabel', () => {
	it('returns the right comparison phrase for each preset', () => {
		expect(formatPriorRangeLabel('today', params)).toBe('with yesterday');
		expect(formatPriorRangeLabel('7d', params)).toBe('with the previous 7 days');
		expect(formatPriorRangeLabel('30d', params)).toBe('with the previous 30 days');
		expect(formatPriorRangeLabel('this-month', params)).toBe('with last month');
		expect(formatPriorRangeLabel('last-month', params)).toBe('with the month before');
	});

	it('computes day-count for custom range', () => {
		expect(formatPriorRangeLabel('custom', { from: '2026-05-28', to: '2026-05-31' })).toBe(
			'with the previous 4 days',
		);
	});
});
