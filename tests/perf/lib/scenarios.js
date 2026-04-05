/**
 * Composable scenario builders for k6 tests.
 *
 * Factory functions that return k6 scenario configuration objects.
 * Mix and match to create any test composition.
 *
 * Usage:
 *   import { browsingScenario, botScenario, mergeScenarios } from './lib/scenarios.js';
 *   export const options = mergeScenarios(
 *     browsingScenario('browse', '5m', 20),
 *     botScenario('bots', '5m', 3),
 *   );
 */

/**
 * Browsing scenario — simulates human visitors browsing pages.
 * Uses ramping-vus executor for realistic traffic ramp.
 *
 * @param {string} name     - Scenario name.
 * @param {string} duration - Total duration (e.g., '5m', '1h').
 * @param {number} maxVUs   - Peak number of virtual users.
 * @returns {object} k6 scenarios config fragment.
 */
export function browsingScenario(name, duration, maxVUs) {
	// Parse duration to seconds for stage calculation.
	const totalSec = parseDuration(duration);
	const rampSec = Math.max(Math.floor(totalSec * 0.15), 10);
	const holdSec = totalSec - rampSec * 2;

	return {
		[name]: {
			executor: 'ramping-vus',
			startVUs: 0,
			stages: [
				{ duration: `${rampSec}s`, target: maxVUs },
				{ duration: `${holdSec}s`, target: maxVUs },
				{ duration: `${rampSec}s`, target: 0 },
			],
			gracefulRampDown: '10s',
			exec: 'browsing',
			tags: { scenario_type: 'browsing' },
		},
	};
}

/**
 * Shopping scenario — simulates WooCommerce checkout flows.
 * Uses per-vu-iterations for controlled checkout count.
 *
 * @param {string} name       - Scenario name.
 * @param {string} duration   - Max duration.
 * @param {number} vus        - Number of shopping VUs.
 * @param {number} iterations - Checkouts per VU. Default 3.
 * @returns {object}
 */
export function shoppingScenario(name, duration, vus, iterations = 3) {
	return {
		[name]: {
			executor: 'per-vu-iterations',
			vus,
			iterations,
			maxDuration: duration,
			exec: 'shopping',
			tags: { scenario_type: 'shopping' },
		},
	};
}

/**
 * Bot scenario — simulates crawler/bot traffic.
 * Uses constant-vus for steady bot crawling.
 *
 * @param {string} name     - Scenario name.
 * @param {string} duration - Duration.
 * @param {number} vus      - Number of bot VUs.
 * @returns {object}
 */
export function botScenario(name, duration, vus) {
	return {
		[name]: {
			executor: 'constant-vus',
			vus,
			duration,
			exec: 'botCrawl',
			tags: { scenario_type: 'bot' },
		},
	};
}

/**
 * Continuous scenario — always-on low-rate traffic.
 * Uses constant-arrival-rate for steady request flow.
 *
 * @param {string} name          - Scenario name.
 * @param {number} ratePerMinute - Requests per minute.
 * @param {string} [duration]    - Duration. Default '24h' (effectively forever).
 * @returns {object}
 */
export function continuousScenario(name, ratePerMinute, duration = '24h') {
	return {
		[name]: {
			executor: 'constant-arrival-rate',
			rate: ratePerMinute,
			timeUnit: '1m',
			duration,
			preAllocatedVUs: Math.max(Math.ceil(ratePerMinute / 2), 2),
			maxVUs: ratePerMinute * 2,
			exec: 'browsing',
			tags: { scenario_type: 'continuous' },
		},
	};
}

/**
 * Verification scenario — runs once after traffic scenarios.
 * Used for data accuracy checks.
 *
 * @param {string} name      - Scenario name.
 * @param {string} startTime - When to start (e.g., '3m10s').
 * @returns {object}
 */
export function verifyScenario(name, startTime) {
	return {
		[name]: {
			executor: 'shared-iterations',
			vus: 1,
			iterations: 1,
			startTime,
			exec: 'verify',
			tags: { scenario_type: 'verify' },
		},
	};
}

/**
 * Merge multiple scenario fragments into a single options object.
 *
 * @param {...object} scenarios - Scenario config fragments.
 * @returns {{ scenarios: object }} k6 options-compatible object.
 */
export function mergeScenarios(...scenarios) {
	const merged = {};
	for (const s of scenarios) {
		Object.assign(merged, s);
	}
	return { scenarios: merged };
}

/**
 * Parse a duration string (e.g., '5m', '1h', '30s') to seconds.
 */
function parseDuration(duration) {
	const match = duration.match(/^(\d+)(s|m|h)$/);
	if (!match) return 300; // Default 5 minutes.
	const value = parseInt(match[1], 10);
	switch (match[2]) {
		case 's':
			return value;
		case 'm':
			return value * 60;
		case 'h':
			return value * 3600;
		default:
			return 300;
	}
}
