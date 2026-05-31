import { useMemo } from 'react';
import { __ } from '@wordpress/i18n';
import { cn, formatPercentChange } from '@/lib/utils';
import { TimeSeriesChart } from '@/components/charts/time-series-chart';
import type { AdvisorAnswer, AdvisorQuestion, AdvisorVizHint, DailyMetric } from '@/types/api';

/**
 * Renders the answer body for an expanded QuestionCard.
 *
 * Impeccable colorize + delight + polish pass: the KPI tile carries the
 * brand-navy headline number, the delta uses the brand-green accent on
 * positives and the destructive red on negatives, and the grouped-bar
 * viz uses a navy → green gradient on bars so the brand reads through.
 * Numbers are weight-700 tabular-nums with -2% tracking for a tighter
 * masthead feel; secondary text stays muted-foreground at 13px so the
 * number is unambiguously the hero.
 *
 * v1 viz templates: `kpi_tile` / `delta` / `table` / `donut`+`bar`+`map`.
 * Anything else falls through to a JSON preview (dev-only — the
 * coming-soon path renders before this for non-handler questions).
 */
interface AnswerVizProps {
	question: AdvisorQuestion;
	answer: AdvisorAnswer;
}

interface VizContext {
	value: Record<string, unknown> | undefined;
	formatNumber: (n: number) => string;
	answer: AdvisorAnswer;
}

type VizRenderer = (ctx: VizContext) => JSX.Element;

export function AnswerViz({ question, answer }: AnswerVizProps) {
	const viz = (answer.viz || question.viz_hint) as AdvisorVizHint;
	// Memoise the Intl.NumberFormat instance per locale so table / grouped-bar
	// renderers don't allocate one per row (up to 10 allocations per render
	// otherwise). `document.documentElement.lang` is checked at render time
	// because the SPA can switch site language without remounting this card.
	const lang =
		typeof document !== 'undefined' ? document.documentElement.lang || 'en' : 'en';
	const formatNumber = useMemo(() => {
		const fmt = new Intl.NumberFormat(lang);
		return (n: number) => fmt.format(n);
	}, [lang]);

	const ctx: VizContext = {
		value: answer.value as Record<string, unknown> | undefined,
		formatNumber,
		answer,
	};
	const renderer = VIZ_RENDERERS[viz] ?? renderJsonFallback;
	return renderer(ctx);
}

const renderKpiTile: VizRenderer = ({ value, formatNumber }) => {
	// KPI tile shape: `{ visitors }` / `{ sessions }` / `{ pageviews }`.
	// "Top X" tiles also carry a `label` (e.g. "Organic Search" for top
	// channel) which renders above the count.
	const metric = pickKpi(value);
	const label = typeof value?.label === 'string' && value.label.length > 0 ? value.label : null;
	return (
		<div>
			{label && (
				<div className="mb-1 text-[13px] font-medium text-[color:var(--color-primary)]">
					{label}
				</div>
			)}
			<div className="flex items-baseline gap-3">
				<div
					className="text-5xl font-bold tabular-nums leading-none text-[color:var(--color-primary)]"
					style={{ letterSpacing: '-0.02em' }}
				>
					{metric.value !== undefined ? formatNumber(metric.value) : '—'}
				</div>
				<div className="text-[13px] text-muted-foreground">{metric.label}</div>
			</div>
		</div>
	);
};

const renderDelta: VizRenderer = ({ value, formatNumber }) => {
	// Period-comparison shape (Q5/Q6/Q10/Q11): `{ current, previous,
	// delta_pct }` OR anomaly summary `{ current, baseline, delta_pct,
	// on_date }`.
	const current = typeof value?.current === 'number' ? value.current : undefined;
	const previous =
		typeof value?.previous === 'number'
			? value.previous
			: typeof value?.baseline === 'number'
				? value.baseline
				: undefined;
	const deltaPct = typeof value?.delta_pct === 'number' ? value.delta_pct : 0;

	const positive = deltaPct > 0;
	const negative = deltaPct < 0;
	const arrow = positive ? '↑' : negative ? '↓' : '•';
	const chipTone = positive
		? 'bg-[color:var(--color-accent)]/10 text-[color:var(--color-sn-green-dk)]'
		: negative
			? 'bg-destructive/10 text-destructive'
			: 'bg-muted text-muted-foreground';

	// Optional leading YES/NO chip (q86 mobile-vs-desktop). Mirrors the Δ%
	// chip shape so the answer reads "Yes — 134 ↑ +28%" at a glance.
	const yesNo = typeof value?.yes_no === 'string' ? value.yes_no : null;

	return (
		<div className="flex items-baseline gap-3">
			{yesNo && (
				<span
					className={cn(
						'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold uppercase tracking-wide',
						yesNo === 'yes'
							? 'bg-[color:var(--color-accent)]/15 text-[color:var(--color-sn-green-dk)]'
							: 'bg-muted text-muted-foreground',
					)}
				>
					{yesNo === 'yes' ? __('Yes', 'statnive') : __('No', 'statnive')}
				</span>
			)}
			<div
				className="text-5xl font-bold tabular-nums leading-none text-[color:var(--color-primary)]"
				style={{ letterSpacing: '-0.02em' }}
			>
				{current !== undefined ? formatNumber(current) : '—'}
			</div>
			<span
				className={`inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold tabular-nums ${chipTone}`}
			>
				<span aria-hidden="true">{arrow}</span>
				{formatPercentChange(deltaPct)}
			</span>
			{previous !== undefined && (
				<span className="text-[13px] text-muted-foreground">
					{__('vs', 'statnive')} {formatNumber(previous)}
				</span>
			)}
		</div>
	);
};

