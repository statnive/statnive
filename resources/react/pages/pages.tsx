import { useMemo, useDeferredValue, useState } from 'react';
import { useDateRange } from '@/hooks/use-date-range';
import { usePages } from '@/hooks/use-pages';
import { useEntryPages, useExitPages } from '@/hooks/use-entry-exit-pages';
import { DataTable, type Column } from '@/components/shared/data-table';
import { formatNumber, formatDuration } from '@/lib/utils';
import type { PageRow, EntryExitPage } from '@/types/api';
import { Search } from 'lucide-react';

const EMPTY: never[] = [];

function filterByQuery<T extends { uri: string; title: string | null }>(
	rows: T[] | undefined,
	q: string,
): T[] {
	const src = rows ?? EMPTY;
	if (!q) return src;
	return src.filter(
		(r) => r.uri.toLowerCase().includes(q) || (r.title ?? '').toLowerCase().includes(q),
	);
}

export function PagesPage() {
	const { params } = useDateRange();
	const { data: pages, isLoading: loadingPages } = usePages(params.from, params.to, 50);
	const { data: entry, isLoading: loadingEntry } = useEntryPages(params.from, params.to);
	const { data: exit, isLoading: loadingExit } = useExitPages(params.from, params.to);

	const [search, setSearch] = useState('');
	const deferredSearch = useDeferredValue(search);

	const q = useMemo(() => deferredSearch.trim().toLowerCase(), [deferredSearch]);
	const filteredPages = useMemo(() => filterByQuery(pages, q), [pages, q]);
	const filteredEntry = useMemo(() => filterByQuery(entry, q), [entry, q]);
	const filteredExit = useMemo(() => filterByQuery(exit, q), [exit, q]);

	const pageColumns: Column<PageRow>[] = useMemo(
		() => [
			{
				key: 'uri',
				header: 'Page',
				render: (row) => (
					<div className="max-w-[300px] truncate" title={row.uri}>
						<span className="font-medium">{row.title ?? row.uri}</span>
						<span className="ml-2 text-xs text-muted-foreground">{row.uri}</span>
					</div>
				),
			},
			{ key: 'visitors', header: 'Visitors', sortable: true, align: 'right' as const, render: (row) => <span className="tabular-nums">{formatNumber(row.visitors)}</span> },
			{ key: 'views', header: 'Views', sortable: true, align: 'right' as const, render: (row) => <span className="tabular-nums">{formatNumber(row.views)}</span> },
			{ key: 'total_duration', header: 'Avg Duration', align: 'right' as const, render: (row) => <span className="tabular-nums">{row.visitors > 0 ? formatDuration(row.total_duration / row.visitors) : '—'}</span> },
		],
		[],
	);

	const entryColumns: Column<EntryExitPage>[] = useMemo(
		() => [
			{ key: 'uri', header: 'Page', render: (row) => <span className="font-medium" title={row.uri}>{row.title ?? row.uri}</span> },
			{ key: 'count', header: 'Entries', sortable: true, align: 'right' as const, render: (row) => <span className="tabular-nums">{formatNumber(row.count)}</span> },
			{ key: 'visitors', header: 'Visitors', sortable: true, align: 'right' as const, render: (row) => <span className="tabular-nums">{formatNumber(row.visitors)}</span> },
		],
		[],
	);

	return (
		<div className="space-y-6">
			<h2 className="text-lg font-semibold">Pages</h2>

			{/* Search */}
			<div className="relative max-w-sm">
				<Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
				<input
					type="text"
					placeholder="Search pages..."
					value={search}
					onChange={(e) => setSearch(e.target.value)}
					className="w-full rounded-md border border-border bg-card !py-[3px] !pl-[30px] !pr-[10px] text-sm placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary"
				/>
			</div>

			{/* Top Content */}
			<div className="rounded-lg border border-border bg-card p-4">
				<DataTable
					title="Top Content"
					data={filteredPages}
					columns={pageColumns}
					isLoading={loadingPages}
					defaultSortKey="visitors"
					getRowKey={(row) => row.uri}
				/>
			</div>

			{/* Entry/Exit Pages */}
			<div className="grid grid-cols-1 gap-6 md:grid-cols-2">
				<div className="rounded-lg border border-border bg-card p-4">
					<DataTable
						title="Entry Pages"
						data={filteredEntry}
						columns={entryColumns}
						isLoading={loadingEntry}
						defaultSortKey="count"
						getRowKey={(row) => `entry-${row.uri}`}
					/>
				</div>
				<div className="rounded-lg border border-border bg-card p-4">
					<DataTable
						title="Exit Pages"
						data={filteredExit}
						columns={entryColumns}
						isLoading={loadingExit}
						defaultSortKey="count"
						getRowKey={(row) => `exit-${row.uri}`}
					/>
				</div>
			</div>
		</div>
	);
}
