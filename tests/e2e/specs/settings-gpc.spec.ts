/**
 * GPC-1..5 — "Respect Global Privacy Control":
 *   "Skip visitors whose browser sends the GPC signal. Legally recognized
 *    in California and other regions."
 *
 * GPC is the primary signal — checked before DNT per PrivacyManager.
 */

import { test, expect } from '../fixtures/auth';
import {
	setSettings,
	snapshotSettings,
	restoreSettings,
	truncateStatnive,
	getDashboardNonce,
} from '../fixtures/settings';
import { newAnonContext, waitForViewCount } from '../fixtures/anon';
import { dbCount } from '../db-cli';
import { env } from '../env';

test.describe('Settings → Privacy → Respect GPC', () => {
	test.beforeEach(async ({ page }) => {
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
		const anon = await newAnonContext(browser, { extraHTTPHeaders: { 'Sec-GPC': '1' } });
		await anon.context.addInitScript(() => {
			Object.defineProperty(navigator, 'globalPrivacyControl', { get: () => true });
		});
		await anon.page.goto(env.baseUrl);
		await anon.page.waitForTimeout(1000);

		expect(dbCount('statnive_views')).toBe(0);
		await anon.close();
	});

	test('GPC-2 respect_gpc=true × no GPC → one view', async ({ browser }) => {
		const anon = await newAnonContext(browser);
		await anon.page.goto(env.baseUrl);
		await waitForViewCount(1);

		expect(dbCount('statnive_views')).toBeGreaterThanOrEqual(1);
		await anon.close();
	});

	test('GPC-3 respect_gpc=false × Sec-GPC=1 → one view (toggle disables gate)', async ({ page, browser }) => {
		await setSettings(page, { respect_gpc: false });

		const anon = await newAnonContext(browser, { extraHTTPHeaders: { 'Sec-GPC': '1' } });
		await anon.page.goto(env.baseUrl);
		await waitForViewCount(1);

		expect(dbCount('statnive_views')).toBeGreaterThanOrEqual(1);
		await anon.close();
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

		const anon = await newAnonContext(browser, {
			extraHTTPHeaders: { 'Sec-GPC': '1', DNT: '1' },
		});
		await anon.page.goto(env.baseUrl);
		await anon.page.waitForTimeout(1000);

		expect(dbCount('statnive_views')).toBe(0);
		await anon.close();
	});
});
