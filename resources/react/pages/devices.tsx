import { useMemo } from 'react';
import { __ } from '@wordpress/i18n';
import { useDateRange } from '@/hooks/use-date-range';
import { useDimensions } from '@/hooks/use-dimensions';
import { DataTable, type Column } from '@/components/shared/data-table';
import { DualBarCell } from '@/components/shared/dual-bar-cell';
import { PieChartCard } from '@/components/charts/pie-chart-card';
import type { DimensionRow } from '@/types/api';

const BOT_DEVICE_TYPE = 'Bot';
const DEVICE_COLORS = ['#2271b1', '#059669', '#f59e0b'];
const BOT_COLORS = ['#2271b1', '#ef4444'];

export function DevicesPage() {
	const { params } = useDateRange();
	const { data: deviceTypes } = useDimensions('devices', params.from, params.to);
	const { data: browsers, isLoading: loadingBrowsers } = useDimensions('browsers', params.from, params.to);
	const { data: oss, isLoading: loadingOs } = useDimensions('oss', params.from, params.to);

	const { humanDevices, botVsHuman } = useMemo(() => {
		const devices = deviceTypes ?? [];
		const human: { name: string; value: number }[] = [];
		let botTotal = 0;
		let humanTotal = 0;

		for (const d of devices) {
			if (d.name === BOT_DEVICE_TYPE) {
				botTotal += Number(d.visitors);
			} else {
				human.push({ name: d.name ?? __('Unknown', 'statnive'), value: Number(d.visitors) });
				humanTotal += Number(d.visitors);
			}
		}

		return {
			humanDevices: human,
			botVsHuman: [
				{ name: __('Human', 'statnive'), value: humanTotal },
				{ name: __('Bot', 'statnive'), value: botTotal },
			],
		};
	}, [deviceTypes]);

	const maxBrowsers = useMemo(
		() => Math.max(...(browsers ?? []).map(d => Math.max(d.visitors, d.sessions)), 1),
		[browsers],
	);
	const maxOs = useMemo(
		() => Math.max(...(oss ?? []).map(d => Math.max(d.visitors, d.sessions)), 1),
		[oss],
	);

	const browserColumns: Column<DimensionRow>[] = useMemo(
		() => [
			{ key: 'name', header: __('Name', 'statnive'), render: (row) => <span className="font-medium">{row.name ?? '—'}</span> },
			{ key: 'visitors', header: __('Visitors / Sessions', 'statnive'), sortable: true, render: (row) => <DualBarCell visitors={row.visitors} secondaryValue={row.sessions} max={maxBrowsers} /> },
		],
		[maxBrowsers],
	);
	const osColumns: Column<DimensionRow>[] = useMemo(
		() => [
			{ key: 'name', header: __('Name', 'statnive'), render: (row) => <span className="font-medium">{row.name ?? '—'}</span> },
			{ key: 'visitors', header: __('Visitors / Sessions', 'statnive'), sortable: true, render: (row) => <DualBarCell visitors={row.visitors} secondaryValue={row.sessions} max={maxOs} /> },
		],
		[maxOs],
	);

	const emptyDeviceMessage = __('No device data for this period. If your site has traffic, data should appear within minutes. If nothing shows after 10 minutes, check Settings → Diagnostics.', 'statnive');

	return (
		<div className="space-y-6">
			<h2 className="text-lg font-semibold">{__('Devices', 'statnive')}</h2>

			{/* Device Distribution + Bot vs Human pie charts */}
			<div className="grid grid-cols-1 gap-6 md:grid-cols-2">
				<PieChartCard
					title={__('Device Distribution', 'statnive')}
					data={humanDevices}
					colors={DEVICE_COLORS}
				/>
				<PieChartCard
					title={__('Bot vs Human', 'statnive')}
					data={botVsHuman}
					colors={BOT_COLORS}
				/>
			</div>

			{/* Browsers + OS */}
			<div className="grid grid-cols-1 gap-6 md:grid-cols-2">
				<div className="rounded-lg border border-border bg-card p-4">
					<DataTable title={__('Browsers', 'statnive')} data={browsers ?? []} columns={browserColumns} isLoading={loadingBrowsers} defaultSortKey="visitors" getRowKey={(row) => row.name ?? ''} emptyMessage={emptyDeviceMessage} />
				</div>
				<div className="rounded-lg border border-border bg-card p-4">
					<DataTable title={__('Operating Systems', 'statnive')} data={oss ?? []} columns={osColumns} isLoading={loadingOs} defaultSortKey="visitors" getRowKey={(row) => row.name ?? ''} emptyMessage={emptyDeviceMessage} />
				</div>
			</div>
		</div>
	);
}
