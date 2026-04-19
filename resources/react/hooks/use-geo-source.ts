import { useQuery } from '@tanstack/react-query';
import { apiGet } from '@/lib/api-client';

export type GeoSource = 'maxmind' | 'cdn_headers' | 'none';

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
	return data?.geoip?.source_detected ?? 'none';
}
