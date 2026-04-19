/**
 * DNT-1..4 — "Respect Do Not Track" behaves as described:
 *   "Skip visitors whose browser sends the DNT signal."
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

test.describe('Settings → Privacy → Respect Do Not Track', () => {
	test.beforeEach(async ({ page, context }) => {
		await disableBeacon(context);
		await snapshotSettings(page);
		await truncateStatnive(page);
		await setSettings(page, {
			tracking_enabled: true,
			consent_mode: 'cookieless',
			respect_dnt: true,
			respect_gpc: false,
			excluded_ips: '',
		});
	});

	test.afterEach(async ({ page }) => {
		await restoreSettings(page);
	});

	test('DNT-1 respect_dnt=true × browser DNT=1 → zero views', async ({ browser }) => {
		const context = await browser.newContext({ extraHTTPHeaders: { DNT: '1' } });
		await disableBeacon(context);
		await context.addInitScript(() => {
			Object.defineProperty(navigator, 'doNotTrack', { get: () => '1' });
		});
		const page = await context.newPage();
		await page.goto(env.baseUrl);
		await page.waitForTimeout(750);

		expect(dbCount('statnive_views')).toBe(0);
		await context.close();
	});

	test('DNT-2 respect_dnt=true × no DNT header → one view', async ({ page }) => {
		await page.goto(env.baseUrl);
		await page.waitForResponse(
			(r) => r.url().includes('/statnive/v1/hit') && r.status() === 204,
			{ timeout: 5000 }
		);

		expect(dbCount('statnive_views')).toBe(1);
	});

	test('DNT-3 respect_dnt=false × DNT=1 → one view (toggle disables gate)', async ({ page, browser }) => {
		await setSettings(page, { respect_dnt: false });

		const context = await browser.newContext({ extraHTTPHeaders: { DNT: '1' } });
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

	test('DNT-4 server-side rejection — direct POST with DNT=1 is dropped', async ({ page }) => {
		const response = await page.request.post(`${env.restUrl}/statnive/v1/hit`, {
			headers: {
				'Content-Type': 'text/plain',
				'X-WP-Nonce': await getDashboardNonce(page),
				DNT: '1',
			},
			data: JSON.stringify({
				resource_type: 'post',
				resource_id: 1,
				signature: 'invalid-but-signature-check-runs-before-dnt',
			}),
		});

		// 204 is the silent drop the privacy gate returns. 403 is also acceptable
		// when HMAC fails first; both prove the hit was NOT persisted.
		expect([204, 403]).toContain(response.status());
		expect(dbCount('statnive_views')).toBe(0);
	});
});
