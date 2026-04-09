#!/usr/bin/env node
/**
 * Compare two perf-impact summary JSON files and report only the deltas
 * that are statistically significant — i.e., larger than the combined
 * noise floor of both test batches.
 *
 * Phase 1 P2 of jaan-to/outputs/ROADMAP-PERFORMANCE.md.
 *
 * Use case: "I ran the benchmark before and after an optimization. Which
 * plugin impact scores actually changed, versus which look different but
 * are within measurement noise?"
 *
 * Usage:
 *   node compare-runs.mjs <summary-a.json> <summary-b.json>
 *
 * The "A" summary is treated as the baseline for the diff — a negative
 * LCP change means "B is faster than A" (improvement for that plugin),
 * a positive LCP change means "B is slower than A" (regression).
 */

import fs from 'node:fs';
import path from 'node:path';

const args = process.argv.slice(2);
if (args.length !== 2) {
	console.error('Usage: compare-runs.mjs <summary-a.json> <summary-b.json>');
	console.error('');
	console.error('  A and B are both summary-*.json files produced by');
	console.error('  perf-impact-runner.sh (Phase 1 multi-run aggregator).');
	console.error('  The report shows how each plugin\'s delta changed from');
	console.error('  A to B, and flags whether the change is significant');
	console.error('  (outside the combined noise floor of both batches).');
	process.exit(1);
}

const [pathA, pathB] = args;

function loadSummary(p) {
	if (!fs.existsSync(p)) {
		console.error(`  ERROR: summary file not found: ${p}`);
		process.exit(1);
	}
	try {
		return JSON.parse(fs.readFileSync(p, 'utf8'));
	} catch (err) {
		console.error(`  ERROR: failed to parse ${p}: ${err.message}`);
		process.exit(1);
	}
}

const a = loadSummary(pathA);
const b = loadSummary(pathB);

// ---------------------------------------------------------------------------
// Validate schema — both files must be Phase 1+ multi-run summaries.
// ---------------------------------------------------------------------------
function hasMultiRunSchema(s) {
	return (
		s &&
		typeof s.runs_count === 'number' &&
		s.noise_floor &&
		typeof s.noise_floor.lcp === 'number' &&
		s.plugins &&
		typeof s.plugins === 'object'
	);
}

if (!hasMultiRunSchema(a)) {
	console.error(`  ERROR: ${pathA} is not a multi-run summary (missing runs_count / noise_floor / plugins).`);
	console.error(`  Re-run with RUNS=N to produce a multi-run summary.`);
	process.exit(1);
}
if (!hasMultiRunSchema(b)) {
	console.error(`  ERROR: ${pathB} is not a multi-run summary (missing runs_count / noise_floor / plugins).`);
	console.error(`  Re-run with RUNS=N to produce a multi-run summary.`);
	process.exit(1);
}

// ---------------------------------------------------------------------------
// Combined noise floor: the LARGER of the two batches' noise floors per vital.
// This is the conservative threshold — a change is only "significant" if it
// exceeds the noisier of the two environments.
// ---------------------------------------------------------------------------
const combinedNoise = {
	lcp: Math.max(a.noise_floor.lcp || 0, b.noise_floor.lcp || 0),
	ttfb: Math.max(a.noise_floor.ttfb || 0, b.noise_floor.ttfb || 0),
	fcp: Math.max(a.noise_floor.fcp || 0, b.noise_floor.fcp || 0),
};

// ---------------------------------------------------------------------------
// Build per-plugin comparison rows.
// ---------------------------------------------------------------------------
const configNames = new Set([
	...Object.keys(a.plugins),
	...Object.keys(b.plugins),
]);

const rows = [];
for (const cfg of configNames) {
	const pa = a.plugins[cfg];
	const pb = b.plugins[cfg];

	if (!pa && !pb) continue;

	// Only-in-A or only-in-B plugins get reported separately (configuration drift).
	if (!pa || !pb) {
		rows.push({
			config: cfg,
			only_in: pa ? 'A' : 'B',
			lcp_a: pa?.delta?.lcp ?? null,
			lcp_b: pb?.delta?.lcp ?? null,
			ttfb_a: pa?.delta?.ttfb ?? null,
			ttfb_b: pb?.delta?.ttfb ?? null,
			fcp_a: pa?.delta?.fcp ?? null,
			fcp_b: pb?.delta?.fcp ?? null,
			score_a: pa?.impact_score ?? null,
			score_b: pb?.impact_score ?? null,
		});
		continue;
	}

	// Change in delta between A and B, per vital.
	// A positive lcp_change means "B's LCP delta grew" => regression.
	// A negative lcp_change means "B's LCP delta shrank" => improvement.
	const lcpChange = pb.delta.lcp - pa.delta.lcp;
	const ttfbChange = pb.delta.ttfb - pa.delta.ttfb;
	const fcpChange = pb.delta.fcp - pa.delta.fcp;
	const scoreChange = pb.impact_score - pa.impact_score;

	rows.push({
		config: cfg,
		only_in: null,
		lcp_a: pa.delta.lcp,
		lcp_b: pb.delta.lcp,
		lcp_change: lcpChange,
		ttfb_a: pa.delta.ttfb,
		ttfb_b: pb.delta.ttfb,
		ttfb_change: ttfbChange,
		fcp_a: pa.delta.fcp,
		fcp_b: pb.delta.fcp,
		fcp_change: fcpChange,
		score_a: pa.impact_score,
		score_b: pb.impact_score,
		score_change: scoreChange,
		significant: {
			lcp: Math.abs(lcpChange) > combinedNoise.lcp,
			ttfb: Math.abs(ttfbChange) > combinedNoise.ttfb,
			fcp: Math.abs(fcpChange) > combinedNoise.fcp,
		},
	});
}

