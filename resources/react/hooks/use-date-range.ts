import { useMemo, useCallback } from 'react';
import { useSearch, useNavigate } from '@tanstack/react-router';
import type { DateRange } from '@/types/api';

interface DateRangeParams {
	from: string;
	to: string;
}

function toDateString(date: Date): string {
	return date.toISOString().split('T')[0]!;
}

function resolveRange(range: DateRange): DateRangeParams {
	const now = new Date();
	const today = toDateString(now);

	switch (range) {
		case 'today':
			return { from: today, to: today };
		case '7d': {
			const from = new Date(now);
			from.setDate(from.getDate() - 6);
			return { from: toDateString(from), to: today };
		}
		case '30d': {
			const from = new Date(now);
			from.setDate(from.getDate() - 29);
			return { from: toDateString(from), to: today };
		}
		case 'this-month': {
			const from = new Date(now.getFullYear(), now.getMonth(), 1);
			return { from: toDateString(from), to: today };
		}
		case 'last-month': {
			const from = new Date(now.getFullYear(), now.getMonth() - 1, 1);
			const to = new Date(now.getFullYear(), now.getMonth(), 0);
			return { from: toDateString(from), to: toDateString(to) };
		}
		default:
			return { from: today, to: today };
	}
}

function resolvePreviousRange(params: DateRangeParams): DateRangeParams {
	const from = new Date(params.from);
	const to = new Date(params.to);
	const diffMs = to.getTime() - from.getTime();
	const diffDays = Math.ceil(diffMs / (1000 * 60 * 60 * 24));

	const prevTo = new Date(from);
	prevTo.setDate(prevTo.getDate() - 1);
	const prevFrom = new Date(prevTo);
	prevFrom.setDate(prevFrom.getDate() - diffDays);

	return { from: toDateString(prevFrom), to: toDateString(prevTo) };
}

export function useDateRange() {
	const { range } = useSearch({ from: '__root__' });
	const navigate = useNavigate();

	const params = useMemo(() => resolveRange(range), [range]);
	const previousParams = useMemo(() => resolvePreviousRange(params), [params]);

	const setDateRange = useCallback(
		(newRange: DateRange) => {
			navigate({ search: (prev) => ({ ...prev, range: newRange }) });
		},
		[navigate],
	);

	return { range, params, previousParams, setDateRange };
}
