/**
 * GPC-1..5 — "Respect Global Privacy Control" behaves as described:
 *   "Skip visitors whose browser sends the GPC signal. Legally recognized
 *    in California and other regions."
 *
 * GPC is the primary signal — checked before DNT per PrivacyManager.
 */

import { test, expect } from '../fixtures/auth';
import { disableBeacon } from '../fixtures/privacy';
import {
	setSettings,
	snapshotSettings,
	restoreSettings,
	truncateStatnive,
	getDashboardNonce,
} from '../fixtures/settings';
import { dbCount } from '../db-cli';
import { env } from '../env';

test.describe('Settings → Privacy → Respect GPC', () => {
	test.beforeEach(async ({ page, context }) => {
		await disableBeacon(context);
		await snapshotSettings(page);
		await truncateStatnive(page);
		await setSettings(page, {
			tracking_enabled: true,
			consent_mode: 'cookieless',
			respect_dnt: false,
			respect_gpc: true,
			excluded_ips: '',
		});
	});

	test.afterEach(async ({ page }) => {
		await restoreSettings(page);
	});

	test('GPC-1 respect_gpc=true × Sec-GPC=1 → zero views', async ({ browser }) => {
		const context = await browser.newContext({ extraHTTPHeaders: { 'Sec-GPC': '1' } });
		await disableBeacon(context);
		await context.addInitScript(() => {
			Object.defineProperty(navigator, 'globalPrivacyControl', { get: () => true });
		});
		const page = await context.newPage();
		await page.goto(env.baseUrl);
		await page.waitForTimeout(750);

		expect(dbCount('statnive_views')).toBe(0);
		await context.close();
	});

	test('GPC-2 respect_gpc=true × no GPC → one view', async ({ page }) => {
		await page.goto(env.baseUrl);
		await page.waitForResponse(
			(r) => r.url().includes('/statnive/v1/hit') && r.status() === 204,
			{ timeout: 5000 }
		);

		expect(dbCount('statnive_views')).toBe(1);
	});

	test('GPC-3 respect_gpc=false × Sec-GPC=1 → one view (toggle disables gate)', async ({ page, browser }) => {
		await setSettings(page, { respect_gpc: false });

		const context = await browser.newContext({ extraHTTPHeaders: { 'Sec-GPC': '1' } });
		await disableBeacon(context);
		const page2 = await context.newPage();
		await page2.goto(env.baseUrl);
		await page2.waitForResponse(
			(r) => r.url().includes('/statnive/v1/hit') && r.status() === 204,
			{ timeout: 5000 }
		);

		expect(dbCount('statnive_views')).toBe(1);
		await context.close();
	});

	test('GPC-4 server-side rejection — direct POST with Sec-GPC=1 is dropped', async ({ page }) => {
		const response = await page.request.post(`${env.restUrl}/statnive/v1/hit`, {
			headers: {
				'Content-Type': 'text/plain',
				'X-WP-Nonce': await getDashboardNonce(page),
				'Sec-GPC': '1',
			},
			data: JSON.stringify({
				resource_type: 'post',
				resource_id: 1,
				signature: 'anything',
			}),
		});

		expect([204, 403]).toContain(response.status());
		expect(dbCount('statnive_views')).toBe(0);
	});

	test('GPC-5 GPC takes precedence over DNT settings', async ({ page, browser }) => {
		await setSettings(page, { respect_dnt: false, respect_gpc: true });

		const context = await browser.newContext({
			extraHTTPHeaders: { 'Sec-GPC': '1', DNT: '1' },
		});
		await disableBeacon(context);
		const p = await context.newPage();
		await p.goto(env.baseUrl);
		await p.waitForTimeout(750);

		// GPC wins even when DNT-respect is off — per PrivacyManager priority.
		expect(dbCount('statnive_views')).toBe(0);
		await context.close();
	});
});
