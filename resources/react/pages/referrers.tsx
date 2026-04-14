import { useMemo } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { useDateRange } from '@/hooks/use-date-range';
import { useGroupedSources } from '@/hooks/use-sources';
import { useUtm } from '@/hooks/use-utm';
import { DualBarCell } from '@/components/shared/dual-bar-cell';
import { DataTable, type Column } from '@/components/shared/data-table';
import { formatNumber } from '@/lib/utils';
import type { UtmRow } from '@/types/api';

export function ReferrersPage() {
	const { params } = useDateRange();
	const { data: channels, isLoading: loadingChannels } = useGroupedSources(params.from, params.to, 10);
	const { data: utm, isLoading: loadingUtm } = useUtm(params.from, params.to);

	const globalMax = useMemo(() => {
		if (!channels) return 1;
		let max = 1;
		for (const ch of channels) {
			for (const s of ch.sources) {
				if (s.visitors > max) max = s.visitors;
				if (s.sessions > max) max = s.sessions;
			}
		}
		return max;
	}, [channels]);

	const utmColumns: Column<UtmRow>[] = useMemo(
		() => [
			{ key: 'campaign', header: __('Campaign', 'statnive'), render: (row) => <span className="font-medium">{row.campaign ?? '—'}</span> },
			{ key: 'source', header: __('Source', 'statnive'), render: (row) => <span>{row.source ?? '—'}</span> },
			{ key: 'medium', header: __('Medium', 'statnive'), render: (row) => <span>{row.medium ?? '—'}</span> },
			{ key: 'visitors', header: __('Visitors', 'statnive'), sortable: true, align: 'right' as const, render: (row) => <span className="tabular-nums">{formatNumber(row.visitors)}</span> },
		],
		[],
	);

	const emptyReferrerMessage = __('No referrer data for this period. If your site has traffic, data should appear within minutes. If nothing shows after 10 minutes, check Settings → Diagnostics.', 'statnive');

	return (
		<div className="space-y-6">
			<h2 className="text-lg font-semibold">{__('Referrers', 'statnive')}</h2>

			{/* Channel Summary Cards */}
			<div className="grid grid-cols-2 gap-4 md:grid-cols-5">
				{(channels ?? []).map((ch) => (
					<div key={ch.channel} className="rounded-lg border border-border bg-card p-3">
						<p className="text-xs font-medium text-muted-foreground">{__(ch.channel, 'statnive')}</p>
						<p className="mt-1 text-xl font-bold tabular-nums">{formatNumber(ch.visitors)}</p>
						<p className="text-xs text-muted-foreground">
							{sprintf(
								/* translators: %s: formatted session count */
								__('%s sessions', 'statnive'),
								formatNumber(ch.sessions),
							)}
						</p>
					</div>
				))}
			</div>

			{/* Channel-Grouped Sources */}
			<div className="rounded-lg border border-border bg-card p-4">
				<h3 className="mb-3 text-sm font-semibold">{__('All Sources', 'statnive')}</h3>
				{loadingChannels ? (
					<div className="space-y-2" role="status" aria-label={__('Loading sources', 'statnive')}>
						{Array.from({ length: 5 }, (_, i) => (
							<div key={i} className="flex items-center gap-4 py-2">
								<div className="h-4 w-28 animate-pulse rounded bg-muted" />
								<div className="h-4 w-20 animate-pulse rounded bg-muted" />
							</div>
						))}
					</div>
				) : !channels || channels.length === 0 ? (
					<p className="py-8 text-center text-sm text-muted-foreground">{emptyReferrerMessage}</p>
				) : (
					<table role="table" className="w-full">
						{channels.map((ch) => (
							<tbody key={ch.channel}>
								{/* Channel header row */}
								<tr className="border-b border-border bg-muted/50">
									<td className="px-3 py-2 text-sm font-semibold">
										{__(ch.channel, 'statnive')}
									</td>
									<td className="px-3 py-2 text-right text-sm tabular-nums text-muted-foreground">
										{sprintf(
											/* translators: %1$s: visitor count, %2$s: session count */
											__('%1$s visitors · %2$s sessions', 'statnive'),
											formatNumber(ch.visitors),
											formatNumber(ch.sessions),
										)}
									</td>
								</tr>
								{/* Source rows */}
								{ch.sources.map((source, i) => (
									<tr key={`${source.domain}-${source.name}-${i}`} className="border-b border-border last:border-b-0">
										<td className="px-3 py-2 pl-6 text-sm">
											<span className="font-medium">{source.name || __('Direct', 'statnive')}</span>
										</td>
										<td className="px-3 py-2">
											<DualBarCell visitors={source.visitors} secondaryValue={source.sessions} max={globalMax} />
										</td>
									</tr>
								))}
							</tbody>
						))}
					</table>
				)}
			</div>

			{/* UTM Campaigns */}
			<div className="rounded-lg border border-border bg-card p-4">
				<DataTable title={__('UTM Campaigns', 'statnive')} data={utm ?? []} columns={utmColumns} isLoading={loadingUtm} defaultSortKey="visitors" getRowKey={(row, i) => `utm-${row.campaign}-${i}`} emptyMessage={__('No UTM parameters tracked yet. UTM-tagged links will appear here once visitors arrive through them.', 'statnive')} />
			</div>
		</div>
	);
}
