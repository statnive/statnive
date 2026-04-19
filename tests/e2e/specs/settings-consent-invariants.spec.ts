/**
 * INV-1 / INV-2 — Privacy invariants.
 *
 * Aggregates all "blocking" copy (Disabled Until Consent + DNT + GPC) into
 * one zero-leak proof, then separately proves Cookieless isn't silently
 * dropping hits (loss bound).
 *
 * Keep these small and fast — they're the deterministic sanity check that
 * earns the UI copy's right to exist.
 */

import { test, expect } from '../fixtures/auth';
import { disableBeacon } from '../fixtures/privacy';
import { setSettings, snapshotSettings, restoreSettings, truncateStatnive } from '../fixtures/settings';
import { dbCount } from '../db-cli';
import { env } from '../env';

const ANALYTICS_TABLES = ['views', 'sessions', 'visitors', 'events', 'parameters'] as const;

test.describe('Privacy invariants', () => {
	test.beforeEach(async ({ page, context }) => {
		await disableBeacon(context);
		await snapshotSettings(page);
		await truncateStatnive(page);
	});

	test.afterEach(async ({ page }) => {
		await restoreSettings(page);
	});

	test('INV-1 all-blocking config → zero rows in every analytics table', async ({ browser, page }) => {
		await setSettings(page, {
			consent_mode: 'disabled-until-consent',
			respect_dnt: true,
			respect_gpc: true,
		});

		const context = await browser.newContext({
			extraHTTPHeaders: { DNT: '1', 'Sec-GPC': '1' },
		});
		await disableBeacon(context);
		const p = await context.newPage();

		for (let i = 0; i < 20; i++) {
			await p.goto(`${env.baseUrl}/?i=${i}`);
			await p.waitForTimeout(50);
		}

		for (const table of ANALYTICS_TABLES) {
			expect(dbCount(`statnive_${table}`), `table ${table}`).toBe(0);
		}

		await context.close();
	});

	test('INV-2 cookieless, no blockers → ≥ 99.5% of pageviews land as views', async ({ browser, page }) => {
		await setSettings(page, {
			consent_mode: 'cookieless',
			respect_dnt: false,
			respect_gpc: false,
			excluded_ips: '',
		});

		const sent = 40;
		const context = await browser.newContext();
		await disableBeacon(context);
		const p = await context.newPage();

		for (let i = 0; i < sent; i++) {
			await p.goto(`${env.baseUrl}/?i=${i}`);
			await p.waitForResponse(
				(r) => r.url().includes('/statnive/v1/hit') && r.status() === 204,
				{ timeout: 5000 }
			);
		}

		const stored = dbCount('statnive_views');
		const ratio = stored / sent;
		expect(ratio, `stored/sent = ${stored}/${sent}`).toBeGreaterThanOrEqual(0.995);
		await context.close();
	});
});
