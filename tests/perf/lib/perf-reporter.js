/**
 * Performance impact report generator.
 *
 * Reads k6 summary data from multiple plugin configurations,
 * computes deltas from baseline, and produces a ranked comparison.
 *
 * Used by perf-impact-test.js handleSummary() for per-config output,
 * and by perf-impact-runner.sh for the final merged report.
 */

/**
 * Extract p50 and p95 from a k6 metric's values object.
 *
 * @param {object} metric - k6 metric with values { p(50), p(95), avg, ... }
 * @returns {{ p50: number, p95: number, avg: number }}
 */
function extractPercentiles(metric) {
	if (!metric || !metric.values) {
		return { p50: 0, p95: 0, avg: 0 };
	}
	return {
		p50: metric.values['p(50)'] || metric.values.med || 0,
		p95: metric.values['p(95)'] || 0,
		avg: metric.values.avg || 0,
	};
}

/**
 * Extract vitals summary from k6 summary data.
 *
 * @param {object} k6Data - k6 handleSummary data object.
 * @param {string} configLabel - Configuration label (e.g. 'baseline', 'statnive').
 * @param {object} [runMeta] - Optional run metadata (run_index, run_ts, config_order).
 * @returns {object} Structured vitals object.
 */
export function extractVitalsSummary(k6Data, configLabel, runMeta = {}) {
	const metrics = k6Data.metrics || {};

	const ttfb = extractPercentiles(metrics.web_vital_ttfb);
	const fcp = extractPercentiles(metrics.web_vital_fcp);
	const lcp = extractPercentiles(metrics.web_vital_lcp);
	const cls = extractPercentiles(metrics.web_vital_cls);
	const inp = extractPercentiles(metrics.web_vital_inp);
	const serverTime = extractPercentiles(metrics.server_response_time);
	const pageRequests = extractPercentiles(metrics.page_total_requests);
	const trackerSize = extractPercentiles(metrics.tracker_script_size);

	return {
		config: configLabel,
		timestamp: new Date().toISOString(),
		run_meta: runMeta,
		vitals: {
			ttfb: { p50: round(ttfb.p50), p95: round(ttfb.p95), avg: round(ttfb.avg) },
			fcp: { p50: round(fcp.p50), p95: round(fcp.p95), avg: round(fcp.avg) },
			lcp: { p50: round(lcp.p50), p95: round(lcp.p95), avg: round(lcp.avg) },
			cls: { p50: round(cls.p50, 4), p95: round(cls.p95, 4), avg: round(cls.avg, 4) },
			inp: { p50: round(inp.p50), p95: round(inp.p95), avg: round(inp.avg) },
		},
		server: {
			response_time: { p50: round(serverTime.p50), p95: round(serverTime.p95) },
		},
		network: {
			requests_avg: round(pageRequests.avg),
			tracker_kb_avg: round(trackerSize.avg, 1),
		},
		samples: metrics.browser_page_loads?.values?.count || metrics.web_vital_ttfb?.values?.count || 0,
	};
}

/**
 * Compute performance deltas between a plugin config and the baseline.
 *
 * @param {object} baseline - Vitals summary for baseline config.
 * @param {object} plugin - Vitals summary for a plugin config.
 * @returns {object} Delta object with ms/value differences and percentages.
 */
export function computeDelta(baseline, plugin) {
	const delta = {};
	const deltaPct = {};

	for (const vital of ['ttfb', 'fcp', 'lcp', 'cls', 'inp']) {
		const bVal = baseline.vitals[vital].p50;
		const pVal = plugin.vitals[vital].p50;
		const diff = pVal - bVal;

		delta[vital] = round(diff, vital === 'cls' ? 4 : 1);

		if (bVal > 0) {
			deltaPct[vital] = round((diff / bVal) * 100, 1);
		} else {
			deltaPct[vital] = 0;
		}
	}

	// Server response time delta.
	delta.server_time = round(
		plugin.server.response_time.p50 - baseline.server.response_time.p50, 1
	);

	// Network deltas.
	delta.requests = round(plugin.network.requests_avg - baseline.network.requests_avg);
	delta.tracker_kb = round(plugin.network.tracker_kb_avg - baseline.network.tracker_kb_avg, 1);

	return { delta, delta_pct: deltaPct };
}

/**
 * Compute a composite impact score from deltas.
 * Lower = less impact = better.
 *
 * Weights: LCP 30%, TTFB 25%, FCP 20%, CLS 15%, INP 10%.
 *
 * @param {object} delta - Delta values from computeDelta().
 * @param {object} baseline - Baseline vitals summary.
 * @returns {number} Impact score (0 = no impact).
 */
