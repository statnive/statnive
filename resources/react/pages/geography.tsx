import { useMemo } from 'react';
import { __ } from '@wordpress/i18n';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useDateRange } from '@/hooks/use-date-range';
import { useDimensions } from '@/hooks/use-dimensions';
import { useGeoSource, useDbipCityActive, DIAGNOSTICS_QUERY_KEY, type GeoSource } from '@/hooks/use-geo-source';
import { apiPost } from '@/lib/api-client';
import { DataTable, type Column } from '@/components/shared/data-table';
import { DualBarCell } from '@/components/shared/dual-bar-cell';
import { HEADING_H2 } from '@/lib/typography';
import type { DimensionRow } from '@/types/api';

export function GeographyPage() {
	const { params } = useDateRange();
	const { data: countries, isLoading: loadingCountries } = useDimensions('countries', params.from, params.to, 30);
	const { data: cities, isLoading: loadingCities } = useDimensions('cities', params.from, params.to, 30);
	const geoSource = useGeoSource();
	const dbipCityActive = useDbipCityActive();
	const queryClient = useQueryClient();
	const enableDbip = useMutation({
		mutationFn: () => apiPost<{ status: string; pending: boolean; database_present: boolean }>('diagnostics/enable-dbip-city'),
		onSuccess: () => {
			void queryClient.invalidateQueries({ queryKey: DIAGNOSTICS_QUERY_KEY });
		},
	});

	const maxCountry = useMemo(
		() => Math.max(...(countries ?? []).map(d => Math.max(d.visitors, d.sessions)), 1),
		[countries],
	);
	const maxCity = useMemo(
		() => Math.max(...(cities ?? []).map(d => Math.max(d.visitors, d.sessions)), 1),
		[cities],
	);

	const countryColumns: Column<DimensionRow>[] = useMemo(
		() => [
			{ key: 'name', header: __('Country', 'statnive'), render: (row) => <span className="font-medium">{row.code ? `${row.code} — ` : ''}{row.name ?? '—'}</span> },
			{ key: 'visitors', header: __('Visitors / Sessions', 'statnive'), sortable: true, render: (row) => <DualBarCell visitors={row.visitors} secondaryValue={row.sessions} max={maxCountry} /> },
		],
		[maxCountry],
	);

	const cityColumns: Column<DimensionRow>[] = useMemo(
		() => [
			{ key: 'city_name', header: __('City', 'statnive'), render: (row) => <span className="font-medium">{row.city_name ?? '—'}</span> },
			{ key: 'country', header: __('Country', 'statnive'), render: (row) => <span className="text-muted-foreground">{row.country ?? '—'}</span> },
			{ key: 'visitors', header: __('Visitors / Sessions', 'statnive'), sortable: true, render: (row) => <DualBarCell visitors={row.visitors} secondaryValue={row.sessions} max={maxCity} /> },
		],
		[maxCity],
	);

	const emptyGeoMessages: Record<GeoSource, string> = {
		maxmind: __('No geography data for this period. If your site has traffic, data should appear within minutes. If nothing shows after 10 minutes, check Settings → Diagnostics.', 'statnive'),
		dbip_city: __('No geography data for this period. The free DB-IP city database is active; data will appear as traffic arrives.', 'statnive'),
		cdn_headers: __('No visitors with a resolvable country in this period. Country detection via your CDN is active; data will appear as traffic arrives.', 'statnive'),
		timezone: __('No visitors with a resolvable country in this period. Approximate country is being derived from each visitor’s browser timezone; for precise city-level data, enable DB-IP below or configure MaxMind GeoIP in Settings → GeoIP.', 'statnive'),
		none: __('Geography resolution is currently disabled. Re-enable the timezone fallback, configure MaxMind GeoIP, or place your site behind a CDN that sets a country header.', 'statnive'),
	};
	const emptyGeoMessage = emptyGeoMessages[geoSource];

	// Show the DB-IP CTA only when no city-level provider is active yet.
	const showDbipCta = !dbipCityActive && (geoSource === 'cdn_headers' || geoSource === 'timezone' || geoSource === 'none');

	return (
		<div className="space-y-6">
			<h2 className={HEADING_H2}>{__('Geography', 'statnive')}</h2>

			{showDbipCta && (
				<div className="rounded-lg border border-border bg-muted/40 p-4">
					<div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
						<div className="space-y-1">
							<p className="font-medium">{__('Want city-level data?', 'statnive')}</p>
							<p className="text-sm text-muted-foreground">
								{__('Enable the free DB-IP city database. No account, no key — one click. ~80 MB downloads to your uploads directory. CC-BY-4.0.', 'statnive')}
							</p>
						</div>
						<button
							type="button"
							className="inline-flex h-9 items-center justify-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-60"
							onClick={() => enableDbip.mutate()}
							disabled={enableDbip.isPending}
						>
							{enableDbip.isPending
								? __('Enabling…', 'statnive')
								: __('Enable city-level geography', 'statnive')}
						</button>
					</div>
					{enableDbip.isError && (
						<p className="mt-2 text-sm text-destructive">
							{__('Could not enable DB-IP. Try again, or configure MaxMind in Settings → GeoIP.', 'statnive')}
						</p>
					)}
				</div>
			)}

			<div className="grid grid-cols-1 gap-6 md:grid-cols-2">
				<div className="rounded-lg border border-border bg-card p-4">
					<DataTable title={__('Countries', 'statnive')} data={countries ?? []} columns={countryColumns} isLoading={loadingCountries} defaultSortKey="visitors" getRowKey={(row) => row.code ?? row.name ?? ''} emptyMessage={emptyGeoMessage} />
				</div>
				<div className="rounded-lg border border-border bg-card p-4">
					<DataTable title={__('Cities', 'statnive')} data={cities ?? []} columns={cityColumns} isLoading={loadingCities} defaultSortKey="visitors" getRowKey={(row, i) => `${row.city_name}-${i}`} emptyMessage={emptyGeoMessage} />
				</div>
			</div>

			{geoSource === 'dbip_city' && (
				<p className="text-xs text-muted-foreground">
					{__('GeoIP data © DB-IP under CC-BY 4.0', 'statnive')}
				</p>
			)}
		</div>
	);
}
