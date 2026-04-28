import { useQuery } from '@tanstack/react-query';
import { apiGet } from '@/lib/api-client';

export type GeoSource = 'maxmind' | 'dbip_city' | 'cdn_headers' | 'timezone' | 'none';

export interface DiagnosticsSnapshot {
	geoip?: {
		source_detected?: GeoSource;
		cdn_header_present?: boolean;
		dbip_city_active?: boolean;
	};
}

export const DIAGNOSTICS_QUERY_KEY = ['diagnostics'] as const;

function useDiagnostics() {
	return useQuery({
		queryKey: DIAGNOSTICS_QUERY_KEY,
		queryFn: () => apiGet<DiagnosticsSnapshot>('diagnostics'),
		staleTime: 5 * 60 * 1000,
		retry: false,
	});
}

export function useGeoSource(): GeoSource {
	const { data } = useDiagnostics();
	// While diagnostics is loading we cannot know which tier is active —
	// fall back to 'none' so the dashboard does not pre-commit to copy that
	// would mislead users on a MaxMind-configured host.
	return data?.geoip?.source_detected ?? 'none';
}

export function useDbipCityActive(): boolean {
	const { data } = useDiagnostics();
	return data?.geoip?.dbip_city_active ?? false;
}
