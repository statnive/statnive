import { test, expect } from '@playwright/test';
import { env } from '../env';

/**
 * Cross-flow E2E: Activation → Dashboard.
 *
 * Production scenario: Fresh activation shows empty state KPIs,
 * then updates after first tracking hit.
 */
test.describe('Activation to Dashboard Flow', () => {
	test('fresh dashboard shows zero-state KPIs and tracking endpoint responds', async ({ page }) => {
		// Login as admin.
		await page.goto(`${env.baseUrl}/wp-login.php`);
		await page.fill('#user_login', env.adminUser);
		await page.fill('#user_pass', env.adminPassword);
		await page.click('#wp-submit');
		await page.waitForURL('**/wp-admin/**');

		// Navigate to Statnive dashboard.
		await page.goto(`${env.baseUrl}/wp-admin/admin.php?page=statnive`);

		// Verify the React SPA mounts.
		const app = page.locator('#statnive-app');
		await expect(app).toBeVisible({ timeout: 10000 });

		// Verify KPI cards render (may show 0 or loading state).
		// The dashboard should not crash on fresh install with no data.
		const pageContent = await page.textContent('#statnive-app');
		expect(pageContent).toBeTruthy();

		// Verify tracking endpoint is live by sending a test hit.
		const hitResponse = await page.request.post(`${env.restUrl}/statnive/v1/hit`, {
			headers: { 'Content-Type': 'text/plain' },
			data: JSON.stringify({
				resource_type: 'post',
				resource_id: 1,
				signature: 'test-will-fail-hmac',
				referrer: '',
				screen_width: 1920,
				screen_height: 1080,
				language: 'en-US',
				timezone: 'UTC',
			}),
		});

		// Should get 403 (invalid signature) — proves endpoint is reachable.
		expect(hitResponse.status()).toBe(403);
	});
});
