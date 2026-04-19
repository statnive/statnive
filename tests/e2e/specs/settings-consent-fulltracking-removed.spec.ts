/**
 * CM-6..CM-8 — "Full Tracking" is gone from UI, REST, and legacy option values.
 */

import { test, expect } from '../fixtures/auth';
import { snapshotSettings, restoreSettings, getDashboardNonce } from '../fixtures/settings';
import { wpOptionUpdate, wpCacheFlush } from '../db-cli';
import { env } from '../env';

test.describe('Full Tracking removal', () => {
	test.beforeEach(async ({ page }) => {
		await snapshotSettings(page);
	});

	test.afterEach(async ({ page }) => {
		await restoreSettings(page);
	});

	test('CM-6 Settings UI exposes exactly 2 consent modes, Full Tracking is not present', async ({ page }) => {
		await page.goto(`${env.baseUrl}/wp-admin/admin.php?page=statnive#/settings`);

		await expect(page.getByTestId('consent-mode-cookieless')).toBeVisible();
		await expect(page.getByTestId('consent-mode-disabled-until-consent')).toBeVisible();
		await expect(page.getByText('Full Tracking')).toHaveCount(0);
		await expect(page.locator('input[name="consent"]')).toHaveCount(2);
	});

	test('CM-7 REST PUT consent_mode="full" is silently coerced to cookieless', async ({ page }) => {
		await page.goto(`${env.baseUrl}/wp-admin/admin.php?page=statnive#/settings`);
		const put = await page.request.put(`${env.restUrl}/statnive/v1/settings`, {
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': await getDashboardNonce(page),
			},
			data: { consent_mode: 'full' },
		});

		expect(put.ok()).toBeTruthy();
		const body = (await put.json()) as { consent_mode: string };
		expect(body.consent_mode).toBe('cookieless');
	});

	test('CM-8 legacy wp_option value "full" is coerced on GET', async ({ page }) => {
		wpOptionUpdate('statnive_consent_mode', 'full');
		wpCacheFlush();

		await page.goto(`${env.baseUrl}/wp-admin/admin.php?page=statnive#/settings`);
		const get = await page.request.get(`${env.restUrl}/statnive/v1/settings`, {
			headers: { 'X-WP-Nonce': await getDashboardNonce(page) },
		});

		const body = (await get.json()) as { consent_mode: string };
		expect(body.consent_mode).toBe('cookieless');
	});
});
