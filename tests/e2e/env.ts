/**
 * E2E test environment configuration.
 *
 * Targets the running Local WP site that hosts this plugin checkout —
 * same site the Playwright MCP attaches to. Override any field via env
 * for CI (wp-env, Docker, etc.).
 */
const defaultBaseUrl = process.env.WP_BASE_URL || 'http://statnive-test.local';

export const env = {
	/** WordPress base URL. */
	baseUrl: defaultBaseUrl,

	/** WordPress admin username. */
	adminUser: process.env.WP_ADMIN_USER || 'admin',

	/** WordPress admin password. */
	adminPassword: process.env.WP_ADMIN_PASSWORD || 'password',

	/** REST API base URL. */
	restUrl: process.env.WP_REST_URL || `${defaultBaseUrl}/wp-json`,

	/** WordPress table prefix. */
	tablePrefix: process.env.WP_TABLE_PREFIX || 'wp_',

	/**
	 * Absolute path to the WordPress install root (directory that contains
	 * `wp-config.php`). `wp-cli` is invoked with this as its CWD so that
	 * DB credentials are picked up from `wp-config.php` without the harness
	 * needing to know Local's per-site MySQL socket.
	 */
	wpRoot: process.env.WP_ROOT || '/Users/parhumm/Local Sites/statnive-test/app/public',
};
