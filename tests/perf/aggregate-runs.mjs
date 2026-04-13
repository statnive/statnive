#!/usr/bin/env node
/**
 * Aggregate N per-run result directories into a single summary file with
 * median-of-medians, IQR, standard deviation, and a "noise floor" computed
 * from the baseline runs.
 *
 * Called by perf-impact-runner.sh after the outer RUNS loop completes.
 *
 * Phase 1 + 2 of jaan-to/outputs/ROADMAP-PERFORMANCE.md.
 *
 * Usage:
 *   node aggregate-runs.mjs <results-dir> <load-tier> <batch-ts> <run-dir-1> [<run-dir-2> ...]
 *
 * Each run directory contains per-config JSON files produced by
 * perf-impact-test.js handleSummary() (which writes via generatePerfSummaryOutput).
 */

import fs from 'node:fs';
import path from 'node:path';
import { aggregateRuns, computeNoiseFloor } from './lib/perf-reporter.js';

const args = process.argv.slice(2);
if (args.length < 4) {
	console.error('Usage: aggregate-runs.mjs <results-dir> <load-tier> <batch-ts> <run-dir-1> [<run-dir-2> ...]');
	process.exit(1);
}

const [resultsDir, loadTier, batchTs, ...runDirs] = args;

// ---------------------------------------------------------------------------
// Load all per-run per-config JSON files and bucket them by config label.
// ---------------------------------------------------------------------------
const byConfig = {}; // configName -> [run1_summary, run2_summary, ...]

for (const runDir of runDirs) {
	if (!fs.existsSync(runDir) || !fs.statSync(runDir).isDirectory()) {
		console.error(`  Warning: run directory not found or not a directory: ${runDir}`);
		continue;
	}
	const files = fs
		.readdirSync(runDir)
		.filter((f) => f.endsWith('.json') && !f.startsWith('summary-'))
		.sort();

	for (const f of files) {
		try {
			const data = JSON.parse(fs.readFileSync(path.join(runDir, f), 'utf8'));
			const cfg = data.config;
			if (!cfg) continue;
			if (!byConfig[cfg]) byConfig[cfg] = [];
			byConfig[cfg].push(data);
		} catch (err) {
			console.error(`  Warning: failed to parse ${f}: ${err.message}`);
		}
	}
}

if (Object.keys(byConfig).length === 0) {
	console.error('  ERROR: No per-config JSON files found in any run directory.');
	process.exit(1);
}

// ---------------------------------------------------------------------------
// Aggregate each config across N runs.
// ---------------------------------------------------------------------------
const aggregated = {};
for (const [cfg, runs] of Object.entries(byConfig)) {
	aggregated[cfg] = aggregateRuns(runs);
}

const baselineAgg = aggregated['baseline'];
if (!baselineAgg) {
	console.error('  ERROR: No baseline runs found. Cannot compute deltas.');
	process.exit(1);
}

// ---------------------------------------------------------------------------
// Compute noise floor from baseline runs.
// ---------------------------------------------------------------------------
const noiseFloor = computeNoiseFloor(byConfig['baseline'] || []);

// ---------------------------------------------------------------------------
// Compute deltas, impact scores, and within-noise-floor flags.
// ---------------------------------------------------------------------------
const round = (v, d = 1) => Math.round(v * Math.pow(10, d)) / Math.pow(10, d);

const plugins = {};
for (const [cfg, agg] of Object.entries(aggregated)) {
	if (cfg === 'baseline') continue;

	const delta = {};
	const deltaPct = {};
	for (const v of ['ttfb', 'fcp', 'lcp', 'cls', 'inp']) {
		const bVal = baselineAgg.vitals[v].median;
		const pVal = agg.vitals[v].median;
		const diff = pVal - bVal;
		delta[v] = round(diff, v === 'cls' ? 4 : 1);
		deltaPct[v] = bVal > 0 ? round((diff / bVal) * 100, 1) : 0;
	}

	// Impact score — same formula and weights as lib/perf-reporter.js impactScore().
	const bTTFB = Math.max(baselineAgg.vitals.ttfb.median, 1);
	const bLCP = Math.max(baselineAgg.vitals.lcp.median, 1);
	const bFCP = Math.max(baselineAgg.vitals.fcp.median, 1);
	const score = round(
		(
			(Math.max(0, delta.ttfb) / bTTFB) * 0.25 +
			(Math.max(0, delta.lcp) / bLCP) * 0.3 +
			(Math.max(0, delta.fcp) / bFCP) * 0.2 +
			(Math.max(0, delta.cls) / 0.1) * 0.15 +
			(Math.max(0, delta.inp) / 200) * 0.1
		) * 100,
		1
	);

	// Mark deltas within the noise floor — cannot be trusted as real differences.
	const withinNoise = {};
	for (const v of ['lcp', 'ttfb', 'fcp']) {
		withinNoise[v] = Math.abs(delta[v]) <= (noiseFloor[v] || 0);
	}

	plugins[cfg] = {
		vitals: agg.vitals,
		delta,
		delta_pct: deltaPct,
		impact_score: score,
		within_noise_floor: withinNoise,
		runs_count: agg.runs_count,
		samples_total: agg.samples_total,
	};
}

