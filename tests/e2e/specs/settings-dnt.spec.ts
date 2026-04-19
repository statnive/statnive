/**
 * DNT-1..4 — "Respect Do Not Track":
 *   "Skip visitors whose browser sends the DNT signal."
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

test.describe('Settings → Privacy → Respect Do Not Track', () => {
	test.beforeEach(async ({ page }) => {
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
		const anon = await newAnonContext(browser, { extraHTTPHeaders: { DNT: '1' } });
		await anon.context.addInitScript(() => {
			Object.defineProperty(navigator, 'doNotTrack', { get: () => '1' });
		});
		await anon.page.goto(env.baseUrl);
		await anon.page.waitForTimeout(1000);

		expect(dbCount('statnive_views')).toBe(0);
		await anon.close();
	});

	test('DNT-2 respect_dnt=true × no DNT header → one view', async ({ browser }) => {
		const anon = await newAnonContext(browser);
		await anon.page.goto(env.baseUrl);
		await waitForViewCount(1);

		expect(dbCount('statnive_views')).toBeGreaterThanOrEqual(1);
		await anon.close();
	});

	test('DNT-3 respect_dnt=false × DNT=1 → one view (toggle disables gate)', async ({ page, browser }) => {
		await setSettings(page, { respect_dnt: false });

		const anon = await newAnonContext(browser, { extraHTTPHeaders: { DNT: '1' } });
		await anon.page.goto(env.baseUrl);
		await waitForViewCount(1);

		expect(dbCount('statnive_views')).toBeGreaterThanOrEqual(1);
		await anon.close();
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

		expect([204, 403]).toContain(response.status());
		expect(dbCount('statnive_views')).toBe(0);
	});
});
