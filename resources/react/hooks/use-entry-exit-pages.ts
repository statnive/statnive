import { useQuery } from '@tanstack/react-query';
import { apiGet } from '@/lib/api-client';
import type { EntryExitPage } from '@/types/api';

export function useEntryPages(from: string, to: string, limit = 10) {
	return useQuery({
		queryKey: ['pages-entry', from, to, limit],
		queryFn: () =>
			apiGet<EntryExitPage[]>('pages/entry', { from, to, limit: String(limit) }),
	});
}

export function useExitPages(from: string, to: string, limit = 10) {
	return useQuery({
		queryKey: ['pages-exit', from, to, limit],
		queryFn: () =>
			apiGet<EntryExitPage[]>('pages/exit', { from, to, limit: String(limit) }),
	});
}
