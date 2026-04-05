import { describe, it, expect } from 'vitest';
import { formatNumber, formatDuration, formatPercentChange, percentChange } from '@/lib/utils';

describe('formatNumber', () => {
	it('formats integers with commas', () => {
		expect(formatNumber(1234)).toBe('1,234');
		expect(formatNumber(1000000)).toBe('1,000,000');
	});

	it('handles zero', () => {
		expect(formatNumber(0)).toBe('0');
	});
});

describe('formatDuration', () => {
	it('formats seconds only', () => {
		expect(formatDuration(45)).toBe('45s');
	});

	it('formats minutes and seconds', () => {
		expect(formatDuration(125)).toBe('2m 5s');
	});

	it('formats exact minutes', () => {
		expect(formatDuration(120)).toBe('2m');
	});
});

describe('formatPercentChange', () => {
	it('shows up arrow for positive', () => {
		expect(formatPercentChange(12.5)).toBe('↑ 12.5%');
	});

	it('shows down arrow for negative', () => {
		expect(formatPercentChange(-5.3)).toBe('↓ 5.3%');
	});

	it('shows up arrow for zero', () => {
		expect(formatPercentChange(0)).toBe('↑ 0.0%');
	});
});

describe('percentChange', () => {
	it('calculates positive change', () => {
		expect(percentChange(150, 100)).toBe(50);
	});

	it('calculates negative change', () => {
		expect(percentChange(80, 100)).toBe(-20);
	});

	it('handles zero previous', () => {
		expect(percentChange(100, 0)).toBe(100);
		expect(percentChange(0, 0)).toBe(0);
	});
});
