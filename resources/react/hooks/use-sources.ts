import { useQuery } from '@tanstack/react-query';
import { apiGet } from '@/lib/api-client';
import type { SourceRow } from '@/types/api';

export function useSources(from: string, to: string, limit = 20) {
	return useQuery({
		queryKey: ['sources', from, to, limit],
		queryFn: () =>
			apiGet<SourceRow[]>('sources', {
				from,
				to,
				limit: String(limit),
			}),
	});
}
