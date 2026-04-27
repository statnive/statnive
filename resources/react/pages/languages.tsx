import { useMemo } from 'react';
import { __ } from '@wordpress/i18n';
import { useDateRange } from '@/hooks/use-date-range';
import { useDimensions } from '@/hooks/use-dimensions';
import { DataTable, type Column } from '@/components/shared/data-table';
import { DualBarCell } from '@/components/shared/dual-bar-cell';
import { HEADING_H2 } from '@/lib/typography';
import type { DimensionRow } from '@/types/api';

export function LanguagesPage() {
	const { params } = useDateRange();
	const { data: languages, isLoading } = useDimensions('languages', params.from, params.to, 30);

	const maxVisitors = useMemo(
		() => Math.max(...(languages ?? []).map(d => Math.max(d.visitors, d.sessions)), 1),
		[languages],
	);

	const columns: Column<DimensionRow>[] = useMemo(
		() => [
			{ key: 'name', header: __('Language', 'statnive'), render: (row) => <span className="font-medium">{row.name ?? '—'}</span> },
			{ key: 'visitors', header: __('Visitors / Sessions', 'statnive'), sortable: true, render: (row) => <DualBarCell visitors={row.visitors} secondaryValue={row.sessions} max={maxVisitors} /> },
		],
		[maxVisitors],
	);

	return (
		<div className="space-y-6">
			<h2 className={HEADING_H2}>{__('Languages', 'statnive')}</h2>
			<div className="rounded-lg border border-border bg-card p-4">
				<DataTable
					title={__('Visitor Languages', 'statnive')}
					data={languages ?? []}
					columns={columns}
					isLoading={isLoading}
					defaultSortKey="visitors"
					getRowKey={(row) => row.name ?? ''}
					emptyMessage={__('No language data for this period. If your site has traffic, data should appear within minutes. If nothing shows after 10 minutes, check Settings → Diagnostics.', 'statnive')}
				/>
			</div>
		</div>
	);
}
