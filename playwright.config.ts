import { defineConfig, devices } from '@playwright/test';

/**
 * Playwright configuration for Statnive E2E tests.
 *
 * Uses WP Playground or local WordPress instance for testing.
 * DB-oracle assertions query analytics tables directly.
 */
export default defineConfig({
	testDir: './tests/e2e/specs',
	fullyParallel: false, // DB operations must be sequential.
	forbidOnly: !!process.env.CI,
	retries: process.env.CI ? 2 : 0,
	workers: 1,
	reporter: process.env.CI ? 'github' : 'html',
	timeout: 30_000,

	use: {
		baseURL: process.env.WP_BASE_URL || 'http://localhost:8080',
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
		video: 'off',
	},

	projects: [
		{
			name: 'chromium',
			use: { ...devices['Desktop Chrome'] },
		},
	],
});
