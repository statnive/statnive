/**
 * Jetpack Stats plugin adapter.
 *
 * Jetpack stores stats on WordPress.com servers.
 * Uses the Jetpack REST API via WordPress.com connection.
 */

import http from 'k6/http';
import { createAdapter } from './adapter-base.js';

const adapter = createAdapter('jetpack-stats');

adapter.isInstalled = function (baseUrl, headers) {
	const res = http.get(`${baseUrl}/wp-json/jetpack/v4/module/stats`, {
		headers,
		redirects: 0,
	});
	return res.status === 200;
};

adapter.getTotals = function (baseUrl, headers, from, to) {
	// Jetpack stats via the site stats endpoint.
	const res = http.get(
		`${baseUrl}/wp-json/wpcom/v2/stats?start_date=${from}&end_date=${to}`,
		{ headers }
	);
	if (res.status !== 200) return null;

	const data = JSON.parse(res.body);
	return {
		hits: data.views || data.pageviews || 0,
		visitors: data.visitors || 0,
		sessions: 0,
	};
};

export default adapter;
