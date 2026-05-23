/**
 * Settings → Tracking master switch (`tracking_enabled`).
 *
 * Asserts the toggle actually stops view recording; existing specs only use
 * `tracking_enabled: true` as a precondition, so the off-state is otherwise
 * uncovered.
 */

import { test, expect } from '../fixtures/auth';
import {
	setSettings,
	snapshotSettings,
	restoreSettings,
	truncateStatnive,
} from '../fixtures/settings';
import { newAnonContext, waitForViewCount } from '../fixtures/anon';
import { dbCount } from '../db-cli';
import { env } from '../env';

test.describe('Settings → Tracking master switch', () => {
	test.beforeEach(async ({ page }) => {
		await snapshotSettings(page);
		await truncateStatnive(page);
	});

	test.afterEach(async ({ page }) => {
		await restoreSettings(page);
	});

	test('tracking_enabled=false → zero views even with permissive privacy settings', async ({
		page,
		browser,
	}) => {
		await setSettings(page, {
			tracking_enabled: false,
			consent_mode: 'cookieless',
			respect_dnt: false,
			respect_gpc: false,
			excluded_ips: '',
		});

		const anon = await newAnonContext(browser);
		await anon.page.goto(env.baseUrl);
		await anon.page.waitForTimeout(1000);

		expect(dbCount('statnive_views')).toBe(0);
		await anon.close();
	});

	test('tracking_enabled=true (control) records ≥1 view under same conditions', async ({
		page,
		browser,
	}) => {
		await setSettings(page, {
			tracking_enabled: true,
			consent_mode: 'cookieless',
			respect_dnt: false,
			respect_gpc: false,
			excluded_ips: '',
		});

		const anon = await newAnonContext(browser);
		await anon.page.goto(env.baseUrl);
		await waitForViewCount(1);

		expect(dbCount('statnive_views')).toBeGreaterThanOrEqual(1);
		await anon.close();
	});
});
