/**
 * WP Statistics plugin adapter.
 *
 * Queries WP Statistics REST API or admin-ajax endpoints.
 * Tables: wp_statistics_visits, wp_statistics_visitor, wp_statistics_pages.
 */

import http from 'k6/http';
import { createAdapter } from './adapter-base.js';

const adapter = createAdapter('wp-statistics');

adapter.isInstalled = function (baseUrl, headers) {
	// WP Statistics registers its own REST namespace.
	const res = http.get(`${baseUrl}/wp-json/wp-statistics/v2/hit?_fields=id`, {
		headers,
		redirects: 0,
	});
	// Also check via plugins API if REST fails.
	if (res.status === 200 || res.status === 401) return true;

	const pluginCheck = http.get(
		`${baseUrl}/wp-json/wp/v2/plugins?search=wp-statistics&_fields=status`,
		{ headers, redirects: 0 }
	);
	if (pluginCheck.status === 200) {
		const plugins = JSON.parse(pluginCheck.body);
		return plugins.some((p) => p.status === 'active');
	}
	return false;
};

adapter.getTotals = function (baseUrl, headers, from, to) {
	// Try the WP Statistics REST API (v14+).
	const res = http.get(
		`${baseUrl}/wp-json/wp-statistics/v2/stats?from=${from}&to=${to}`,
		{ headers }
	);
	if (res.status === 200) {
		const data = JSON.parse(res.body);
		return {
			hits: data.views || data.visits || 0,
			visitors: data.visitors || 0,
			sessions: data.visits || 0,
		};
	}

	// Fallback: admin-ajax with wp_statistics action.
	const ajaxRes = http.get(
		`${baseUrl}/wp-admin/admin-ajax.php?action=wp_statistics_summary&from=${from}&to=${to}`,
		{ headers }
	);
	if (ajaxRes.status === 200) {
		const data = JSON.parse(ajaxRes.body);
		return {
			hits: data.total_views || 0,
			visitors: data.total_visitors || 0,
			sessions: 0,
		};
	}

	return null;
};

adapter.getByChannel = function (baseUrl, headers, from, to) {
	const res = http.get(
		`${baseUrl}/wp-json/wp-statistics/v2/referrers?from=${from}&to=${to}`,
		{ headers }
	);
	if (res.status !== 200) return null;

	const data = JSON.parse(res.body);
	if (!Array.isArray(data)) return null;

	return data.map((r) => ({
		channel: r.referrer || r.domain || 'Direct',
		hits: r.views || r.count || 0,
		visitors: r.visitors || 0,
	}));
};

adapter.getByDevice = function (baseUrl, headers, from, to) {
	const res = http.get(
		`${baseUrl}/wp-json/wp-statistics/v2/browsers?from=${from}&to=${to}`,
		{ headers }
	);
	if (res.status !== 200) return null;

	const data = JSON.parse(res.body);
	if (!Array.isArray(data)) return null;

	return data.map((d) => ({
		device_type: d.platform || d.agent || 'Unknown',
		hits: d.count || 0,
		visitors: d.visitors || 0,
	}));
};

export default adapter;
