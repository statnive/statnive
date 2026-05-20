import { __ } from '@wordpress/i18n';
import type { AdvisorAnswer, AdvisorQuestion } from '@/types/api';

/**
 * Light renderer that adapts an AdvisorAnswer to a viz template. The five
 * v1 handlers (Q2 KPI tile, Q23 table, Q41 donut/table, Q72 country
 * table+map, Q81 device bar) cover the default-pinned questions. Other
 * `viz` values fall through to a generic JSON dump (development only — the
 * coming-soon path renders before this for non-handler questions).
 */
interface AnswerVizProps {
	question: AdvisorQuestion;
	answer: AdvisorAnswer;
}

export function AnswerViz({ question, answer }: AnswerVizProps) {
	const viz = answer.viz || question.viz_hint;
	const value = answer.value as Record<string, unknown> | undefined;

	if (viz === 'kpi_tile') {
		// KPI tile shape: any of `{ visitors }` / `{ sessions }` / `{ pageviews }`
		// from the cat-1 handlers; fall back to the first numeric field found.
		const lang = document.documentElement.lang || 'en';
		const metric = pickKpi(value);
		return (
			<div>
				<div className="text-4xl font-semibold tabular-nums">
					{metric.value !== undefined ? new Intl.NumberFormat(lang).format(metric.value) : '—'}
				</div>
				<div className="mt-1 text-sm text-muted-foreground">{metric.label}</div>
			</div>
		);
	}

	if (viz === 'delta') {
		// Period-comparison shape from Q5/Q6/Q10/Q11. Carries
		// `{ current, previous, delta_pct, … }` OR anomaly-summary
		// `{ has_anomaly, current, baseline, delta_pct, on_date }`.
		const lang = document.documentElement.lang || 'en';
		const current = typeof value?.current === 'number' ? value.current : undefined;
		const previous =
			typeof value?.previous === 'number'
				? value.previous
				: typeof value?.baseline === 'number'
					? value.baseline
					: undefined;
		const deltaPct = typeof value?.delta_pct === 'number' ? value.delta_pct : 0;
		const arrow = deltaPct > 0 ? '▲' : deltaPct < 0 ? '▼' : '•';
		const tone =
			deltaPct > 0 ? 'text-emerald-600' : deltaPct < 0 ? 'text-rose-600' : 'text-muted-foreground';
		return (
			<div>
				<div className="text-4xl font-semibold tabular-nums">
					{current !== undefined ? new Intl.NumberFormat(lang).format(current) : '—'}
				</div>
				<div className={`mt-1 text-sm ${tone}`}>
					<span aria-hidden="true">{arrow}</span> {Math.abs(deltaPct).toFixed(1)}%
					{previous !== undefined && (
						<span className="ml-2 text-muted-foreground">
							{__('vs', 'statnive')}{' '}
							{new Intl.NumberFormat(lang).format(previous)}
						</span>
					)}
				</div>
			</div>
		);
	}

	if (viz === 'table') {
		// Q23 top-pages pattern: `{ rows: [{ uri, views }] }`.
		const rows = Array.isArray(value?.rows) ? value.rows : [];
		if (rows.length === 0) {
			return <p className="text-sm text-muted-foreground">{__('No data yet.', 'statnive')}</p>;
		}
		return (
			<table className="w-full border-collapse text-sm">
				<thead>
					<tr className="text-left text-[11px] uppercase text-muted-foreground">
						{Object.keys(rows[0]).map((k) => (
							<th key={k} className="border-b border-border py-2 font-medium">
								{k}
							</th>
						))}
					</tr>
				</thead>
				<tbody>
					{rows.slice(0, 10).map((row, i) => (
						<tr key={i} className={i % 2 === 0 ? 'bg-card' : 'bg-muted/40'}>
							{Object.values(row as Record<string, unknown>).map((v, j) => (
								<td key={j} className="py-2 pr-3 tabular-nums">
									{typeof v === 'string' ? v : String(v ?? '')}
								</td>
							))}
						</tr>
					))}
				</tbody>
			</table>
		);
	}

	if (viz === 'donut' || viz === 'bar' || viz === 'map') {
		// Q41 channels, Q72 countries, Q81 devices — all return `{ rows: [...] }`
		// with sessions/visitors per group. For v1 we render a horizontal bar so
		// every grouped answer looks correct without a charting lib dependency
		// (lib swap to Recharts comes in the same PR that ships native viz).
		const rows = Array.isArray(value?.rows) ? (value.rows as Record<string, unknown>[]) : [];
		const totals = rows.map((r) => Number(r.sessions ?? r.visitors ?? 0));
		const max = Math.max(...totals, 1);

		return (
			<ul className="space-y-1.5">
				{rows.slice(0, 10).map((row, i) => {
					const label = String(row.channel ?? row.device ?? row.name ?? row.code ?? '—');
					const count = Number(row.sessions ?? row.visitors ?? 0);
					const pct = (count / max) * 100;
					return (
						<li key={i} className="grid grid-cols-[1fr_auto] items-center gap-3 text-sm">
							<div className="space-y-1">
								<div className="text-sm font-medium">{label}</div>
								<div className="h-1.5 rounded-full bg-muted">
									<div
										className="h-full rounded-full bg-primary"
										style={{ width: `${pct.toFixed(1)}%` }}
										aria-hidden="true"
									/>
								</div>
							</div>
							<div className="font-mono text-xs tabular-nums text-muted-foreground">
								{new Intl.NumberFormat(document.documentElement.lang || 'en').format(count)}
							</div>
						</li>
					);
				})}
			</ul>
		);
	}

	return (
		<pre className="overflow-x-auto rounded bg-muted/40 p-3 text-xs">
			{JSON.stringify(answer.value, null, 2)}
		</pre>
	);
}

/** Pick the canonical KPI from the answer value, with a localized label. */
function pickKpi(value: Record<string, unknown> | undefined): {
	value: number | undefined;
	label: string;
} {
	if (typeof value?.visitors === 'number') {
		return { value: value.visitors, label: __('Visitors in selected range', 'statnive') };
	}
	if (typeof value?.sessions === 'number') {
		return { value: value.sessions, label: __('Sessions in selected range', 'statnive') };
	}
	if (typeof value?.pageviews === 'number') {
		return { value: value.pageviews, label: __('Pageviews in selected range', 'statnive') };
	}
	return { value: undefined, label: __('No data', 'statnive') };
}
