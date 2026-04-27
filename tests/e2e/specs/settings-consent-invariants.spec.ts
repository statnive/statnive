/**
 * INV-1 / INV-2 — Privacy invariants.
 *
 * Both specs visit the frontend as an anonymous visitor (admin cookies
 * would redirect to wp-admin and the tracker never fires there).
 */

import { test, expect } from '../fixtures/auth';
import { setSettings, snapshotSettings, restoreSettings, truncateStatnive } from '../fixtures/settings';
import { newAnonContext, waitForViewCount } from '../fixtures/anon';
import { dbCount } from '../db-cli';
import { env } from '../env';

const ANALYTICS_TABLES = ['views', 'sessions', 'visitors', 'events', 'parameters'] as const;

test.describe('Privacy invariants', () => {
	// Loop of pageviews plus admin setup easily pushes past 30s.
	test.setTimeout(90_000);

	test.beforeEach(async ({ page }) => {
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

		const anon = await newAnonContext(browser, {
			extraHTTPHeaders: { DNT: '1', 'Sec-GPC': '1' },
		});

		// Three visits is sufficient to catch a consent-leak regression; the
		// Local site + DNT/GPC header path is several seconds per goto, so
		// more iterations just inflate the wall clock.
		for (let i = 0; i < 3; i++) {
			await anon.page.goto(`${env.baseUrl}/?i=${i}`, { waitUntil: 'domcontentloaded' });
		}
		await anon.page.waitForTimeout(500);

		for (const table of ANALYTICS_TABLES) {
			expect(dbCount(`statnive_${table}`), `table ${table}`).toBe(0);
		}

		await anon.close();
	});

	test('INV-2 cookieless, no blockers → ≥ 90% of pageviews land as views', async ({ browser, page }) => {
		await setSettings(page, {
			consent_mode: 'cookieless',
			respect_dnt: false,
			respect_gpc: false,
			excluded_ips: '',
		});

		const sent = 3;
		const anon = await newAnonContext(browser);

		for (let i = 0; i < sent; i++) {
			await anon.page.goto(`${env.baseUrl}/?i=${i}`, { waitUntil: 'domcontentloaded' });
		}
		// Single waitForViewCount at the end gives the trailing fetches up to
		// 8s to drain — faster than 3 × sequential polls and keeps us inside
		// the 90s describe cap.
		await waitForViewCount(sent);

		const stored = dbCount('statnive_views');
		const ratio = stored / sent;
		// Loss budget looser here than the checklist 0.05% bound — this spec
		// fires ~10 requests, not 1000+, so ratios are noisy. The authoritative
		// invariant test lives in a future perf-load spec.
		expect(ratio, `stored/sent = ${stored}/${sent}`).toBeGreaterThanOrEqual(0.9);
		await anon.close();
	});
});
