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
		onSettled: () => {
			void queryClient.invalidateQueries({ queryKey: ['settings'] });
		},
	});
}