// ---------------------------------------------------------------------------
// Rank by impact score (lowest first = least overhead).
// ---------------------------------------------------------------------------
const ranking = Object.entries(plugins)
	.sort((a, b) => a[1].impact_score - b[1].impact_score)
	.map(([n]) => n);

// ---------------------------------------------------------------------------
// Write aggregated summary JSON.
// ---------------------------------------------------------------------------
const summary = {
	timestamp: new Date().toISOString(),
	load_tier: loadTier,
	runs_count: runDirs.length,
	run_dirs: runDirs.map((d) => path.basename(d)),
	noise_floor: noiseFloor,
	baseline: baselineAgg,
	plugins,
	ranking,
};

const outPath = path.join(resultsDir, `summary-${batchTs}.json`);
fs.writeFileSync(outPath, JSON.stringify(summary, null, 2));

// ---------------------------------------------------------------------------
// Console output.
// ---------------------------------------------------------------------------
console.log('');
console.log('  ╔══════════════════════════════════════════════════════════════════════════════════╗');
console.log('  ║                    PERFORMANCE IMPACT — MULTI-RUN AGGREGATE                     ║');
console.log('  ╚══════════════════════════════════════════════════════════════════════════════════╝');
console.log('');
console.log(`  Load Tier:    ${loadTier}`);
console.log(`  Runs:         ${runDirs.length}`);
console.log(
	`  Baseline:     LCP ${baselineAgg.vitals.lcp.median}ms (median)  |  TTFB ${baselineAgg.vitals.ttfb.median}ms  |  FCP ${baselineAgg.vitals.fcp.median}ms`
);
console.log(
	`  Noise floor:  LCP ±${noiseFloor.lcp}ms  |  TTFB ±${noiseFloor.ttfb}ms  |  FCP ±${noiseFloor.fcp}ms  (from ${noiseFloor.runs} baseline run${noiseFloor.runs === 1 ? '' : 's'})`
);
console.log('');
console.log('  ┌───────────────────────────┬────────────┬────────────┬────────────┬───────┬────────┐');
console.log('  │ Plugin                    │ LCP Δ      │ TTFB Δ     │ FCP Δ      │Impact │ Noise? │');
console.log('  │                           │ med ± IQR  │ med ± IQR  │ med ± IQR  │ Score │        │');
console.log('  ├───────────────────────────┼────────────┼────────────┼────────────┼───────┼────────┤');

const fmtDelta = (deltaVal, iqrVal) => {
	const sign = deltaVal >= 0 ? '+' : '';
	const str = `${sign}${Math.round(deltaVal)}±${Math.round(iqrVal)}`;
	return str.padStart(10);
};

for (const name of ranking) {
	const p = plugins[name];
	const lcpStr = fmtDelta(p.delta.lcp, p.vitals.lcp.iqr);
	const ttfbStr = fmtDelta(p.delta.ttfb, p.vitals.ttfb.iqr);
	const fcpStr = fmtDelta(p.delta.fcp, p.vitals.fcp.iqr);
	const scoreStr = p.impact_score.toFixed(1).padStart(5);
	const noiseStr = p.within_noise_floor.lcp ? '  YES ' : '  no  ';
	console.log(`  │ ${name.padEnd(25)} │ ${lcpStr} │ ${ttfbStr} │ ${fcpStr} │ ${scoreStr} │ ${noiseStr} │`);
}

console.log('  └───────────────────────────┴────────────┴────────────┴────────────┴───────┴────────┘');
console.log('');
console.log('  "Noise? = YES" means the LCP delta is within the noise floor and');
console.log('  cannot be distinguished from baseline variance.');
console.log('');
if (ranking.length > 0) {
	const best = ranking[0];
	const worst = ranking[ranking.length - 1];
	console.log(`  Lowest impact:  ${best} (score: ${plugins[best].impact_score})`);
	console.log(`  Highest impact: ${worst} (score: ${plugins[worst].impact_score})`);
	console.log('');
}
console.log(`  Summary saved to: ${outPath}`);
console.log('');