export function impactScore(delta, baseline) {
	const bTTFB = Math.max(baseline.vitals.ttfb.p50, 1);
	const bLCP = Math.max(baseline.vitals.lcp.p50, 1);
	const bFCP = Math.max(baseline.vitals.fcp.p50, 1);

	const score =
		(Math.max(0, delta.ttfb) / bTTFB) * 0.25 +
		(Math.max(0, delta.lcp) / bLCP) * 0.30 +
		(Math.max(0, delta.fcp) / bFCP) * 0.20 +
		(Math.max(0, delta.cls) / 0.1) * 0.15 +
		(Math.max(0, delta.inp) / 200) * 0.10;

	return round(score * 100, 1);
}

/**
 * Build the full performance impact comparison report.
 *
 * @param {object} baselineSummary - Vitals summary for baseline.
 * @param {object[]} pluginSummaries - Array of vitals summaries for each plugin.
 * @param {string} loadTier - Load tier used (light/medium/heavy).
 * @returns {object} Complete report with rankings.
 */
export function buildPerfImpactReport(baselineSummary, pluginSummaries, loadTier) {
	const plugins = {};

	for (const ps of pluginSummaries) {
		const { delta, delta_pct } = computeDelta(baselineSummary, ps);
		const score = impactScore(delta, baselineSummary);

		plugins[ps.config] = {
			vitals: ps.vitals,
			server: ps.server,
			network: ps.network,
			delta,
			delta_pct,
			impact_score: score,
			samples: ps.samples,
		};
	}

	// Rank by impact score (lowest first = best).
	const ranking = Object.entries(plugins)
		.sort((a, b) => a[1].impact_score - b[1].impact_score)
		.map(([name]) => name);

	return {
		timestamp: new Date().toISOString(),
		load_tier: loadTier,
		baseline: baselineSummary,
		plugins,
		ranking,
	};
}

/**
 * Format the performance impact report as a console-friendly table.
 *
 * @param {object} report - From buildPerfImpactReport().
 * @returns {string} Formatted table string.
 */
export function consolePerfReport(report) {
	const lines = [
		'',
		'╔══════════════════════════════════════════════════════════════════════════════════╗',
		'║                      PERFORMANCE IMPACT COMPARISON                              ║',
		'╚══════════════════════════════════════════════════════════════════════════════════╝',
		'',
		`  Load Tier:   ${report.load_tier}`,
		`  Timestamp:   ${report.timestamp}`,
		`  Baseline:    TTFB ${report.baseline.vitals.ttfb.p50}ms | FCP ${report.baseline.vitals.fcp.p50}ms | LCP ${report.baseline.vitals.lcp.p50}ms`,
		'',
		'  ┌───────────────────────────┬────────┬────────┬────────┬────────┬────────┬───────┐',
		'  │ Plugin                    │ TTFB   │  FCP   │  LCP   │  CLS   │  INP   │Impact │',
		'  │                           │ Δ ms   │ Δ ms   │ Δ ms   │  Δ     │ Δ ms   │ Score │',
		'  ├───────────────────────────┼────────┼────────┼────────┼────────┼────────┼───────┤',
	];

	// Baseline row.
	lines.push(
		'  │ baseline                  │   ---  │   ---  │   ---  │  ---   │   ---  │  0.0  │'
	);

	// Plugin rows (sorted by ranking).
	for (const name of report.ranking) {
		const p = report.plugins[name];
		const d = p.delta;
		const fmtMs = (v) => {
			const s = (v >= 0 ? '+' : '') + Math.round(v);
			return (s + 'ms').padStart(6);
		};
		const fmtCls = (v) => {
			const s = (v >= 0 ? '+' : '') + v.toFixed(3);
			return s.padStart(6);
		};
		const fmtScore = (v) => v.toFixed(1).padStart(5);

		lines.push(
			`  │ ${name.padEnd(25)} │ ${fmtMs(d.ttfb)} │ ${fmtMs(d.fcp)} │ ${fmtMs(d.lcp)} │ ${fmtCls(d.cls)} │ ${fmtMs(d.inp)} │ ${fmtScore(p.impact_score)} │`
		);
	}

	lines.push(
		'  └───────────────────────────┴────────┴────────┴────────┴────────┴────────┴───────┘'
	);
	lines.push('');

	// Winner announcement.
	if (report.ranking.length > 0) {
		const best = report.ranking[0];
		const worst = report.ranking[report.ranking.length - 1];
		lines.push(`  🏆 Lowest impact: ${best} (score: ${report.plugins[best].impact_score})`);
		lines.push(`  ⚠️  Highest impact: ${worst} (score: ${report.plugins[worst].impact_score})`);
		lines.push('');
	}

	return lines.join('\n');
}

