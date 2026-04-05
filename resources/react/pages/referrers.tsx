import { useMemo } from 'react';
import { useDateRange } from '@/hooks/use-date-range';
import { useSources } from '@/hooks/use-sources';
import { useUtm } from '@/hooks/use-utm';
import { DualBarCell } from '@/components/shared/dual-bar-cell';
import { DataTable, type Column } from '@/components/shared/data-table';
import { formatNumber } from '@/lib/utils';
import type { SourceRow, UtmRow } from '@/types/api';

const CHANNELS = ['Organic Search', 'Direct', 'Social Media', 'Referral', 'Email'] as const;

export function ReferrersPage() {
	const { params } = useDateRange();
	const { data: sources, isLoading: loadingSources } = useSources(params.from, params.to, 50);
	const { data: utm, isLoading: loadingUtm } = useUtm(params.from, params.to);

	const channelSummary = useMemo(() => {
		if (!sources) return [];
		return CHANNELS.map((ch) => {
			const items = sources.filter((s) => s.channel === ch);
			return {
				channel: ch,
				visitors: items.reduce((sum, s) => sum + Number(s.visitors), 0),
				sessions: items.reduce((sum, s) => sum + Number(s.sessions), 0),
			};
		});
	}, [sources]);

	const maxV = useMemo(() => Math.max(...(sources ?? []).map((s) => s.visitors), 1), [sources]);
	const maxS = useMemo(() => Math.max(...(sources ?? []).map((s) => s.sessions), 1), [sources]);

	const sourceColumns: Column<SourceRow>[] = useMemo(
		() => [
			{
				key: 'name', header: 'Source',
				render: (row) => (
					<div>
						<span className="font-medium">{row.name ?? 'Direct'}</span>
						<span className="ml-2 text-xs text-muted-foreground">{row.channel ?? ''}</span>
					</div>
				),
			},
			{ key: 'visitors', header: 'Visitors / Sessions', sortable: true,
				render: (row) => <DualBarCell visitors={row.visitors} secondaryValue={row.sessions} maxVisitors={maxV} maxSecondary={maxS} />,
			},
		],
		[maxV, maxS],
	);

	const utmColumns: Column<UtmRow>[] = useMemo(
		() => [
			{ key: 'campaign', header: 'Campaign', render: (row) => <span className="font-medium">{row.campaign ?? '—'}</span> },
			{ key: 'source', header: 'Source', render: (row) => <span>{row.source ?? '—'}</span> },
			{ key: 'medium', header: 'Medium', render: (row) => <span>{row.medium ?? '—'}</span> },
			{ key: 'visitors', header: 'Visitors', sortable: true, align: 'right' as const, render: (row) => <span className="tabular-nums">{formatNumber(row.visitors)}</span> },
		],
		[],
	);

	return (
		<div className="space-y-6">
			<h2 className="text-lg font-semibold">Referrers</h2>

			{/* Channel Summary Cards */}
			<div className="grid grid-cols-2 gap-4 md:grid-cols-5">
				{channelSummary.map((ch) => (
					<div key={ch.channel} className="rounded-lg border border-border bg-card p-3">
						<p className="text-xs font-medium text-muted-foreground">{ch.channel}</p>
						<p className="mt-1 text-xl font-bold tabular-nums">{formatNumber(ch.visitors)}</p>
						<p className="text-xs text-muted-foreground">{formatNumber(ch.sessions)} sessions</p>
					</div>
				))}
			</div>

			{/* All Sources */}
			<div className="rounded-lg border border-border bg-card p-4">
				<DataTable title="All Sources" data={sources ?? []} columns={sourceColumns} isLoading={loadingSources} defaultSortKey="visitors" getRowKey={(row, i) => `${row.channel}-${row.name}-${i}`} />
			</div>

			{/* UTM Campaigns */}
			<div className="rounded-lg border border-border bg-card p-4">
				<DataTable title="UTM Campaigns" data={utm ?? []} columns={utmColumns} isLoading={loadingUtm} defaultSortKey="visitors" getRowKey={(row, i) => `utm-${row.campaign}-${i}`} emptyMessage="No UTM parameters tracked yet" />
			</div>
		</div>
	);
}
