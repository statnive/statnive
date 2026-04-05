/**
 * User profile loader using k6 SharedArray for memory efficiency.
 *
 * Loads visitor profiles from CSV once in init context, then shares
 * across all VUs without duplicating memory.
 *
 * Plugin-agnostic — profiles describe visitors, not analytics behavior.
 */

import { SharedArray } from 'k6/data';
import papaparse from 'https://jslib.k6.io/papaparse/5.1.1/index.js';

/**
 * Data directory path — k6's open() resolves relative to the entry script,
 * not the file containing the open() call. All entry scripts are in
 * tests/perf/, so data files are at ./data/ relative to them.
 */
const DATA_DIR = '../data';

/**
 * Parse CSV content into array of objects.
 * Handles quoted fields (UA strings contain commas).
 */
function parseCSV(content) {
	const parsed = papaparse.parse(content, { header: true, skipEmptyLines: true });
	return parsed.data.map((row) => ({
		id: row.id,
		device_type: row.device_type,
		user_agent: row.user_agent,
		viewport_w: parseInt(row.viewport_w, 10),
		viewport_h: parseInt(row.viewport_h, 10),
		timezone: row.timezone,
		locale: row.locale,
		accept_language: row.accept_language,
		is_logged_in: row.is_logged_in === '1',
		username: row.username || '',
		password: row.password || '',
		is_bot: row.is_bot === '1',
	}));
}

/** All visitor profiles (shared across VUs). */
const profiles = new SharedArray('visitor-profiles', function () {
	const content = open(`${DATA_DIR}/visitor-profiles.csv`);
	return parseCSV(content);
});

/** UTM campaign combinations. */
const utmCampaigns = new SharedArray('utm-campaigns', function () {
	const content = open(`${DATA_DIR}/utm-campaigns.csv`);
	return papaparse.parse(content, { header: true, skipEmptyLines: true }).data;
});

/** Referrer URLs with expected channel classification. */
const referrers = new SharedArray('referrers', function () {
	const content = open(`${DATA_DIR}/referrers.csv`);
	return papaparse.parse(content, { header: true, skipEmptyLines: true }).data;
});

/** WooCommerce products. */
const products = new SharedArray('products', function () {
	const content = open(`${DATA_DIR}/products.csv`);
	return papaparse.parse(content, { header: true, skipEmptyLines: true }).data.map((row) => ({
		...row,
		product_id: parseInt(row.product_id, 10),
		price: parseFloat(row.price),
	}));
});

/**
 * Get a deterministic profile for a VU (consistent across iterations).
 * @param {number} vuId - Virtual user ID (__VU).
 * @returns {object} Visitor profile.
 */
export function getProfile(vuId) {
	return profiles[vuId % profiles.length];
}

/**
 * Get a random profile.
 * @returns {object} Visitor profile.
 */
export function getRandomProfile() {
	return profiles[Math.floor(Math.random() * profiles.length)];
}

/**
 * Get profiles filtered by criteria.
 * NOTE: Do NOT call on SharedArray directly — returns a new regular array.
 * Use sparingly (init context preferred).
 * @param {object} filter - { device_type?, is_bot?, is_logged_in? }
 * @returns {object[]}
 */
export function getProfilesByType(filter) {
	const result = [];
	for (let i = 0; i < profiles.length; i++) {
		const p = profiles[i];
		if (filter.device_type && p.device_type !== filter.device_type) continue;
		if (filter.is_bot !== undefined && p.is_bot !== filter.is_bot) continue;
		if (filter.is_logged_in !== undefined && p.is_logged_in !== filter.is_logged_in) continue;
		result.push(p);
	}
	return result;
}

/**
 * Get a random UTM campaign (or null for no UTM).
 * @param {number} probability - Chance of returning UTM (0-1). Default 0.3.
 * @returns {object|null}
 */
export function getRandomUTM(probability = 0.3) {
	if (Math.random() > probability) return null;
	return utmCampaigns[Math.floor(Math.random() * utmCampaigns.length)];
}

/**
 * Get a random referrer with its expected channel.
 * @returns {{ url: string, expected_channel: string }}
 */
export function getRandomReferrer() {
	return referrers[Math.floor(Math.random() * referrers.length)];
}

/**
 * Get a random product.
 * @returns {object}
 */
export function getRandomProduct() {
	return products[Math.floor(Math.random() * products.length)];
}

/**
 * Get total count of loaded profiles.
 * @returns {number}
 */
export function getProfileCount() {
	return profiles.length;
}
