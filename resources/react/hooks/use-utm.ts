import { useQuery } from '@tanstack/react-query';
import { apiGet } from '@/lib/api-client';
import type { UtmRow } from '@/types/api';

export function useUtm(from: string, to: string, limit = 20) {
	return useQuery({
		queryKey: ['utm', from, to, limit],
		queryFn: () =>
			apiGet<UtmRow[]>('utm', { from, to, limit: String(limit) }),
	});
}
