import { useQuery } from '@tanstack/react-query';
import { apiGet } from '@/lib/api-client';
import type { ChannelGroup, SourceRow } from '@/types/api';

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

export function useGroupedSources(from: string, to: string, perChannel = 10) {
	return useQuery({
		queryKey: ['sources', 'grouped', from, to, perChannel],
		queryFn: () =>
			apiGet<ChannelGroup[]>('sources', {
				from,
				to,
				group_by: 'channel',
				per_channel: String(perChannel),
			}),
	});
}
