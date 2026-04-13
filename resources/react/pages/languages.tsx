import { useMemo } from 'react';
import { __ } from '@wordpress/i18n';
import { useDateRange } from '@/hooks/use-date-range';
import { useDimensions } from '@/hooks/use-dimensions';
import { DataTable, type Column } from '@/components/shared/data-table';
import { formatNumber } from '@/lib/utils';
import type { DimensionRow } from '@/types/api';

export function LanguagesPage() {
	const { params } = useDateRange();
	const { data: languages, isLoading } = useDimensions('languages', params.from, params.to, 30);

	const columns: Column<DimensionRow>[] = useMemo(
		() => [
			{ key: 'name', header: __('Language', 'statnive'), render: (row) => <span className="font-medium">{row.name ?? '—'}</span> },
			{ key: 'visitors', header: __('Visitors', 'statnive'), sortable: true, align: 'right' as const, render: (row) => <span className="tabular-nums">{formatNumber(row.visitors)}</span> },
			{ key: 'sessions', header: __('Sessions', 'statnive'), sortable: true, align: 'right' as const, render: (row) => <span className="tabular-nums">{formatNumber(row.sessions)}</span> },
		],
		[],
	);

	return (
		<div className="space-y-6">
			<h2 className="text-lg font-semibold">{__('Languages', 'statnive')}</h2>
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
