/**
 * Koko Analytics plugin adapter.
 *
 * Lightweight, privacy-friendly analytics.
 * Tables: wp_koko_analytics_site_stats, wp_koko_analytics_post_stats,
 *         wp_koko_analytics_referrer_stats, wp_koko_analytics_referrer_urls.
 */

import http from 'k6/http';
import { createAdapter } from './adapter-base.js';

const adapter = createAdapter('koko-analytics');

adapter.isInstalled = function (baseUrl, headers) {
	// Koko Analytics uses admin-ajax, not REST.
	const res = http.get(
		`${baseUrl}/wp-admin/admin-ajax.php?action=koko_analytics_get_stats`,
		{ headers, redirects: 0 }
	);
	return res.status === 200 || res.status === 400; // 400 = missing params but plugin active.
};

adapter.getTotals = function (baseUrl, headers, from, to) {
	const res = http.get(
		`${baseUrl}/wp-admin/admin-ajax.php?action=koko_analytics_get_stats&start_date=${from}&end_date=${to}`,
		{ headers }
	);
	if (res.status !== 200) return null;

	const data = JSON.parse(res.body);
	return {
		hits: data.pageviews || 0,
		visitors: data.visitors || 0,
		sessions: 0,
	};
};

export default adapter;
