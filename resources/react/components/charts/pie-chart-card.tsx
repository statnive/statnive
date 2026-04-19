import {
	ResponsiveContainer,
	PieChart,
	Pie,
	Cell,
	Tooltip,
	Legend,
} from 'recharts';
import { __ } from '@wordpress/i18n';
import { formatNumber, prefersReducedMotion } from '@/lib/utils';
import { HEADING_H3 } from '@/lib/typography';

interface PieChartDatum {
	name: string;
	value: number;
}

interface PieChartCardProps {
	title: string;
	data: PieChartDatum[];
	colors: string[];
}

function CustomTooltip({
	active,
	payload,
}: {
	active?: boolean;
	payload?: Array<{ name: string; value: number; payload: { fill: string } }>;
}) {
	const entry = payload?.[0];
	if (!active || !entry) return null;
	return (
		<div className="rounded-md border border-border bg-card px-3 py-2 text-sm shadow-sm">
			<p style={{ color: entry.payload.fill }} className="font-medium">
				{entry.name}: {formatNumber(entry.value)}
			</p>
		</div>
	);
}

export function PieChartCard({ title, data, colors }: PieChartCardProps) {
	const total = data.reduce((sum, d) => sum + d.value, 0);

	return (
		<div className="rounded-lg border border-border bg-card p-4">
			<h3 className={`mb-3 ${HEADING_H3}`}>{title}</h3>

			<div className="relative">
				<ResponsiveContainer width="100%" height={240}>
					<PieChart>
						<Pie
							data={data}
							dataKey="value"
							nameKey="name"
							cx="50%"
							cy="50%"
							innerRadius={50}
							outerRadius={90}
							paddingAngle={2}
							isAnimationActive={!prefersReducedMotion}
						>
							{data.map((entry, i) => (
								<Cell
									key={entry.name}
									fill={colors[i % colors.length]}
								/>
							))}
						</Pie>
						<Tooltip content={<CustomTooltip />} />
						<Legend
							formatter={(value: string) => (
								<span className="text-xs text-foreground">
									{value}
								</span>
							)}
						/>
					</PieChart>
				</ResponsiveContainer>
				<div
					className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center"
					aria-hidden="true"
					// Offset the centred overlay upward by the Recharts <Legend>
					// default height so TOTAL+value land in the donut hole.
					style={{ paddingBottom: 36 }}
				>
					<p className="font-display text-[10px] font-medium uppercase tracking-[0.14em] text-muted-foreground">
						{__('Total', 'statnive')}
					</p>
					<p className="font-display text-[24px] font-medium leading-none tracking-[-0.5px] tabular-nums">
						{formatNumber(total)}
					</p>
				</div>
			</div>

			{/* Screen-reader accessible table fallback */}
			<table className="sr-only">
				<caption>{title}</caption>
				<thead>
					<tr>
						<th scope="col">{__('Name', 'statnive')}</th>
						<th scope="col">{__('Visitors', 'statnive')}</th>
						<th scope="col">{__('Percentage', 'statnive')}</th>
					</tr>
				</thead>
				<tbody>
					{data.map((d) => (
						<tr key={d.name}>
							<td>{d.name}</td>
							<td>{formatNumber(d.value)}</td>
							<td>
								{total > 0
									? ((d.value / total) * 100).toFixed(1)
									: '0.0'}
								%
							</td>
						</tr>
					))}
				</tbody>
			</table>
		</div>
	);
}
