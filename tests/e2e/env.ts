/**
 * E2E test environment configuration.
 *
 * Reads from environment variables with sensible defaults
 * for local WP Playground testing.
 */
export const env = {
	/** WordPress base URL. */
	baseUrl: process.env.WP_BASE_URL || 'http://localhost:8080',

	/** WordPress admin username. */
	adminUser: process.env.WP_ADMIN_USER || 'admin',

	/** WordPress admin password. */
	adminPassword: process.env.WP_ADMIN_PASSWORD || 'password',

	/** REST API base URL. */
	restUrl: process.env.WP_REST_URL || `${process.env.WP_BASE_URL || 'http://localhost:8080'}/wp-json`,

	/** Database connection (for DB-oracle assertions). */
	db: {
		host: process.env.DB_HOST || '127.0.0.1',
		port: parseInt(process.env.DB_PORT || '3306', 10),
		user: process.env.DB_USER || 'root',
		password: process.env.DB_PASSWORD || 'root',
		database: process.env.DB_NAME || 'wordpress',
	},

	/** WordPress table prefix. */
	tablePrefix: process.env.WP_TABLE_PREFIX || 'wp_',
};
