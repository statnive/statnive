import { useQuery } from '@tanstack/react-query';
import { apiGet } from '@/lib/api-client';
import type { RealtimeResponse } from '@/types/api';

export function useRealtime() {
	return useQuery({
		queryKey: ['realtime'],
		queryFn: () => apiGet<RealtimeResponse>('realtime'),
		refetchInterval: 5000,
		staleTime: 4000,
	});
}
