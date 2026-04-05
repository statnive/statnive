import { useQuery } from '@tanstack/react-query';
import { apiGet } from '@/lib/api-client';
import type { PageRow } from '@/types/api';

export function usePages(from: string, to: string, limit = 20, offset = 0) {
	return useQuery({
		queryKey: ['pages', from, to, limit, offset],
		queryFn: () =>
			apiGet<PageRow[]>('pages', {
				from,
				to,
				limit: String(limit),
				offset: String(offset),
			}),
	});
}