// Sort: significant LCP changes first (regressions, then improvements),
// then insignificant ones, then only-in rows last.
rows.sort((x, y) => {
	if (x.only_in && !y.only_in) return 1;
	if (!x.only_in && y.only_in) return -1;
	if (x.only_in && y.only_in) return x.config.localeCompare(y.config);
	const xSig = x.significant.lcp ? 1 : 0;
	const ySig = y.significant.lcp ? 1 : 0;
	if (xSig !== ySig) return ySig - xSig; // significant first
	return (y.lcp_change || 0) - (x.lcp_change || 0); // biggest change first
});

// ---------------------------------------------------------------------------
// Console output.
// ---------------------------------------------------------------------------
console.log('');
console.log('  ╔══════════════════════════════════════════════════════════════════════════════════╗');
console.log('  ║                    PERFORMANCE IMPACT — RUN-TO-RUN COMPARISON                   ║');
console.log('  ╚══════════════════════════════════════════════════════════════════════════════════╝');
console.log('');
console.log(`  A:  ${path.basename(pathA)}`);
console.log(`      runs=${a.runs_count}  tier=${a.load_tier}  timestamp=${a.timestamp}`);
console.log(`  B:  ${path.basename(pathB)}`);
console.log(`      runs=${b.runs_count}  tier=${b.load_tier}  timestamp=${b.timestamp}`);
console.log('');
console.log('  Combined noise floor (max of A and B, used as significance threshold):');
console.log(`    LCP ±${Math.round(combinedNoise.lcp)}ms  |  TTFB ±${Math.round(combinedNoise.ttfb)}ms  |  FCP ±${Math.round(combinedNoise.fcp)}ms`);
console.log('');
console.log('  A positive "Change" means B is SLOWER than A (regression).');
console.log('  A negative "Change" means B is FASTER than A (improvement).');
console.log('  "Significant?" = YES if |Change| > combined noise floor for that vital.');
console.log('');

// Main table.
console.log('  ┌───────────────────────────┬──────────┬──────────┬──────────┬─────────┬────────┐');
console.log('  │ Plugin                    │ LCP A    │ LCP B    │ Change   │  Score  │  Sig?  │');
console.log('  │                           │ (delta)  │ (delta)  │  (B-A)   │ Δ (B-A) │ (LCP)  │');
console.log('  ├───────────────────────────┼──────────┼──────────┼──────────┼─────────┼────────┤');

const fmtMs = (v) => {
	if (v === null || v === undefined) return '      --';
	const s = (v >= 0 ? '+' : '') + Math.round(v);
	return (s + 'ms').padStart(8);
};
const fmtScore = (v) => {
	if (v === null || v === undefined) return '      --';
	return ((v >= 0 ? '+' : '') + v.toFixed(1)).padStart(7);
};

let sigCount = 0;
let onlyInCount = 0;
for (const row of rows) {
	if (row.only_in) {
		onlyInCount++;
		const note = `  only in ${row.only_in}`.padStart(8);
		console.log(
			`  │ ${row.config.padEnd(25)} │ ${fmtMs(row.lcp_a)} │ ${fmtMs(row.lcp_b)} │${note} │${fmtScore(row.score_a ?? row.score_b)} │        │`
		);
		continue;
	}
	if (row.significant.lcp) sigCount++;
	const sigStr = row.significant.lcp ? '  YES   ' : '   no   ';
	console.log(
		`  │ ${row.config.padEnd(25)} │ ${fmtMs(row.lcp_a)} │ ${fmtMs(row.lcp_b)} │ ${fmtMs(row.lcp_change)} │${fmtScore(row.score_change)} │${sigStr}│`
	);
}
console.log('  └───────────────────────────┴──────────┴──────────┴──────────┴─────────┴────────┘');
console.log('');

// Summary.
const commonCount = rows.length - onlyInCount;
console.log(`  ${commonCount} plugin(s) compared, ${sigCount} significant LCP change(s), ${onlyInCount} configuration drift.`);
if (sigCount === 0 && commonCount > 0) {
	console.log('  No plugin showed a LCP change larger than the combined noise floor.');
	console.log('  Either the two runs are statistically indistinguishable, or the noise');
	console.log('  floor is too wide to detect the change. Consider running more iterations.');
}
console.log('');

// Exit code signals whether significant changes were found: 0 = no significant
// change, 1 = at least one significant change. Useful for CI scripts that want
// to block releases on perf regressions.
process.exit(sigCount > 0 ? 1 : 0);
