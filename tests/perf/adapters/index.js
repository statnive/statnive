/**
 * Plugin adapter auto-discovery.
 *
 * Imports all available adapters and checks which plugins are installed
 * on the target WordPress site.
 *
 * To add a new plugin: create a new adapter file and import it here.
 */

import statnive from './statnive.js';
import wpStatistics from './wp-statistics.js';
import independentAnalytics from './independent-analytics.js';
import burstStatistics from './burst-statistics.js';
import kokoAnalytics from './koko-analytics.js';
import wpSlimstat from './wp-slimstat.js';
import jetpackStats from './jetpack-stats.js';

/** All registered adapters. */
const ALL_ADAPTERS = [
	statnive,
	wpStatistics,
	independentAnalytics,
	burstStatistics,
	kokoAnalytics,
	wpSlimstat,
	jetpackStats,
];

/**
 * Discover which analytics plugins are installed on the target site.
 *
 * @param {string} baseUrl - WordPress site URL.
 * @param {object} headers - Auth headers.
 * @returns {object[]} Array of active adapter objects.
 */
export function getInstalledAdapters(baseUrl, headers) {
	const installed = [];
	for (const adapter of ALL_ADAPTERS) {
		try {
			if (adapter.isInstalled(baseUrl, headers)) {
				installed.push(adapter);
			}
		} catch {
			// Adapter check failed — skip silently.
		}
	}
	return installed;
}

/**
 * Get a specific adapter by name.
 *
 * @param {string} name - Plugin name (e.g., 'statnive').
 * @returns {object|null}
 */
export function getAdapter(name) {
	return ALL_ADAPTERS.find((a) => a.name === name) || null;
}

/**
 * Get all registered adapter names.
 * @returns {string[]}
 */
export function getAllAdapterNames() {
	return ALL_ADAPTERS.map((a) => a.name);
}
