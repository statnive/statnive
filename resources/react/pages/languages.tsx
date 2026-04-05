import { useMemo } from 'react';
import { useDateRange } from '@/hooks/use-date-range';
import { useDimensions } from '@/hooks/use-dimensions';
import { DateRangePicker } from '@/components/shared/date-range-picker';
import { DataTable, type Column } from '@/components/shared/data-table';
import { formatNumber } from '@/lib/utils';
import type { DimensionRow } from '@/types/api';

export function LanguagesPage() {
	const { range, params, setDateRange } = useDateRange();
	const { data: languages, isLoading } = useDimensions('languages', params.from, params.to, 30);

	const columns: Column<DimensionRow>[] = useMemo(
		() => [
			{ key: 'name', header: 'Language', render: (row) => <span className="font-medium">{row.name ?? '—'}</span> },
			{ key: 'visitors', header: 'Visitors', sortable: true, align: 'right' as const, render: (row) => <span className="tabular-nums">{formatNumber(row.visitors)}</span> },
			{ key: 'sessions', header: 'Sessions', sortable: true, align: 'right' as const, render: (row) => <span className="tabular-nums">{formatNumber(row.sessions)}</span> },
		],
		[],
	);

	return (
		<div className="space-y-6">
			<div className="flex items-center justify-between">
				<h2 className="text-lg font-semibold">Languages</h2>
				<DateRangePicker value={range} onChange={setDateRange} />
			</div>
			<div className="rounded-lg border border-border bg-card p-4">
				<DataTable title="Visitor Languages" data={languages ?? []} columns={columns} isLoading={isLoading} defaultSortKey="visitors" getRowKey={(row) => row.name ?? ''} />
			</div>
		</div>
	);
}
