/**
 * Ground truth recording client for k6 tests.
 *
 * Communicates with the standalone ground-truth mu-plugin
 * to record expected hits and retrieve validation data.
 *
 * Plugin-agnostic — records what the test framework expects,
 * not what any specific analytics plugin tracks.
 */

import http from 'k6/http';
import { GROUND_TRUTH_URL, TEST_RUN_ID } from './config.js';

/**
 * Record a single expected hit to the ground truth table.
 *
 * @param {object} hit - Expected hit data.
 * @param {string} hit.profile_id       - Visitor profile ID from CSV.
 * @param {string} hit.resource_type    - 'page' or 'post'.
 * @param {number} hit.resource_id      - WordPress post/page ID.
 * @param {string} [hit.page_url]       - Page URL path.
 * @param {string} [hit.referrer_url]   - Referrer URL.
 * @param {string} [hit.expected_channel] - Expected channel classification.
 * @param {string} [hit.utm_source]
 * @param {string} [hit.utm_medium]
 * @param {string} [hit.utm_campaign]
 * @param {string} [hit.device_type]
 * @param {boolean} [hit.is_bot]
 * @param {boolean} [hit.is_logged_in]
 * @param {string} [hit.user_agent]
 * @param {object} headers - Auth headers (from wp-auth.js).
 * @returns {boolean} True if recorded successfully.
 */
export function recordHit(hit, headers = {}) {
	const payload = JSON.stringify({
		test_run_id: TEST_RUN_ID,
		profile_id: hit.profile_id || '',
		resource_type: hit.resource_type || '',
		resource_id: hit.resource_id || 0,
		page_url: hit.page_url || '',
		referrer_url: hit.referrer_url || '',
		expected_channel: hit.expected_channel || '',
		utm_source: hit.utm_source || '',
		utm_medium: hit.utm_medium || '',
		utm_campaign: hit.utm_campaign || '',
		device_type: hit.device_type || '',
		is_bot: hit.is_bot || false,
		is_logged_in: hit.is_logged_in || false,
		user_agent: hit.user_agent || '',
	});

	const res = http.post(`${GROUND_TRUTH_URL}/record`, payload, {
		headers: {
			'Content-Type': 'application/json',
			...headers,
		},
	});

	return res.status === 201;
}

/**
 * Get ground truth summary for a date range.
 *
 * @param {string} from - Start date (YYYY-MM-DD).
 * @param {string} to   - End date (YYYY-MM-DD).
 * @param {object} headers - Auth headers.
 * @returns {object|null} Summary data or null on failure.
 */
export function getGroundTruth(from, to, headers = {}) {
	const url = `${GROUND_TRUTH_URL}/summary?from=${from}&to=${to}&test_run_id=${TEST_RUN_ID}`;
	const res = http.get(url, { headers });

	if (res.status === 200) {
		return JSON.parse(res.body);
	}
	return null;
}

/**
 * Get ground truth breakdown by expected channel.
 *
 * @param {string} from
 * @param {string} to
 * @param {object} headers
 * @returns {object[]|null}
 */
export function getGroundTruthByChannel(from, to, headers = {}) {
	const url = `${GROUND_TRUTH_URL}/by-channel?from=${from}&to=${to}&test_run_id=${TEST_RUN_ID}`;
	const res = http.get(url, { headers });

	if (res.status === 200) {
		return JSON.parse(res.body);
	}
	return null;
}

/**
 * Get ground truth breakdown by device type.
 *
 * @param {string} from
 * @param {string} to
 * @param {object} headers
 * @returns {object[]|null}
 */
export function getGroundTruthByDevice(from, to, headers = {}) {
	const url = `${GROUND_TRUTH_URL}/by-device?from=${from}&to=${to}&test_run_id=${TEST_RUN_ID}`;
	const res = http.get(url, { headers });

	if (res.status === 200) {
		return JSON.parse(res.body);
	}
	return null;
}

/**
 * Get ground truth breakdown by page.
 *
 * @param {string} from
 * @param {string} to
 * @param {object} headers
 * @returns {object[]|null}
 */
export function getGroundTruthByPage(from, to, headers = {}) {
	const url = `${GROUND_TRUTH_URL}/by-page?from=${from}&to=${to}&test_run_id=${TEST_RUN_ID}`;
	const res = http.get(url, { headers });

	if (res.status === 200) {
		return JSON.parse(res.body);
	}
	return null;
}

/**
 * Clear ground truth records for the current test run.
 *
 * @param {object} headers
 * @returns {boolean}
 */
export function clearGroundTruth(headers = {}) {
	const res = http.del(`${GROUND_TRUTH_URL}/clear?test_run_id=${TEST_RUN_ID}`, null, {
		headers,
	});
	return res.status === 200;
}
