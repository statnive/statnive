/**
 * Base adapter interface for analytics plugin comparison.
 *
 * Every plugin adapter must implement these methods.
 * The framework uses duck-typing — just export an object matching this shape.
 *
 * To add a new plugin: create a new file in adapters/, export an object
 * with { name, isInstalled, getTotals, getByChannel, getByDevice }.
 */

/**
 * @typedef {object} PluginTotals
 * @property {number} hits      - Total page views / hits.
 * @property {number} visitors  - Unique visitors.
 * @property {number} sessions  - Total sessions (if available).
 */

/**
 * @typedef {object} ChannelBreakdown
 * @property {string} channel   - Channel name.
 * @property {number} hits      - Hits from this channel.
 * @property {number} visitors  - Unique visitors from this channel.
 */

/**
 * @typedef {object} DeviceBreakdown
 * @property {string} device_type - 'desktop', 'mobile', 'tablet'.
 * @property {number} hits
 * @property {number} visitors
 */

/**
 * Create a base adapter with default no-op implementations.
 * Override the methods you can support.
 *
 * @param {string} name - Plugin identifier (e.g., 'statnive', 'wp-statistics').
 * @returns {object} Base adapter.
 */
export function createAdapter(name) {
	return {
		name,

		/**
		 * Check if this plugin is installed and active on the target site.
		 * @param {string} baseUrl
		 * @param {object} headers - Auth headers.
		 * @returns {boolean}
		 */
		isInstalled(baseUrl, headers) {
			return false;
		},

		/**
		 * Get total hits/visitors/sessions for a date range.
		 * @param {string} baseUrl
		 * @param {object} headers
		 * @param {string} from - YYYY-MM-DD
		 * @param {string} to   - YYYY-MM-DD
		 * @returns {PluginTotals|null}
		 */
		getTotals(baseUrl, headers, from, to) {
			return null;
		},

		/**
		 * Get breakdown by traffic channel/source.
		 * @returns {ChannelBreakdown[]|null}
		 */
		getByChannel(baseUrl, headers, from, to) {
			return null;
		},

		/**
		 * Get breakdown by device type.
		 * @returns {DeviceBreakdown[]|null}
		 */
		getByDevice(baseUrl, headers, from, to) {
			return null;
		},
	};
}
