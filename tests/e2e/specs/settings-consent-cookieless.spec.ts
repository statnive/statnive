/**
 * CM-1 / CM-2 — Cookieless mode proves the UI copy
 * "No cookies, privacy-first. Designed to support GDPR/CCPA/APPI compliance."
 *
 * `page` from fixtures/auth carries admin cookies — only use it for REST
 * setup. `newAnonContext` is the real frontend-visitor path.
 */

import { test, expect } from '../fixtures/auth';
import { setSettings, snapshotSettings, restoreSettings, truncateStatnive } from '../fixtures/settings';
import { newAnonContext, waitForViewCount } from '../fixtures/anon';
import { dbCount } from '../db-cli';
import { env } from '../env';

test.describe('Settings → Privacy → Cookieless mode', () => {
	test.setTimeout(60_000);

	test.beforeEach(async ({ page }) => {
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

	test('CM-1 one pageview → views row written, Statnive writes no cookies / storage', async ({ browser }) => {
		const anon = await newAnonContext(browser);
		await anon.page.goto(env.baseUrl);
		await waitForViewCount(1);

		expect(dbCount('statnive_views')).toBeGreaterThanOrEqual(1);

		// Other plugins on this test site (Slimstat, Sourcebuster) set their
		// own cookies — the UI claim is that *Statnive itself* sets none, so
		// scope the assertion to Statnive-named keys.
		const cookies = await anon.context.cookies();
		expect(cookies.filter((c) => c.name.toLowerCase().includes('statnive'))).toHaveLength(0);

		const statniveKeys = await anon.page.evaluate(() => ({
			ls: Object.keys(localStorage).filter((k) => k.toLowerCase().includes('statnive')).length,
			ss: Object.keys(sessionStorage).filter((k) => k.toLowerCase().includes('statnive')).length,
		}));
		expect(statniveKeys).toEqual({ ls: 0, ss: 0 });

		await anon.close();
	});

	test('CM-2 repeat visits still write views, Statnive still sets no cookies', async ({ browser }) => {
		// Two distinct pageviews, then confirm the tracker fired twice and
		// that it still set no Statnive cookies. Simulating bfcache via
		// `page.goBack()` is flaky in Chromium headless — a second goto()
		// exercises the same "multiple pageviews" semantic without the race.
		const anon = await newAnonContext(browser);
		await anon.page.goto(env.baseUrl);
		await waitForViewCount(1);
		await anon.page.goto(`${env.baseUrl}/?second=${Date.now()}`);
		await waitForViewCount(2);

		expect(dbCount('statnive_views')).toBeGreaterThanOrEqual(2);

		const cookies = await anon.context.cookies();
		expect(cookies.filter((c) => c.name.toLowerCase().includes('statnive'))).toHaveLength(0);

		await anon.close();
	});
});
