/**
 * Independent Analytics plugin adapter.
 *
 * Tables: wp_independent_analytics_* (views, sessions, etc.)
 * REST: /wp-json/independent-analytics/v1/
 */

import http from 'k6/http';
import { createAdapter } from './adapter-base.js';

const adapter = createAdapter('independent-analytics');

adapter.isInstalled = function (baseUrl, headers) {
	const res = http.get(`${baseUrl}/wp-json/iawp/v1/quick-stats`, {
		headers,
		redirects: 0,
	});
	return res.status === 200 || res.status === 401;
};

adapter.getTotals = function (baseUrl, headers, from, to) {
	const res = http.get(
		`${baseUrl}/wp-json/iawp/v1/quick-stats?start=${from}&end=${to}`,
		{ headers }
	);
	if (res.status !== 200) return null;

	const data = JSON.parse(res.body);
	return {
		hits: data.views || data.pageviews || 0,
		visitors: data.visitors || data.unique_visitors || 0,
		sessions: data.sessions || 0,
	};
};

export default adapter;