/**
 * Generate handleSummary output for a single perf-impact config run.
 *
 * @param {object} k6Data - k6 summary data.
 * @param {string} configLabel - Config label (e.g. 'baseline', 'statnive').
 * @param {string} [resultsDir] - Directory to write the JSON file (default: ./results/perf-impact).
 * @param {object} [runMeta] - Optional run metadata (run_index, run_ts, config_order).
 * @returns {object} k6 handleSummary return value.
 */
export function generatePerfSummaryOutput(k6Data, configLabel, resultsDir = './results/perf-impact', runMeta = {}) {
	const summary = extractVitalsSummary(k6Data, configLabel, runMeta);
	const timestamp = Date.now();

	return {
		stdout: `\n  Config: ${configLabel} | TTFB p50: ${summary.vitals.ttfb.p50}ms | LCP p50: ${summary.vitals.lcp.p50}ms | Samples: ${summary.samples}\n`,
		[`${resultsDir}/${configLabel}-${timestamp}.json`]: JSON.stringify(summary, null, 2),
	};
}

// ---------------------------------------------------------------------------
// Multi-run aggregation (Phase 1 of ROADMAP-PERFORMANCE.md)
// ---------------------------------------------------------------------------

/**
 * Aggregate N per-run summary files for a single config into one stats object.
 * Computes median-of-medians, median-of-p95s, IQR, standard deviation,
 * min, and max across the N runs' p50 values.
 *
 * @param {object[]} runs - Array of per-run vitals summaries for the same config.
 * @returns {object|null} Aggregate stats, or null if runs is empty.
 */
export function aggregateRuns(runs) {
	if (!runs || runs.length === 0) return null;

	const vitals = {};
	for (const vital of ['ttfb', 'fcp', 'lcp', 'cls', 'inp']) {
		const p50s = runs.map((r) => r.vitals[vital].p50).sort((a, b) => a - b);
		const p95s = runs.map((r) => r.vitals[vital].p95).sort((a, b) => a - b);
		const dec = vital === 'cls' ? 4 : 1;
		vitals[vital] = {
			median: round(median(p50s), dec),
			p95_median: round(median(p95s), dec),
			iqr: round(iqr(p50s), dec),
			std_dev: round(stdDev(p50s), dec),
			min: round(p50s[0], dec),
			max: round(p50s[p50s.length - 1], dec),
			runs_p50: p50s.map((v) => round(v, dec)),
		};
	}

	return {
		config: runs[0].config,
		vitals,
		samples_total: runs.reduce((s, r) => s + (r.samples || 0), 0),
		runs_count: runs.length,
	};
}

/**
 * Compute the "noise floor" from N baseline runs: the maximum |delta| between
 * any two baseline runs' p50 values for each vital. Differences between plugin
 * configurations smaller than the noise floor are within measurement noise
 * and should not be interpreted as real differences.
 *
 * @param {object[]} baselineRuns - Array of per-run baseline summaries.
 * @returns {object} Noise floor per vital { lcp, ttfb, fcp, runs }.
 */
export function computeNoiseFloor(baselineRuns) {
	if (!baselineRuns || baselineRuns.length < 2) {
		return { lcp: 0, ttfb: 0, fcp: 0, runs: baselineRuns?.length || 0 };
	}
	const floor = {};
	for (const vital of ['lcp', 'ttfb', 'fcp']) {
		const vals = baselineRuns.map((r) => r.vitals[vital].p50);
		floor[vital] = round(Math.max(...vals) - Math.min(...vals), 1);
	}
	floor.runs = baselineRuns.length;
	return floor;
}

/** Median of a pre-sorted array. */
function median(sorted) {
	if (sorted.length === 0) return 0;
	const mid = Math.floor(sorted.length / 2);
	return sorted.length % 2 ? sorted[mid] : (sorted[mid - 1] + sorted[mid]) / 2;
}

/** Interquartile range (Q3 - Q1) of a pre-sorted array. */
function iqr(sorted) {
	if (sorted.length < 4) {
		return sorted.length > 0 ? sorted[sorted.length - 1] - sorted[0] : 0;
	}
	const q1 = sorted[Math.floor(sorted.length * 0.25)];
	const q3 = sorted[Math.floor(sorted.length * 0.75)];
	return q3 - q1;
}

/** Population standard deviation. */
function stdDev(values) {
	if (values.length < 2) return 0;
	const mean = values.reduce((a, b) => a + b, 0) / values.length;
	const variance = values.reduce((a, b) => a + (b - mean) ** 2, 0) / values.length;
	return Math.sqrt(variance);
}

/** Round to N decimal places. */
function round(v, decimals = 0) {
	const f = Math.pow(10, decimals);
	return Math.round(v * f) / f;
}
