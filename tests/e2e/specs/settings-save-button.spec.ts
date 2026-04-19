/**
 * S-1..S-6 — Save-button contract replaces auto-save.
 */

import { test, expect } from '../fixtures/auth';
import { snapshotSettings, restoreSettings } from '../fixtures/settings';
import { env } from '../env';

test.describe('Settings → Save-button flow', () => {
	test.beforeEach(async ({ page }) => {
		await snapshotSettings(page);
	});

	test.afterEach(async ({ page }) => {
		await restoreSettings(page);
	});

	test('S-1 Save button is disabled on initial load (no edits)', async ({ page }) => {
		await page.goto(`${env.baseUrl}/wp-admin/admin.php?page=statnive#/settings`);
		await expect(page.getByTestId('settings-save')).toBeDisabled();
	});

	test('S-2 Toggling a checkbox enables Save', async ({ page }) => {
		await page.goto(`${env.baseUrl}/wp-admin/admin.php?page=statnive#/settings`);
		await page.getByTestId('dnt-respect-toggle').click();
		await expect(page.getByTestId('settings-save')).toBeEnabled();
	});

	test('S-3 Clicking Save shows the Saved ✓ flash', async ({ page }) => {
		await page.goto(`${env.baseUrl}/wp-admin/admin.php?page=statnive#/settings`);
		await page.getByTestId('dnt-respect-toggle').click();
		await page.getByTestId('settings-save').click();
		await expect(page.getByTestId('settings-saved-flash')).toBeVisible();
	});

	test('S-4 Navigating away without Save discards edits', async ({ page }) => {
		await page.goto(`${env.baseUrl}/wp-admin/admin.php?page=statnive#/settings`);
		const dnt = page.getByTestId('dnt-respect-toggle');
		const originallyChecked = await dnt.isChecked();
		await dnt.click();

		// Route to Overview (or anywhere away from settings) and back.
		await page.goto(`${env.baseUrl}/wp-admin/admin.php?page=statnive`);
		await page.goto(`${env.baseUrl}/wp-admin/admin.php?page=statnive#/settings`);

		await expect(dnt).toBeChecked({ checked: originallyChecked });
		await expect(page.getByTestId('settings-save')).toBeDisabled();
	});

	test('S-5 Full reload preserves a saved change', async ({ page }) => {
		await page.goto(`${env.baseUrl}/wp-admin/admin.php?page=statnive#/settings`);
		const retention = page.getByTestId('retention-select');
		await retention.selectOption('365');
		await page.getByTestId('settings-save').click();
		await expect(page.getByTestId('settings-saved-flash')).toBeVisible();

		await page.reload();
		await expect(retention).toHaveValue('365');
	});

	test('S-6 Save failure keeps local edits and surfaces an error', async ({ page }) => {
		await page.goto(`${env.baseUrl}/wp-admin/admin.php?page=statnive#/settings`);

		await page.route('**/statnive/v1/settings', (route) => {
			if (route.request().method() === 'PUT') {
				return route.fulfill({ status: 500, body: 'boom' });
			}
			return route.continue();
		});

		await page.getByTestId('gpc-respect-toggle').click();
		await page.getByTestId('settings-save').click();

		await expect(page.getByTestId('settings-error')).toBeVisible();
		// Local state is kept → Save is still enabled for retry.
		await expect(page.getByTestId('settings-save')).toBeEnabled();
	});
});
