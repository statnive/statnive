import { useQuery } from '@tanstack/react-query';
import { apiGet } from '@/lib/api-client';
import type { DimensionRow } from '@/types/api';

export function useDimensions(type: string, from: string, to: string, limit = 20) {
	return useQuery({
		queryKey: ['dimensions', type, from, to, limit],
		queryFn: () =>
			apiGet<DimensionRow[]>(`dimensions/${type}`, {
				from,
				to,
				limit: String(limit),
			}),
	});
}
