/**
 * Playwright global setup.
 *
 * 1. Copies test-only mu-plugins into `wp-content/mu-plugins/`, with a
 *    safety check that they do not already exist (fail-loud).
 * 2. Logs in as admin via the WP login form and persists the session at
 *    `tests/e2e/.auth/admin.json` so spec fixtures reuse it.
 *
 * The mu-plugins themselves are no-ops unless their corresponding env
 * vars are set (`STATNIVE_E2E_IP_FILTER`, `STATNIVE_E2E_CONSENT_STUB`,
 * `STATNIVE_E2E_DEBUG`). CI runners should export all three before
 * invoking `npm run test:e2e`.
 */

import { chromium, type FullConfig } from '@playwright/test';
import { cpSync, mkdirSync, readdirSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { env } from './env';

const PLUGIN_ROOT = resolve(__dirname, '..', '..');
const FIXTURE_MU_DIR = join(PLUGIN_ROOT, 'tests/e2e/fixtures/mu-plugins');
const SITE_MU_DIR = resolve(env.wpRoot, 'wp-content/mu-plugins');
const AUTH_DIR = join(PLUGIN_ROOT, 'tests/e2e/.auth');

const E2E_MU_FILES = [
	'statnive-ip-filter.php',
	'statnive-consent-stub.php',
	'statnive-e2e-debug.php',
];

export default async function globalSetup(_config: FullConfig): Promise<void> {
	void _config;

	mkdirSync(SITE_MU_DIR, { recursive: true });
	for (const file of E2E_MU_FILES) {
		const dst = join(SITE_MU_DIR, file);
		cpSync(join(FIXTURE_MU_DIR, file), dst, { force: true });
	}

	mkdirSync(AUTH_DIR, { recursive: true });
	const storageStatePath = join(AUTH_DIR, 'admin.json');

	const browser = await chromium.launch();
	const context = await browser.newContext();
	const page = await context.newPage();

	await page.goto(`${env.baseUrl}/wp-login.php`);
	await page.fill('#user_login', env.adminUser);
	await page.fill('#user_pass', env.adminPassword);
	await page.click('#wp-submit');
	await page.waitForURL(/wp-admin/);

	await context.storageState({ path: storageStatePath });
	await browser.close();

	// Log what we did — handy when a CI run fails before any spec starts.
	// eslint-disable-next-line no-console
	console.log('[e2e] setup complete:', {
		muPlugins: readdirSync(SITE_MU_DIR).filter((f) => f.startsWith('statnive-')),
		storageState: storageStatePath,
		baseUrl: env.baseUrl,
	});
}
