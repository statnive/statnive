/**
 * Report generators for analytics validation tests.
 *
 * Generates JSON and console-formatted comparison reports.
 * Plugin-agnostic — works with any adapter results.
 */

import { TEST_RUN_ID } from './config.js';

/**
 * Calculate correlation ratio between expected and actual counts.
 * Ported from statnive/tests/e2e/correlation.ts.
 *
 * @param {number} expected - Ground truth count.
 * @param {number} actual   - Analytics plugin count.
 * @returns {number} Ratio 0.0-1.0 (1.0 = perfect match).
 */
export function correlationRatio(expected, actual) {
	if (expected === 0) {
		return actual === 0 ? 1.0 : 0.0;
	}
	return Math.min(actual / expected, 1.0);
}

/**
 * Calculate accuracy percentage.
 *
 * @param {number} expected
 * @param {number} actual
 * @returns {number} Accuracy 0-100.
 */
export function accuracy(expected, actual) {
	if (expected === 0) return actual === 0 ? 100 : 0;
	const diff = Math.abs(expected - actual);
	return Math.max(0, (1 - diff / expected) * 100);
}

/**
 * Build a cross-plugin comparison report.
 *
 * @param {object} groundTruth     - { total_hits, unique_profiles, bot_hits, ... }
 * @param {object} pluginResults   - { plugin_name: { hits, visitors, ... }, ... }
 * @param {string} from            - Start date.
 * @param {string} to              - End date.
 * @returns {object} Comparison report.
 */
export function buildComparisonReport(groundTruth, pluginResults, from, to) {
	const plugins = {};

	for (const [name, data] of Object.entries(pluginResults)) {
		const hitAccuracy = accuracy(groundTruth.total_hits, data.hits || 0);
		const visitorAccuracy = accuracy(
			groundTruth.unique_profiles,
			data.visitors || 0
		);

		plugins[name] = {
			hits: data.hits || 0,
			visitors: data.visitors || 0,
			sessions: data.sessions || 0,
			hit_accuracy: parseFloat(hitAccuracy.toFixed(2)),
			visitor_accuracy: parseFloat(visitorAccuracy.toFixed(2)),
			correlation: parseFloat(
				correlationRatio(groundTruth.total_hits, data.hits || 0).toFixed(4)
			),
			raw: data,
		};
	}

	return {
		test_run_id: TEST_RUN_ID,
		date_range: { from, to },
		timestamp: new Date().toISOString(),
		ground_truth: groundTruth,
		plugins,
	};
}

/**
 * Format comparison report as console-friendly table.
 *
 * @param {object} report - From buildComparisonReport().
 * @returns {string} Formatted string.
 */
export function consoleReport(report) {
	const lines = [
		'',
		'╔══════════════════════════════════════════════════════════════╗',
		'║            ANALYTICS ACCURACY COMPARISON REPORT            ║',
		'╚══════════════════════════════════════════════════════════════╝',
		'',
		`  Test Run:    ${report.test_run_id}`,
		`  Date Range:  ${report.date_range.from} → ${report.date_range.to}`,
		`  Timestamp:   ${report.timestamp}`,
		'',
		'  GROUND TRUTH',
		`  ─────────────────────────────────────`,
		`  Total Hits:      ${report.ground_truth.total_hits}`,
		`  Unique Profiles: ${report.ground_truth.unique_profiles}`,
		`  Bot Hits:        ${report.ground_truth.bot_hits}`,
		`  Logged-In Hits:  ${report.ground_truth.logged_in_hits}`,
		'',
		'  PLUGIN RESULTS',
		'  ─────────────────────────────────────────────────────────',
		'  Plugin                  Hits    Visitors  Hit Acc%  Vis Acc%',
		'  ─────────────────────────────────────────────────────────',
	];

	for (const [name, data] of Object.entries(report.plugins)) {
		const pad = (s, n) => String(s).padStart(n);
		lines.push(
			`  ${name.padEnd(24)} ${pad(data.hits, 6)}  ${pad(data.visitors, 8)}  ${pad(data.hit_accuracy.toFixed(1), 7)}  ${pad(data.visitor_accuracy.toFixed(1), 7)}`
		);
	}

	lines.push('  ─────────────────────────────────────────────────────────');
	lines.push('');

	return lines.join('\n');
}

/**
 * Generate handleSummary output for k6 with comparison report.
 *
 * @param {object} k6Data - k6 summary data.
 * @param {object} report - From buildComparisonReport().
 * @returns {object} k6 handleSummary return value.
 */
export function generateSummaryOutput(k6Data, report) {
	const timestamp = Date.now();
	const reportJson = JSON.stringify(report, null, 2);
	const consoleTxt = consoleReport(report);

	return {
		stdout: consoleTxt,
		[`./results/comparison-${timestamp}.json`]: reportJson,
	};
}
