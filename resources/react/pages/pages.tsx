import { useMemo, useDeferredValue, useState } from 'react';
import { __ } from '@wordpress/i18n';
import { useDateRange } from '@/hooks/use-date-range';
import { usePages } from '@/hooks/use-pages';
import { useEntryPages, useExitPages } from '@/hooks/use-entry-exit-pages';
import { DataTable, type Column } from '@/components/shared/data-table';
import { DualBarCell } from '@/components/shared/dual-bar-cell';
import { formatDuration } from '@/lib/utils';
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

	const maxPageViews = useMemo(
		() => Math.max(...(pages ?? []).map(p => Math.max(p.visitors, p.views)), 1),
		[pages],
	);
	const maxEntry = useMemo(
		() => Math.max(...(entry ?? []).map(p => Math.max(p.count, p.visitors)), 1),
		[entry],
	);
	const maxExit = useMemo(
		() => Math.max(...(exit ?? []).map(p => Math.max(p.count, p.visitors)), 1),
		[exit],
	);

	const pageColumns: Column<PageRow>[] = useMemo(
		() => [
			{
				key: 'uri',
				header: __('Page', 'statnive'),
				render: (row) => (
					<div className="max-w-[300px] truncate" title={row.uri}>
						<span className="font-medium">{row.title ?? row.uri}</span>
						<span className="ml-2 text-xs text-muted-foreground">{row.uri}</span>
					</div>
				),
			},
			{ key: 'visitors', header: __('Visitors / Views', 'statnive'), sortable: true, render: (row) => <DualBarCell visitors={row.visitors} secondaryValue={row.views} max={maxPageViews} /> },
			{ key: 'total_duration', header: __('Avg Duration', 'statnive'), align: 'right' as const, render: (row) => <span className="tabular-nums">{row.visitors > 0 ? formatDuration(row.total_duration / row.visitors) : '—'}</span> },
		],
		[maxPageViews],
	);

	const makeEntryExitColumns = (header: string, max: number): Column<EntryExitPage>[] => [
		{ key: 'uri', header: __('Page', 'statnive'), render: (row) => <span className="font-medium" title={row.uri}>{row.title ?? row.uri}</span> },
		{ key: 'count', header, sortable: true, render: (row) => <DualBarCell visitors={row.count} secondaryValue={row.visitors} max={max} /> },
	];

	const entryColumns = useMemo(() => makeEntryExitColumns(__('Entries / Visitors', 'statnive'), maxEntry), [maxEntry]);
	const exitColumns = useMemo(() => makeEntryExitColumns(__('Exits / Visitors', 'statnive'), maxExit), [maxExit]);

	const emptyPageMessage = __('No page data for this period. If your site has traffic, data should appear within minutes. If nothing shows after 10 minutes, check Settings → Diagnostics.', 'statnive');

	return (
		<div className="space-y-6">
			<h2 className="text-lg font-semibold">{__('Pages', 'statnive')}</h2>

			{/* Search */}
			<div className="relative max-w-sm">
				<Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
				<input
					type="text"
					placeholder={__('Search pages...', 'statnive')}
					value={search}
					onChange={(e) => setSearch(e.target.value)}
					className="w-full rounded-md border border-border bg-card !py-[3px] !pl-[30px] !pr-[10px] text-sm placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary"
				/>
			</div>

			{/* Top Content */}
			<div className="rounded-lg border border-border bg-card p-4">
				<DataTable
					title={__('Top Content', 'statnive')}
					data={filteredPages}
					columns={pageColumns}
					isLoading={loadingPages}
					defaultSortKey="visitors"
					getRowKey={(row) => row.uri}
					emptyMessage={emptyPageMessage}
				/>
			</div>

			{/* Entry/Exit Pages */}
			<div className="grid grid-cols-1 gap-6 md:grid-cols-2">
				<div className="rounded-lg border border-border bg-card p-4">
					<DataTable
						title={__('Entry Pages', 'statnive')}
						data={filteredEntry}
						columns={entryColumns}
						isLoading={loadingEntry}
						defaultSortKey="count"
						getRowKey={(row) => `entry-${row.uri}`}
						emptyMessage={emptyPageMessage}
					/>
				</div>
				<div className="rounded-lg border border-border bg-card p-4">
					<DataTable
						title={__('Exit Pages', 'statnive')}
						data={filteredExit}
						columns={exitColumns}
						isLoading={loadingExit}
						defaultSortKey="count"
						getRowKey={(row) => `exit-${row.uri}`}
						emptyMessage={emptyPageMessage}
					/>
				</div>
			</div>
		</div>
	);
}
