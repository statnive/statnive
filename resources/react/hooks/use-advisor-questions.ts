import { useQuery } from '@tanstack/react-query';
import { apiGet } from '@/lib/api-client';
import type { AdvisorQuestionsResponse } from '@/types/api';

/**
 * Fetch the full Ask me! question inventory (120 entries + 10 categories).
 *
 * The response is locale-keyed on the server, so we include the active locale
 * in the query key — switching site language refetches the translated payload.
 * staleTime 60 minutes: the inventory rarely changes within a session.
 */
export function useAdvisorQuestions() {
	const locale =
		typeof document !== 'undefined' ? document.documentElement.lang || 'en' : 'en';

	return useQuery({
		queryKey: ['advisor', 'questions', locale],
		queryFn: () => apiGet<AdvisorQuestionsResponse>('advisor/questions'),
		staleTime: 60 * 60 * 1000,
	});
}
