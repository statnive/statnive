import { useMemo } from 'react';
import { useDateRange } from '@/hooks/use-date-range';
import { useSummary } from '@/hooks/use-summary';
import { useSources } from '@/hooks/use-sources';
import { usePages } from '@/hooks/use-pages';
import { KpiCard } from '@/components/shared/kpi-card';
import { DualBarCell } from '@/components/shared/dual-bar-cell';
import { DataTable, type Column } from '@/components/shared/data-table';
import { TimeSeriesChart } from '@/components/charts/time-series-chart';
import { formatNumber, formatDuration, percentChange } from '@/lib/utils';
import type { SourceRow, PageRow } from '@/types/api';

export function OverviewPage() {
	const { range, params, previousParams } = useDateRange();
	const { data: current, isLoading: loadingCurrent } = useSummary(params.from, params.to);
	const { data: previous, isLoading: loadingPrev } = useSummary(
		previousParams.from,
		previousParams.to,
	);
	const { data: sources, isLoading: loadingSources } = useSources(params.from, params.to, 10);
	const { data: pages, isLoading: loadingPages } = usePages(params.from, params.to, 10);

	const isLoading = loadingCurrent || loadingPrev;
	const totals = current?.totals;
	const prevTotals = previous?.totals;

	const avgDuration = totals && totals.sessions > 0 ? totals.total_duration / totals.sessions : 0;
	const prevAvgDuration =
		prevTotals && prevTotals.sessions > 0 ? prevTotals.total_duration / prevTotals.sessions : 0;

	// Source table columns.
	const maxVisitors = useMemo(
		() => Math.max(...(sources ?? []).map((s) => s.visitors), 1),
		[sources],
	);
	const maxSessions = useMemo(
		() => Math.max(...(sources ?? []).map((s) => s.sessions), 1),
		[sources],
	);

	const sourceColumns: Column<SourceRow>[] = useMemo(
		() => [
			{
				key: 'source',
				header: 'Source',
				render: (row) => (
					<div>
						<span className="font-medium">{row.name ?? 'Direct'}</span>
						<span className="ml-2 text-xs text-muted-foreground">
							{row.channel ?? 'Direct'}
						</span>
					</div>
				),
			},
			{
				key: 'visitors',
				header: 'Visitors / Sessions',
				sortable: true,
				render: (row) => (
					<DualBarCell
						visitors={row.visitors}
						secondaryValue={row.sessions}
						maxVisitors={maxVisitors}
						maxSecondary={maxSessions}
					/>
				),
			},
		],
		[maxVisitors, maxSessions],
	);

	const pageColumns: Column<PageRow>[] = useMemo(
		() => [
			{
				key: 'uri',
				header: 'Page',
				render: (row) => (
					<div className="max-w-[200px] truncate" title={row.uri}>
						<span className="font-medium">{row.title ?? row.uri}</span>
					</div>
				),
			},
			{
				key: 'visitors',
				header: 'Visitors',
				sortable: true,
				align: 'right' as const,
				render: (row) => (
					<span className="tabular-nums">{formatNumber(row.visitors)}</span>
				),
			},
			{
				key: 'views',
				header: 'Views',
				sortable: true,
				align: 'right' as const,
				render: (row) => (
					<span className="tabular-nums">{formatNumber(row.views)}</span>
				),
			},
		],
		[],
	);

	return (
		<div className="space-y-6">
			<h2 className="text-lg font-semibold">Overview</h2>

			{/* KPI Cards */}
			<div className="grid grid-cols-2 gap-4 md:grid-cols-4">
				<KpiCard
					label="Visitors"
					value={totals ? formatNumber(totals.visitors) : '0'}
					change={totals && prevTotals ? percentChange(totals.visitors, prevTotals.visitors) : undefined}
					isLoading={isLoading}
				/>
				<KpiCard
					label="Sessions"
					value={totals ? formatNumber(totals.sessions) : '0'}
					change={totals && prevTotals ? percentChange(totals.sessions, prevTotals.sessions) : undefined}
					isLoading={isLoading}
				/>
				<KpiCard
					label="Pageviews"
					value={totals ? formatNumber(totals.views) : '0'}
					change={totals && prevTotals ? percentChange(totals.views, prevTotals.views) : undefined}
					isLoading={isLoading}
				/>
				<KpiCard
					label="Avg Duration"
					value={formatDuration(avgDuration)}
					change={prevAvgDuration > 0 ? percentChange(avgDuration, prevAvgDuration) : undefined}
					isLoading={isLoading}
				/>
			</div>

			{/* Time Series Chart */}
			<div className="rounded-lg border border-border bg-card p-4">
				<h3 className="mb-4 text-sm font-medium text-muted-foreground">
					Visitors & Sessions — {range === 'today' ? 'Today' : `Last ${range}`}
				</h3>
				<TimeSeriesChart data={current?.daily ?? []} />
			</div>

			{/* Two-column: Sources + Pages */}
			<div className="grid grid-cols-1 gap-6 md:grid-cols-2">
				<div className="rounded-lg border border-border bg-card p-4">
					<DataTable
						title="Top Sources"
						data={sources ?? []}
						columns={sourceColumns}
						isLoading={loadingSources}
						defaultSortKey="visitors"
						getRowKey={(row, i) => `${row.channel}-${row.name}-${i}`}
						emptyMessage="No traffic sources recorded yet. New visits will appear within a few minutes — if nothing shows after 10 minutes, run the self-test under Settings → Diagnostics or check Settings → Tracking is enabled."
					/>
				</div>
				<div className="rounded-lg border border-border bg-card p-4">
					<DataTable
						title="Top Pages"
						data={pages ?? []}
						columns={pageColumns}
						isLoading={loadingPages}
						defaultSortKey="visitors"
						getRowKey={(row) => row.uri}
						emptyMessage="No page data recorded yet. Tracking is active — pageviews will appear after the next visit. If nothing shows after 10 minutes, run the self-test under Settings → Diagnostics."
					/>
				</div>
			</div>
		</div>
	);
}
