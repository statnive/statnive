/**
 * Cross-plugin analytics accuracy comparison.
 *
 * Queries ground truth data and compares it against every installed
 * analytics plugin using the adapter pattern.
 *
 * Usage:
 *   # Run after traffic simulation
 *   k6 run tests/perf/cross-plugin-compare.js \
 *     -e BASE_URL=http://localhost:10013 \
 *     -e ADMIN_USER=root -e ADMIN_PASS=q1w2e3
 *
 *   # Discover installed plugins only
 *   k6 run tests/perf/cross-plugin-compare.js -e MODE=discover
 */

import http from 'k6/http';
import { check, sleep } from 'k6';
import { Trend } from 'k6/metrics';
import { BASE_URL, ADMIN_USER, ADMIN_PASS, MODE, TEST_RUN_ID } from './lib/config.js';
import { authenticate } from './lib/wp-auth.js';
import { getGroundTruth, getGroundTruthByChannel } from './lib/ground-truth.js';
import { getInstalledAdapters } from './adapters/index.js';
import { buildComparisonReport, consoleReport } from './lib/reporters.js';

const comparisonLatency = new Trend('comparison_query_latency');

export const options = {
	scenarios: {
		compare: {
			executor: 'shared-iterations',
			vus: 1,
			iterations: 1,
			maxDuration: '5m',
		},
	},
};

export function setup() {
	const auth = authenticate(ADMIN_USER, ADMIN_PASS, BASE_URL);
	if (!auth.success) {
		console.error('Authentication failed. Cannot run comparison.');
		return { headers: {}, success: false };
	}
	return { headers: auth.headers, success: true };
}

export default function (setupData) {
	if (!setupData.success) {
		console.error('Skipping — no auth.');
		return;
	}

	const headers = setupData.headers;
	const today = new Date().toISOString().slice(0, 10);
	const weekAgo = new Date(Date.now() - 7 * 86400000).toISOString().slice(0, 10);

	// Step 1: Discover installed plugins.
	console.log('\nDiscovering installed analytics plugins...');
	const adapters = getInstalledAdapters(BASE_URL, headers);
	console.log(`Found ${adapters.length} plugin(s): ${adapters.map((a) => a.name).join(', ')}`);

	if (MODE === 'discover') {
		console.log('\nDiscovery mode — exiting.');
		return;
	}

	// Step 2: Get ground truth data.
	console.log('\nFetching ground truth...');
	const gt = getGroundTruth(weekAgo, today, headers);
	if (!gt) {
		console.error('No ground truth data found. Run simulate-traffic.js first.');
		return;
	}
	console.log(`Ground truth: ${gt.total_hits} hits, ${gt.unique_profiles} profiles`);

	// Step 3: Query each installed plugin.
	console.log('\nQuerying plugins...');
	const pluginResults = {};

	for (const adapter of adapters) {
		const start = Date.now();
		try {
			const totals = adapter.getTotals(BASE_URL, headers, weekAgo, today);
			comparisonLatency.add(Date.now() - start);

			if (totals) {
				pluginResults[adapter.name] = totals;
				console.log(
					`  ${adapter.name}: ${totals.hits} hits, ${totals.visitors} visitors, ${totals.sessions} sessions`
				);
			} else {
				console.warn(`  ${adapter.name}: No data returned.`);
				pluginResults[adapter.name] = { hits: 0, visitors: 0, sessions: 0 };
			}
		} catch (err) {
			console.error(`  ${adapter.name}: Error — ${err}`);
			pluginResults[adapter.name] = { hits: 0, visitors: 0, sessions: 0, error: String(err) };
		}
	}

	// Step 4: Build and display comparison report.
	const report = buildComparisonReport(gt, pluginResults, weekAgo, today);
	const reportText = consoleReport(report);
	console.log(reportText);

	// Step 5: Verify accuracy thresholds.
	for (const [name, data] of Object.entries(report.plugins)) {
		check(data, {
			[`${name} hit accuracy > 0%`]: (d) => d.hit_accuracy > 0,
		});
	}

	console.log(`\nFull report saved by handleSummary to results/ directory.`);
}

export function handleSummary(data) {
	// This will be called by k6 after the test completes.
	const timestamp = Date.now();
	return {
		[`./results/comparison-${timestamp}.json`]: JSON.stringify(data, null, 2),
	};
}
