/**
 * Statnive analytics plugin adapter.
 *
 * Queries Statnive's REST API for comparison data.
 */

import http from 'k6/http';
import { createAdapter } from './adapter-base.js';

const adapter = createAdapter('statnive');

adapter.isInstalled = function (baseUrl, headers) {
	const res = http.get(`${baseUrl}/wp-json/statnive/v1/summary?from=2000-01-01&to=2000-01-01`, {
		headers,
		redirects: 0,
	});
	return res.status === 200;
};

adapter.getTotals = function (baseUrl, headers, from, to) {
	const res = http.get(`${baseUrl}/wp-json/statnive/v1/summary?from=${from}&to=${to}`, {
		headers,
	});
	if (res.status !== 200) return null;

	const data = JSON.parse(res.body);
	const totals = data.totals || {};
	return {
		hits: totals.views || 0,
		visitors: totals.visitors || 0,
		sessions: totals.sessions || 0,
	};
};

adapter.getByChannel = function (baseUrl, headers, from, to) {
	const res = http.get(
		`${baseUrl}/wp-json/statnive/v1/sources?from=${from}&to=${to}&limit=100`,
		{ headers }
	);
	if (res.status !== 200) return null;

	const sources = JSON.parse(res.body);
	return sources.map((s) => ({
		channel: s.channel || s.domain || 'Unknown',
		hits: s.views || 0,
		visitors: s.visitors || 0,
	}));
};

adapter.getByDevice = function (baseUrl, headers, from, to) {
	const res = http.get(
		`${baseUrl}/wp-json/statnive/v1/dimensions/devices?from=${from}&to=${to}`,
		{ headers }
	);
	if (res.status !== 200) return null;

	const devices = JSON.parse(res.body);
	return devices.map((d) => ({
		device_type: d.name || d.device_type || 'Unknown',
		hits: d.views || 0,
		visitors: d.visitors || 0,
	}));
};

export default adapter;
