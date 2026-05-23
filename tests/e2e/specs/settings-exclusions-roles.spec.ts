/**
 * Settings → Exclusions → Roles (`excluded_roles`).
 *
 * Integration coverage exists in tests/integration/Service/ExclusionMatcherTest.php
 * but never exercises the full REST hit pipeline. These tests run that pipeline
 * with real logged-in users.
 */

import { test, expect } from '../fixtures/auth';
import {
	setSettings,
	snapshotSettings,
	restoreSettings,
	truncateStatnive,
} from '../fixtures/settings';
import { newAnonContext, waitForViewCount } from '../fixtures/anon';
import { loginAsRole } from '../fixtures/role-login';
import { dbCount } from '../db-cli';
import { env } from '../env';

test.describe('Settings → Exclusions → Roles', () => {
	test.beforeEach(async ({ page }) => {
		await snapshotSettings(page);
		await truncateStatnive(page);
		await setSettings(page, {
			tracking_enabled: true,
			consent_mode: 'cookieless',
			respect_dnt: false,
			respect_gpc: false,
			excluded_ips: '',
			excluded_roles: ['subscriber'],
		});
	});

	test.afterEach(async ({ page }) => {
		await restoreSettings(page);
	});

	test('excluded role (subscriber) → zero views', async ({ page, browser }) => {
		const session = await loginAsRole(browser, page, 'subscriber');
		await session.page.goto(env.baseUrl);
		await session.page.waitForTimeout(1000);

		expect(dbCount('statnive_views')).toBe(0);
		await session.close();
	});

	test('non-excluded role (editor) → ≥1 view (control)', async ({ page, browser }) => {
		const session = await loginAsRole(browser, page, 'editor');
		await session.page.goto(env.baseUrl);
		await waitForViewCount(1);

		expect(dbCount('statnive_views')).toBeGreaterThanOrEqual(1);
		await session.close();
	});

	test('logged-out visitor → ≥1 view (role exclusion does not bleed to anon)', async ({
		browser,
	}) => {
		const anon = await newAnonContext(browser);
		await anon.page.goto(env.baseUrl);
		await waitForViewCount(1);

		expect(dbCount('statnive_views')).toBeGreaterThanOrEqual(1);
		await anon.close();
	});
});
