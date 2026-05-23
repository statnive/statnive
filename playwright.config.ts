import { defineConfig, devices } from '@playwright/test';

/**
 * Playwright configuration for Statnive E2E tests.
 *
 * Targets a live WordPress install (Local WP by default). `globalSetup`
 * copies test-only mu-plugins and captures an admin session so specs
 * can exercise admin-scoped routes without re-logging in every test.
 */
export default defineConfig({
	testDir: './tests/e2e/specs',
	fullyParallel: false, // DB operations must be sequential.
	forbidOnly: !!process.env.CI,
	retries: process.env.CI ? 2 : 0,
	workers: 1,
	reporter: process.env.CI ? 'github' : 'html',
	timeout: 30_000,

	globalSetup: require.resolve('./tests/e2e/global-setup.ts'),
	globalTeardown: require.resolve('./tests/e2e/global-teardown.ts'),

	use: {
		baseURL: process.env.WP_BASE_URL || 'http://statnive-test.local',
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
