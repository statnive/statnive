import { useMemo } from 'react';
import { useDateRange } from '@/hooks/use-date-range';
import { useDimensions } from '@/hooks/use-dimensions';
import { DateRangePicker } from '@/components/shared/date-range-picker';
import { DataTable, type Column } from '@/components/shared/data-table';
import { formatNumber } from '@/lib/utils';
import type { DimensionRow } from '@/types/api';

export function GeographyPage() {
	const { range, params, setDateRange } = useDateRange();
	const { data: countries, isLoading: loadingCountries } = useDimensions('countries', params.from, params.to, 30);
	const { data: cities, isLoading: loadingCities } = useDimensions('cities', params.from, params.to, 30);

	const countryColumns: Column<DimensionRow>[] = useMemo(
		() => [
			{ key: 'name', header: 'Country', render: (row) => <span className="font-medium">{row.code ? `${row.code} — ` : ''}{row.name ?? '—'}</span> },
			{ key: 'visitors', header: 'Visitors', sortable: true, align: 'right' as const, render: (row) => <span className="tabular-nums">{formatNumber(row.visitors)}</span> },
			{ key: 'sessions', header: 'Sessions', sortable: true, align: 'right' as const, render: (row) => <span className="tabular-nums">{formatNumber(row.sessions)}</span> },
		],
		[],
	);

	const cityColumns: Column<DimensionRow>[] = useMemo(
		() => [
			{ key: 'city_name', header: 'City', render: (row) => <span className="font-medium">{row.city_name ?? '—'}</span> },
			{ key: 'country', header: 'Country', render: (row) => <span className="text-muted-foreground">{row.country ?? '—'}</span> },
			{ key: 'visitors', header: 'Visitors', sortable: true, align: 'right' as const, render: (row) => <span className="tabular-nums">{formatNumber(row.visitors)}</span> },
		],
		[],
	);

	return (
		<div className="space-y-6">
			<div className="flex items-center justify-between">
				<h2 className="text-lg font-semibold">Geography</h2>
				<DateRangePicker value={range} onChange={setDateRange} />
			</div>

			<div className="grid grid-cols-1 gap-6 md:grid-cols-2">
				<div className="rounded-lg border border-border bg-card p-4">
					<DataTable title="Countries" data={countries ?? []} columns={countryColumns} isLoading={loadingCountries} defaultSortKey="visitors" getRowKey={(row) => row.code ?? row.name ?? ''} />
				</div>
				<div className="rounded-lg border border-border bg-card p-4">
					<DataTable title="Cities" data={cities ?? []} columns={cityColumns} isLoading={loadingCities} defaultSortKey="visitors" getRowKey={(row, i) => `${row.city_name}-${i}`} />
				</div>
			</div>
		</div>
	);
}
