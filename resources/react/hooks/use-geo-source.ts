import { useQuery } from '@tanstack/react-query';
import { apiGet } from '@/lib/api-client';

export type GeoSource = 'maxmind' | 'cdn_headers' | 'timezone' | 'none';

interface DiagnosticsSnapshot {
	geoip?: {
		source_detected?: GeoSource;
		cdn_header_present?: boolean;
	};
}

export function useGeoSource(): GeoSource {
	const { data } = useQuery({
		queryKey: ['diagnostics'],
		queryFn: () => apiGet<DiagnosticsSnapshot>('diagnostics'),
		staleTime: 5 * 60 * 1000,
		retry: false,
	});
	// While diagnostics is loading we cannot know which tier is active —
	// fall back to 'none' so the dashboard does not pre-commit to copy that
	// would mislead users on a MaxMind-configured host.
	return data?.geoip?.source_detected ?? 'none';
}