const renderTable: VizRenderer = ({ value }) => {
	const rows = Array.isArray(value?.rows) ? value.rows : [];
	if (rows.length === 0) {
		return <p className="text-sm text-muted-foreground">{__('No data yet.', 'statnive')}</p>;
	}
	return (
		<div className="overflow-x-auto">
			<table className="w-full border-collapse text-sm">
				<thead>
					<tr className="text-left text-[11px] uppercase tracking-wide text-muted-foreground">
						{Object.keys(rows[0]).map((k) => (
							<th key={k} className="border-b border-border py-2 pr-4 font-medium">
								{k}
							</th>
						))}
					</tr>
				</thead>
				<tbody>
					{rows.slice(0, 10).map((row, i) => (
						<tr
							key={i}
							className="border-b border-border/40 last:border-b-0 hover:bg-muted/40"
						>
							{Object.values(row as Record<string, unknown>).map((v, j) => (
								<td
									key={j}
									className={`py-2 pr-4 tabular-nums ${
										j === 0
											? 'font-medium text-[color:var(--color-primary)]'
											: 'text-foreground'
									}`}
								>
									{typeof v === 'string' || typeof v === 'number'
										? String(v)
										: String(v ?? '')}
								</td>
							))}
						</tr>
					))}
				</tbody>
			</table>
		</div>
	);
};

const renderGroupedBar: VizRenderer = ({ value, formatNumber }) => {
	// Q41 channels, Q72 countries, Q81 devices, plus all the cat-4/6/7
	// helpers that return `{ rows: [...] }` with sessions / visitors per
	// group. v1 renders a horizontal bar with a navy→green gradient so
	// the brand reads without pulling in a charting lib. Shared across
	// `donut` / `bar` / `map` viz hints.
	const rows = Array.isArray(value?.rows) ? (value.rows as Record<string, unknown>[]) : [];
	const totals = rows.map((r) => Number(r.sessions ?? r.visitors ?? 0));
	const max = Math.max(...totals, 1);

	if (rows.length === 0) {
		return <p className="text-sm text-muted-foreground">{__('No data yet.', 'statnive')}</p>;
	}

	return (
		<ul className="space-y-2.5">
			{rows.slice(0, 10).map((row, i) => {
				const label = String(
					row.label ?? row.channel ?? row.device ?? row.network ?? row.name ?? row.code ?? '—',
				);
				const count = Number(row.sessions ?? row.visitors ?? 0);
				const pct = (count / max) * 100;
				return (
					<li key={i} className="grid grid-cols-[1fr_auto] items-center gap-3 text-sm">
						<div className="space-y-1">
							<div className="text-[13px] font-medium text-[color:var(--color-primary)]">
								{label}
							</div>
							<div className="relative h-2 overflow-hidden rounded-full bg-muted">
								<div
									className="h-full rounded-full transition-[width] duration-300 ease-out"
									style={{
										width: `${pct.toFixed(1)}%`,
										background:
											'linear-gradient(90deg, var(--color-primary) 0%, var(--color-accent) 100%)',
									}}
									aria-hidden="true"
								/>
							</div>
						</div>
						<div className="font-mono text-xs tabular-nums text-muted-foreground">
							{formatNumber(count)}
						</div>
					</li>
				);
			})}
		</ul>
	);
};

const renderLine: VizRenderer = ({ value }) => {
	const rows = Array.isArray(value?.rows) ? (value.rows as DailyMetric[]) : [];
	if (rows.length === 0) {
		return <p className="text-sm text-muted-foreground">{__('No data yet.', 'statnive')}</p>;
	}
	return <TimeSeriesChart data={rows} />;
};

const renderRecommendation: VizRenderer = ({ value }) => {
	// q87 — mobile share with a one-line recommendation. The share % is the
	// hero number; the recommendation sentence below tells the owner what
	// to do with it.
	const share = typeof value?.share_pct === 'number' ? value.share_pct : 0;
	const rec = typeof value?.recommendation === 'string' ? value.recommendation : '';
	const copy =
		rec === 'prioritise_mobile'
			? __('Mobile is the majority. Prioritise the mobile design first.', 'statnive')
			: __('Desktop still leads. Mobile improvements are secondary for now.', 'statnive');
	return (
		<div>
			<div className="flex items-baseline gap-3">
				<div
					className="text-5xl font-bold tabular-nums leading-none text-[color:var(--color-primary)]"
					style={{ letterSpacing: '-0.02em' }}
				>
					{share.toFixed(0)}%
				</div>
				<div className="text-[13px] text-muted-foreground">
					{__('of sessions are on mobile', 'statnive')}
				</div>
			</div>
			<p className="mt-3 text-sm text-foreground/80">{copy}</p>
		</div>
	);
};

const renderJsonFallback: VizRenderer = ({ answer }) => (
	<pre className="overflow-x-auto rounded bg-muted/40 p-3 text-xs">
		{JSON.stringify(answer.value, null, 2)}
	</pre>
);

/**
 * Viz hint → renderer dispatch. Mirror of `Questions::VIZ_*` on the server;
 * anything missing falls through to the JSON preview (dev-only — the
 * coming-soon path renders before this for non-handler questions).
 */
const VIZ_RENDERERS: Partial<Record<AdvisorVizHint, VizRenderer>> = {
	kpi_tile: renderKpiTile,
	delta: renderDelta,
	table: renderTable,
	donut: renderGroupedBar,
	bar: renderGroupedBar,
	map: renderGroupedBar,
	line: renderLine,
	recommendation: renderRecommendation,
};

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
