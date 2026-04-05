import { useQuery } from '@tanstack/react-query';
import { apiGet } from '@/lib/api-client';
import type { SummaryResponse } from '@/types/api';

export function useSummary(from: string, to: string) {
	return useQuery({
		queryKey: ['summary', from, to],
		queryFn: () => apiGet<SummaryResponse>('summary', { from, to }),
	});
}
