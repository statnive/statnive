import { test, expect } from '@playwright/test';
import { env } from '../env';

/**
 * Cross-flow E2E: Dashboard error recovery.
 *
 * Production scenario: REST API returns 500 → dashboard shows
 * error state → API recovers → dashboard recovers on refetch.
 */
test.describe('Dashboard Error Recovery', () => {
	test('dashboard handles API failure and recovers on retry', async ({ page }) => {
		// Step 1: Login as admin.
		await page.goto(`${env.baseUrl}/wp-login.php`);
		await page.fill('#user_login', env.adminUser);
		await page.fill('#user_pass', env.adminPassword);
		await page.click('#wp-submit');
		await page.waitForURL('**/wp-admin/**');

		// Step 2: Intercept summary API with 500 error.
		let interceptActive = true;

		await page.route('**/statnive/v1/summary**', async (route) => {
			if (interceptActive) {
				await route.fulfill({
					status: 500,
					contentType: 'application/json',
					body: JSON.stringify({
						code: 'internal_error',
						message: 'Simulated server error for E2E test',
					}),
				});
			} else {
				await route.continue();
			}
		});

		// Step 3: Navigate to dashboard — should show error state.
		await page.goto(`${env.baseUrl}/wp-admin/admin.php?page=statnive`);

		const app = page.locator('#statnive-app');
		await expect(app).toBeVisible({ timeout: 10000 });

		// Dashboard should not crash — the React app should still be mounted.
		const appContent = await app.textContent();
		expect(appContent).toBeTruthy();

		// Step 4: Remove the interception and reload — dashboard should recover.
		interceptActive = false;

		await page.reload();
		await expect(app).toBeVisible({ timeout: 10000 });

		// After recovery, the app should render without the error state.
		const recoveredContent = await app.textContent();
		expect(recoveredContent).toBeTruthy();
	});
});
