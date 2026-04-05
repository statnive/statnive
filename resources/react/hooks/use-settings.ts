import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { apiGet, apiPut } from '@/lib/api-client';
import type { SettingsState } from '@/types/api';

export function useSettings() {
	return useQuery({
		queryKey: ['settings'],
		queryFn: () => apiGet<SettingsState>('settings'),
	});
}

export function useUpdateSettings() {
	const queryClient = useQueryClient();

	return useMutation({
		mutationFn: (data: Partial<SettingsState>) => apiPut<SettingsState>('settings', data),
		onMutate: async (newData) => {
			await queryClient.cancelQueries({ queryKey: ['settings'] });
			const previous = queryClient.getQueryData<SettingsState>(['settings']);

			if (previous) {
				queryClient.setQueryData<SettingsState>(['settings'], {
					...previous,
					...newData,
				});
			}

			return { previous };
		},
		onError: (_err, _vars, context) => {
			if (context?.previous) {
				queryClient.setQueryData(['settings'], context.previous);
			}
		},
		onSettled: () => {
			void queryClient.invalidateQueries({ queryKey: ['settings'] });
		},
	});
}
