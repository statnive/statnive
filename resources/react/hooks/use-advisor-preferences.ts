import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { apiGet, apiPut } from '@/lib/api-client';
import type { AdvisorPreferencesResponse } from '@/types/api';

const QUERY_KEY = ['advisor', 'preferences'] as const;

/**
 * Per-WP-user pinned-question preferences.
 *
 * - GET returns the user's current pinned set OR the 5 default-pins from
 *   research #71 §6 if they have no entry yet.
 * - The mutation hooks (`pin`, `unpin`, `setPinned`) all do an optimistic
 *   in-cache update first, then PUT the server, then refetch on settled.
 *   Rolling back on error so failed network requests don't permanently
 *   diverge the UI from the server state.
 */
export function useAdvisorPreferences() {
	return useQuery({
		queryKey: QUERY_KEY,
		queryFn: () => apiGet<AdvisorPreferencesResponse>('advisor/preferences'),
		staleTime: 5 * 60 * 1000,
	});
}

export function useAdvisorPinMutations() {
	const queryClient = useQueryClient();

	const writePinned = useMutation({
		mutationFn: (next: string[]) =>
			apiPut<AdvisorPreferencesResponse>('advisor/preferences', {
				pinned_questions: next,
			}),
		onMutate: async (next) => {
			await queryClient.cancelQueries({ queryKey: QUERY_KEY });
			const previous = queryClient.getQueryData<AdvisorPreferencesResponse>(QUERY_KEY);
			queryClient.setQueryData<AdvisorPreferencesResponse>(QUERY_KEY, (old) => ({
				pinned_questions: next,
				max_pins: old?.max_pins ?? 5,
				defaults: old?.defaults,
			}));
			return { previous };
		},
		onError: (_err, _next, ctx) => {
			if (ctx?.previous) {
				queryClient.setQueryData(QUERY_KEY, ctx.previous);
			}
		},
		onSettled: () => queryClient.invalidateQueries({ queryKey: QUERY_KEY }),
	});

	const pin = (id: string) => {
		const current = queryClient.getQueryData<AdvisorPreferencesResponse>(QUERY_KEY);
		const existing = current?.pinned_questions ?? [];
		const max = current?.max_pins ?? 5;
		if (existing.includes(id)) return;
		if (existing.length >= max) return; // Cap.
		writePinned.mutate([...existing, id]);
	};

	const unpin = (id: string) => {
		const current = queryClient.getQueryData<AdvisorPreferencesResponse>(QUERY_KEY);
		const existing = current?.pinned_questions ?? [];
		writePinned.mutate(existing.filter((x) => x !== id));
	};

	const setPinned = (next: string[]) => writePinned.mutate(next);

	return { pin, unpin, setPinned, isPending: writePinned.isPending };
}
