/**
 * IP-1..3 — "Your IP right now" hint + "Add to exclusions" button.
 *
 * Requires STATNIVE_E2E_IP_FILTER=1. The mu-plugin reads the
 * X-Test-Client-IP header and returns that IP from IpExtractor::extract(),
 * which ReactHandler then localizes as window.StatniveDashboard.currentIp.
 */

import { test, expect } from '../fixtures/auth';
import { snapshotSettings, restoreSettings } from '../fixtures/settings';
import { withClientIp } from '../fixtures/ip-spoof';
import { env } from '../env';

const SPOOF_IP = '198.51.100.77';

test.describe('Settings → Exclusions → Current IP hint', () => {
	test.beforeEach(async ({ page }) => {
		await snapshotSettings(page);
	});

	test.afterEach(async ({ page }) => {
		await restoreSettings(page);
	});

	test('IP-1 Settings page shows the admin\'s current IP', async ({ browser }) => {
		const context = await browser.newContext();
		await withClientIp(context, SPOOF_IP);
		const page = await context.newPage();

		await page.goto(`${env.baseUrl}/wp-admin/admin.php?page=statnive#/settings`);
		await expect(page.getByTestId('current-ip-value')).toHaveText(SPOOF_IP);

		await context.close();
	});

	test('IP-2 "Add to exclusions" appends the IP and marks form dirty', async ({ browser }) => {
		const context = await browser.newContext();
		await withClientIp(context, SPOOF_IP);
		const page = await context.newPage();

		await page.goto(`${env.baseUrl}/wp-admin/admin.php?page=statnive#/settings`);
		await page.getByTestId('add-ip-button').click();

		const textarea = page.getByTestId('excluded-ips-textarea');
		await expect(textarea).toContainText(SPOOF_IP);
		await expect(page.getByTestId('settings-save')).toBeEnabled();

		await context.close();
	});

	test('IP-3 Save persists the added IP, survives a reload', async ({ browser }) => {
		const context = await browser.newContext();
		await withClientIp(context, SPOOF_IP);
		const page = await context.newPage();

		await page.goto(`${env.baseUrl}/wp-admin/admin.php?page=statnive#/settings`);
		await page.getByTestId('add-ip-button').click();
		await page.getByTestId('settings-save').click();
		await expect(page.getByTestId('settings-saved-flash')).toBeVisible();

		await page.reload();
		await expect(page.getByTestId('excluded-ips-textarea')).toContainText(SPOOF_IP);

		await context.close();
	});
});
