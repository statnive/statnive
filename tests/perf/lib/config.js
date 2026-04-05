/**
 * Central configuration loader for the perf test framework.
 *
 * Reads from k6 environment variables (__ENV) with sensible defaults.
 * All test scripts import config from this single source of truth.
 *
 * Usage:
 *   k6 run script.js -e BASE_URL=http://localhost:10013 -e ADMIN_USER=root
 */

/** WordPress site base URL. */
export const BASE_URL = __ENV.BASE_URL || 'http://localhost:10013';

/** WordPress admin credentials. */
export const ADMIN_USER = __ENV.ADMIN_USER || 'root';
export const ADMIN_PASS = __ENV.ADMIN_PASS || 'q1w2e3';

/** HMAC secret for Statnive tracker signature. */
export const HMAC_SECRET = __ENV.HMAC_SECRET || '';

/** REST API base URL. */
export const REST_URL = `${BASE_URL}/wp-json`;

/** WP nonce (set by run.sh or manually). */
export const WP_NONCE = __ENV.WP_NONCE || '';

/** Admin cookie string (set by run.sh after login). */
export const ADMIN_COOKIE = __ENV.ADMIN_COOKIE || '';

/**
 * Test mode:
 * - 'continuous'  — low-rate always-on traffic (runs indefinitely)
 * - 'burst'       — high-volume short burst
 * - 'accuracy'    — deterministic validation (100 known hits)
 * - 'browser'     — real browser simulation (k6 browser module)
 * - 'compare'     — cross-plugin comparison
 */
export const MODE = __ENV.MODE || 'burst';

/** Hits per minute in continuous mode. */
export const RATE = parseInt(__ENV.RATE || '3', 10);

/** Configuration label for perf-impact tests (e.g. 'baseline', 'statnive'). */
export const CONFIG_LABEL = __ENV.CONFIG_LABEL || 'unknown';

/** Load tier for perf-impact tests: light, medium, heavy. */
export const LOAD_TIER = __ENV.LOAD_TIER || 'medium';

/** Number of page-visit rounds per browser VU in perf-impact tests. */
export const ITERATIONS_PER_VU = parseInt(__ENV.ITERATIONS_PER_VU || '3', 10);

/** Comma-separated list of plugins to compare. */
export const PLUGINS_UNDER_TEST = (__ENV.PLUGINS_UNDER_TEST || 'statnive')
	.split(',')
	.map((p) => p.trim())
	.filter(Boolean);

/** Unique test run ID for ground truth correlation. */
export const TEST_RUN_ID =
	__ENV.TEST_RUN_ID || `run-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;

/** Ground truth API base URL. */
export const GROUND_TRUTH_URL = `${REST_URL}/ground-truth/v1`;

/**
 * Pages available on the target site.
 * Override via PAGES_JSON env var for custom sites.
 */
export const PAGES = __ENV.PAGES_JSON
	? JSON.parse(__ENV.PAGES_JSON)
	: [
			{ type: 'page', id: 2 },
			{ type: 'page', id: 3 },
			{ type: 'page', id: 4 },
			{ type: 'page', id: 5 },
			{ type: 'page', id: 6 },
			{ type: 'post', id: 7 },
			{ type: 'post', id: 8 },
			{ type: 'post', id: 9 },
			{ type: 'post', id: 10 },
			{ type: 'post', id: 11 },
		];
