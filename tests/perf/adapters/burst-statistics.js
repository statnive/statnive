/**
 * Burst Statistics plugin adapter.
 *
 * Burst is privacy-focused (no cookies, no external services).
 * Tables: wp_burst_statistics, wp_burst_summary, wp_burst_goals.
 */

import http from 'k6/http';
import { createAdapter } from './adapter-base.js';

const adapter = createAdapter('burst-statistics');

adapter.isInstalled = function (baseUrl, headers) {
	const res = http.get(`${baseUrl}/wp-json/burst/v1/data/statistics`, {
		headers,
		redirects: 0,
	});
	return res.status === 200 || res.status === 401;
};

adapter.getTotals = function (baseUrl, headers, from, to) {
	const res = http.get(
		`${baseUrl}/wp-json/burst/v1/data/statistics?date_start=${from}&date_end=${to}`,
		{ headers }
	);
	if (res.status !== 200) return null;

	const data = JSON.parse(res.body);
	return {
		hits: data.pageviews || data.hits || 0,
		visitors: data.visitors || data.unique_visitors || 0,
		sessions: data.sessions || 0,
	};
};

adapter.getByChannel = function (baseUrl, headers, from, to) {
	const res = http.get(
		`${baseUrl}/wp-json/burst/v1/data/referrers?date_start=${from}&date_end=${to}`,
		{ headers }
	);
	if (res.status !== 200) return null;

	const data = JSON.parse(res.body);
	if (!Array.isArray(data)) return null;

	return data.map((r) => ({
		channel: r.referrer || r.source || 'Direct',
		hits: r.pageviews || r.count || 0,
		visitors: r.visitors || 0,
	}));
};

export default adapter;
