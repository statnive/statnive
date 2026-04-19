/**
 * CM-1 / CM-2 — Cookieless mode proves the UI copy "No cookies, privacy-first".
 *
 * Requires the mu-plugins copied by global-setup (STATNIVE_E2E_DEBUG=1).
 */

import { test, expect } from '../fixtures/auth';
import { disableBeacon } from '../fixtures/privacy';
import { setSettings, snapshotSettings, restoreSettings, truncateStatnive } from '../fixtures/settings';
import { dbCount } from '../db-cli';
import { env } from '../env';

test.describe('Settings → Privacy → Cookieless mode', () => {
	test.beforeEach(async ({ page, context }) => {
		await disableBeacon(context);
		await snapshotSettings(page);
		await truncateStatnive(page);
		await setSettings(page, {
			tracking_enabled: true,
			consent_mode: 'cookieless',
			respect_dnt: false,
			respect_gpc: false,
			excluded_ips: '',
		});
	});

	test.afterEach(async ({ page }) => {
		await restoreSettings(page);
	});

	test('CM-1 one pageview → views row written, zero cookies/localStorage/sessionStorage', async ({ page }) => {
		await page.goto(env.baseUrl);
		await page.waitForResponse(
			(res) => res.url().includes('/statnive/v1/hit') && res.status() === 204,
			{ timeout: 5000 }
		);

		expect(dbCount('statnive_views')).toBe(1);

		const cookies = await page.context().cookies();
		expect(cookies).toHaveLength(0);

		const storage = await page.evaluate(() => [localStorage.length, sessionStorage.length]);
		expect(storage).toEqual([0, 0]);
	});

	test('CM-2 bfcache restore still writes views, still zero storage', async ({ page }) => {
		await page.goto(env.baseUrl);
		await page.waitForResponse(
			(res) => res.url().includes('/statnive/v1/hit') && res.status() === 204,
			{ timeout: 5000 }
		);
		await page.goto(`${env.baseUrl}/?cachebuster=${Date.now()}`);
		await page.goBack();
		await page.waitForTimeout(500);

		expect(dbCount('statnive_views')).toBeGreaterThanOrEqual(2);

		const cookies = await page.context().cookies();
		expect(cookies).toHaveLength(0);
	});
});
