import { useQuery, useQueryClient } from '@tanstack/react-query';
import { apiPost } from '@/lib/api-client';
import { useDateRange } from '@/hooks/use-date-range';
import type { AdvisorAnswer, AdvisorAnswersResponse } from '@/types/api';

/**
 * Lazy-fetch answers for a batch of Ask me! question IDs.
 *
 * - Pass `enabled=false` while the user has yet to expand any card to avoid
 *   firing the network call eagerly.
 * - The hook caches every individual answer under
 *   `['advisor','answer',id,from,to]` so subsequent expansions of the same
 *   question reuse the in-memory cache instead of refetching.
 * - The pinned tab batches all default-pin answers in a single POST by
 *   passing the full pinned-IDs array — answers are then individually
 *   primed into the per-ID cache via `setQueryData`.
 */
export function useAdvisorAnswers(ids: string[], enabled: boolean = true) {
	const { params } = useDateRange();
	const { from, to } = params;
	const queryClient = useQueryClient();
	const cacheKeyIds = [...ids].sort().join(',');

	return useQuery({
		enabled: enabled && ids.length > 0,
		queryKey: ['advisor', 'answers', cacheKeyIds, from, to],
		queryFn: async (): Promise<AdvisorAnswersResponse> => {
			const res = await apiPost<AdvisorAnswersResponse>('advisor/answers', {
				question_ids: ids,
				from,
				to,
			});
			for (const answer of res.answers) {
				queryClient.setQueryData<AdvisorAnswer>(
					['advisor', 'answer', answer.id, from, to],
					answer,
				);
			}
			return res;
		},
	});
}

/**
 * Read a single pre-cached answer (no network) for an already-batched
 * question ID. Used by individual QuestionCard components inside an
 * already-loaded PinnedTab so each card can render without re-querying.
 */
export function useCachedAdvisorAnswer(id: string): AdvisorAnswer | undefined {
	const { params } = useDateRange();
	const { from, to } = params;
	const queryClient = useQueryClient();
	return queryClient.getQueryData<AdvisorAnswer>(['advisor', 'answer', id, from, to]);
}

/**
 * Lazy-fetch a single answer (used when a category card is expanded by the
 * user — the pinned-batch may not have included this ID).
 */
export function useSingleAdvisorAnswer(id: string | null, enabled: boolean = true) {
	const { params } = useDateRange();
	const { from, to } = params;
	const queryClient = useQueryClient();

	return useQuery({
		enabled: enabled && id !== null,
		queryKey: ['advisor', 'answer', id, from, to],
		queryFn: async (): Promise<AdvisorAnswer | undefined> => {
			if (id === null) return undefined;
			const res = await apiPost<AdvisorAnswersResponse>('advisor/answers', {
				question_ids: [id],
				from,
				to,
			});
			const answer = res.answers[0];
			if (answer) {
				queryClient.setQueryData<AdvisorAnswer>(
					['advisor', 'answer', answer.id, from, to],
					answer,
				);
			}
			return answer;
		},
	});
}
