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
		// Q2 visitors-this-week pattern: `{ visitors, from, to }`.
		const number = typeof value?.visitors === 'number' ? value.visitors : undefined;
		return (
			<div>
				<div className="text-4xl font-semibold tabular-nums">
					{number !== undefined
						? new Intl.NumberFormat(document.documentElement.lang || 'en').format(number)
						: '—'}
				</div>
				<div className="mt-1 text-sm text-muted-foreground">
					{__('Visitors in selected range', 'statnive')}
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
