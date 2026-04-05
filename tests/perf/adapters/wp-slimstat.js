/**
 * WP Slimstat plugin adapter.
 *
 * Table: wp_slim_stats (main events table).
 * REST: /wp-json/slimstat/v1/ or admin-ajax.
 */

import http from 'k6/http';
import { createAdapter } from './adapter-base.js';

const adapter = createAdapter('wp-slimstat');

adapter.isInstalled = function (baseUrl, headers) {
	const res = http.get(`${baseUrl}/wp-json/slimstat/v1/stats`, {
		headers,
		redirects: 0,
	});
	if (res.status === 200 || res.status === 401) return true;

	// Fallback: check plugin list.
	const pluginCheck = http.get(
		`${baseUrl}/wp-json/wp/v2/plugins?search=wp-slimstat&_fields=status`,
		{ headers, redirects: 0 }
	);
	if (pluginCheck.status === 200) {
		const plugins = JSON.parse(pluginCheck.body);
		return plugins.some((p) => p.status === 'active');
	}
	return false;
};

adapter.getTotals = function (baseUrl, headers, from, to) {
	const res = http.get(
		`${baseUrl}/wp-json/slimstat/v1/stats?from=${from}&to=${to}`,
		{ headers }
	);
	if (res.status !== 200) return null;

	const data = JSON.parse(res.body);
	return {
		hits: data.pageviews || data.events || 0,
		visitors: data.visitors || data.unique_ips || 0,
		sessions: data.visits || 0,
	};
};

export default adapter;
