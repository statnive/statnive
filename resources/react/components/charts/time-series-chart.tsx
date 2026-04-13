import {
	ResponsiveContainer,
	LineChart,
	Line,
	XAxis,
	YAxis,
	CartesianGrid,
	Tooltip,
	Legend,
} from 'recharts';
import { __ } from '@wordpress/i18n';
import type { DailyMetric } from '@/types/api';
import { formatNumber } from '@/lib/utils';

interface TimeSeriesChartProps {
	data: DailyMetric[];
}

const prefersReducedMotion =
	typeof window !== 'undefined' &&
	window.matchMedia('(prefers-reduced-motion: reduce)').matches;

function CustomTooltip({
	active,
	payload,
	label,
}: {
	active?: boolean;
	payload?: Array<{ value: number; name: string; color: string }>;
	label?: string;
}) {
	if (!active || !payload?.length) return null;

	return (
		<div className="rounded-md border border-border bg-card px-3 py-2 text-sm shadow-sm">
			<p className="mb-1 font-medium">{label}</p>
			{payload.map((entry) => (
				<p key={entry.name} style={{ color: entry.color }}>
					{entry.name}: {formatNumber(entry.value)}
				</p>
			))}
		</div>
	);
}

export function TimeSeriesChart({ data }: TimeSeriesChartProps) {
	const visitorsLabel = __('Visitors', 'statnive');
	const sessionsLabel = __('Sessions', 'statnive');

	return (
		<>
			<ResponsiveContainer width="100%" height={300}>
				<LineChart data={data} margin={{ top: 5, right: 20, left: 0, bottom: 5 }}>
					<CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
					<XAxis
						dataKey="date"
						tick={{ fontSize: 12, fill: '#6b7280' }}
						tickLine={false}
						axisLine={{ stroke: '#e5e7eb' }}
					/>
					<YAxis
						tick={{ fontSize: 12, fill: '#6b7280' }}
						tickLine={false}
						axisLine={false}
						width={50}
					/>
					<Tooltip content={<CustomTooltip />} />
					<Legend />
					<Line
						type="monotone"
						dataKey="visitors"
						name={visitorsLabel}
						stroke="#2271b1"
						strokeWidth={2}
						dot={false}
						isAnimationActive={!prefersReducedMotion}
					/>
					<Line
						type="monotone"
						dataKey="sessions"
						name={sessionsLabel}
						stroke="#93c5fd"
						strokeWidth={2}
						strokeDasharray="5 5"
						dot={false}
						isAnimationActive={!prefersReducedMotion}
					/>
				</LineChart>
			</ResponsiveContainer>

			{/* Visually-hidden table fallback for screen readers */}
			<table className="sr-only">
				<caption>{__('Visitors and sessions over time', 'statnive')}</caption>
				<thead>
					<tr>
						<th scope="col">{__('Date', 'statnive')}</th>
						<th scope="col">{visitorsLabel}</th>
						<th scope="col">{sessionsLabel}</th>
					</tr>
				</thead>
				<tbody>
					{data.map((row) => (
						<tr key={row.date}>
							<td>{row.date}</td>
							<td>{formatNumber(row.visitors)}</td>
							<td>{formatNumber(row.sessions)}</td>
						</tr>
					))}
				</tbody>
			</table>
		</>
	);
}
