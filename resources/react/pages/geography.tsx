import { useMemo } from 'react';
import { __ } from '@wordpress/i18n';
import { useDateRange } from '@/hooks/use-date-range';
import { useDimensions } from '@/hooks/use-dimensions';
import { useGeoSource } from '@/hooks/use-geo-source';
import { DataTable, type Column } from '@/components/shared/data-table';
import { DualBarCell } from '@/components/shared/dual-bar-cell';
import type { DimensionRow } from '@/types/api';

export function GeographyPage() {
	const { params } = useDateRange();
	const { data: countries, isLoading: loadingCountries } = useDimensions('countries', params.from, params.to, 30);
	const { data: cities, isLoading: loadingCities } = useDimensions('cities', params.from, params.to, 30);
	const geoSource = useGeoSource();

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

	const emptyGeoMessage = geoSource === 'none'
		? __('Geography needs an approximate-country source. Put your site behind Cloudflare, AWS CloudFront, or Vercel (free tiers set a country header automatically), or configure MaxMind GeoIP in Settings → GeoIP.', 'statnive')
		: geoSource === 'cdn_headers'
			? __('No visitors with a resolvable country in this period. Country detection via your CDN is active; data will appear as traffic arrives.', 'statnive')
			: __('No geography data for this period. If your site has traffic, data should appear within minutes. If nothing shows after 10 minutes, check Settings → Diagnostics.', 'statnive');

	return (
		<div className="space-y-6">
			<h2 className="text-lg font-semibold">{__('Geography', 'statnive')}</h2>

			<div className="grid grid-cols-1 gap-6 md:grid-cols-2">
				<div className="rounded-lg border border-border bg-card p-4">
					<DataTable title={__('Countries', 'statnive')} data={countries ?? []} columns={countryColumns} isLoading={loadingCountries} defaultSortKey="visitors" getRowKey={(row) => row.code ?? row.name ?? ''} emptyMessage={emptyGeoMessage} />
				</div>
				<div className="rounded-lg border border-border bg-card p-4">
					<DataTable title={__('Cities', 'statnive')} data={cities ?? []} columns={cityColumns} isLoading={loadingCities} defaultSortKey="visitors" getRowKey={(row, i) => `${row.city_name}-${i}`} emptyMessage={emptyGeoMessage} />
				</div>
			</div>
		</div>
	);
}
