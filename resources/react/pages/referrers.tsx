import { useMemo } from 'react';
import { __, sprintf } from '@wordpress/i18n';
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

	const max = useMemo(
		() => Math.max(
			...(sources ?? []).map((s) => Math.max(s.visitors, s.sessions)),
			1,
		),
		[sources],
	);

	const sourceColumns: Column<SourceRow>[] = useMemo(
		() => [
			{
				key: 'name', header: __('Source', 'statnive'),
				render: (row) => (
					<div>
						<span className="font-medium">{row.name ?? __('Direct', 'statnive')}</span>
						<span className="ml-2 text-xs text-muted-foreground">{row.channel ?? ''}</span>
					</div>
				),
			},
			{ key: 'visitors', header: __('Visitors / Sessions', 'statnive'), sortable: true,
				render: (row) => <DualBarCell visitors={row.visitors} secondaryValue={row.sessions} max={max} />,
			},
		],
		[max],
	);

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
				{channelSummary.map((ch) => (
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

			{/* All Sources */}
			<div className="rounded-lg border border-border bg-card p-4">
				<DataTable title={__('All Sources', 'statnive')} data={sources ?? []} columns={sourceColumns} isLoading={loadingSources} defaultSortKey="visitors" getRowKey={(row, i) => `${row.channel}-${row.name}-${i}`} emptyMessage={emptyReferrerMessage} />
			</div>

			{/* UTM Campaigns */}
			<div className="rounded-lg border border-border bg-card p-4">
				<DataTable title={__('UTM Campaigns', 'statnive')} data={utm ?? []} columns={utmColumns} isLoading={loadingUtm} defaultSortKey="visitors" getRowKey={(row, i) => `utm-${row.campaign}-${i}`} emptyMessage={__('No UTM parameters tracked yet. UTM-tagged links will appear here once visitors arrive through them.', 'statnive')} />
			</div>
		</div>
	);
}
