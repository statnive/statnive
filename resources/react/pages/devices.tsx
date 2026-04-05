import { useMemo } from 'react';
import { useDateRange } from '@/hooks/use-date-range';
import { useDimensions } from '@/hooks/use-dimensions';
import { DateRangePicker } from '@/components/shared/date-range-picker';
import { DataTable, type Column } from '@/components/shared/data-table';
import { formatNumber } from '@/lib/utils';
import type { DimensionRow } from '@/types/api';

export function DevicesPage() {
	const { range, params, setDateRange } = useDateRange();
	const { data: deviceTypes } = useDimensions('devices', params.from, params.to);
	const { data: browsers, isLoading: loadingBrowsers } = useDimensions('browsers', params.from, params.to);
	const { data: oss, isLoading: loadingOs } = useDimensions('oss', params.from, params.to);

	const totalVisitors = useMemo(
		() => (deviceTypes ?? []).reduce((sum, d) => sum + Number(d.visitors), 0),
		[deviceTypes],
	);

	const dimColumns: Column<DimensionRow>[] = useMemo(
		() => [
			{ key: 'name', header: 'Name', render: (row) => <span className="font-medium">{row.name ?? '—'}</span> },
			{ key: 'visitors', header: 'Visitors', sortable: true, align: 'right' as const, render: (row) => <span className="tabular-nums">{formatNumber(row.visitors)}</span> },
			{ key: 'sessions', header: 'Sessions', sortable: true, align: 'right' as const, render: (row) => <span className="tabular-nums">{formatNumber(row.sessions)}</span> },
		],
		[],
	);

	return (
		<div className="space-y-6">
			<div className="flex items-center justify-between">
				<h2 className="text-lg font-semibold">Devices</h2>
				<DateRangePicker value={range} onChange={setDateRange} />
			</div>

			{/* Device Type Breakdown */}
			<div className="grid grid-cols-3 gap-4">
				{(deviceTypes ?? []).map((d) => {
					const pct = totalVisitors > 0 ? (d.visitors / totalVisitors) * 100 : 0;
					return (
						<div key={d.name} className="rounded-lg border border-border bg-card p-4">
							<p className="text-xs font-medium text-muted-foreground">{d.name}</p>
							<p className="mt-1 text-2xl font-bold tabular-nums">{pct.toFixed(1)}%</p>
							<div className="mt-2 h-2 w-full overflow-hidden rounded-full bg-muted">
								<div className="h-full rounded-full bg-primary transition-all duration-300" style={{ width: `${pct}%` }} />
							</div>
							<p className="mt-1 text-xs text-muted-foreground">{formatNumber(d.visitors)} visitors</p>
						</div>
					);
				})}
			</div>

			{/* Browsers + OS */}
			<div className="grid grid-cols-1 gap-6 md:grid-cols-2">
				<div className="rounded-lg border border-border bg-card p-4">
					<DataTable title="Browsers" data={browsers ?? []} columns={dimColumns} isLoading={loadingBrowsers} defaultSortKey="visitors" getRowKey={(row) => row.name ?? ''} />
				</div>
				<div className="rounded-lg border border-border bg-card p-4">
					<DataTable title="Operating Systems" data={oss ?? []} columns={dimColumns} isLoading={loadingOs} defaultSortKey="visitors" getRowKey={(row) => row.name ?? ''} />
				</div>
			</div>
		</div>
	);
}
