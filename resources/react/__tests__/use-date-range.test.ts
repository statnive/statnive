import { describe, it, expect } from 'vitest';
import { renderHook, act } from '@testing-library/react';
import { useDateRange } from '@/hooks/use-date-range';

describe('useDateRange', () => {
	it('defaults to 7d range', () => {
		const { result } = renderHook(() => useDateRange());
		expect(result.current.range).toBe('7d');
	});

	it('provides from and to date strings', () => {
		const { result } = renderHook(() => useDateRange());
		expect(result.current.params.from).toMatch(/^\d{4}-\d{2}-\d{2}$/);
		expect(result.current.params.to).toMatch(/^\d{4}-\d{2}-\d{2}$/);
	});

	it('provides previous period params', () => {
		const { result } = renderHook(() => useDateRange());
		expect(result.current.previousParams.from).toMatch(/^\d{4}-\d{2}-\d{2}$/);
		expect(result.current.previousParams.to).toMatch(/^\d{4}-\d{2}-\d{2}$/);
		// Previous period should end before current period starts.
		expect(result.current.previousParams.to < result.current.params.from).toBe(true);
	});

	it('updates range on setDateRange', () => {
		const { result } = renderHook(() => useDateRange());

		act(() => {
			result.current.setDateRange('30d');
		});

		expect(result.current.range).toBe('30d');
	});

	it('today range has same from and to', () => {
		const { result } = renderHook(() => useDateRange());

		act(() => {
			result.current.setDateRange('today');
		});

		expect(result.current.params.from).toBe(result.current.params.to);
	});
});
