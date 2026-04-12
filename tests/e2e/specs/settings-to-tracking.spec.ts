import { test, expect } from '@playwright/test';
import { env } from '../env';

/**
 * Cross-flow E2E: Settings → Tracking behavior.
 *
 * Production scenario: Changing consent mode in settings
 * affects tracker behavior on the frontend.
 */
test.describe('Settings to Tracking Flow', () => {
	test('disabled-until-consent mode prevents tracking without consent signal', async ({
		page,
		request,
	}) => {
		// Step 1: Login as admin and get a nonce.
		await page.goto(`${env.baseUrl}/wp-login.php`);
		await page.fill('#user_login', env.adminUser);
		await page.fill('#user_pass', env.adminPassword);
		await page.click('#wp-submit');
		await page.waitForURL('**/wp-admin/**');

		// Get the REST nonce from the admin page.
		const nonce = await page.evaluate(() => {
			const settings = (window as Record<string, unknown>).wpApiSettings as
				| { nonce: string }
				| undefined;
			return settings?.nonce || '';
		});

		// Step 2: Set consent mode to disabled-until-consent via REST API.
		const settingsResponse = await page.request.post(
			`${env.restUrl}/statnive/v1/settings`,
			{
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': nonce,
				},
				data: JSON.stringify({ consent_mode: 'disabled-until-consent' }),
			}
		);

		// Settings endpoint may return 200 or 204.
		expect([200, 204]).toContain(settingsResponse.status());

		// Step 3: Visit the frontend and verify no hit is sent.
		const hitRequests: string[] = [];

		await page.route('**/statnive/v1/hit', async (route) => {
			hitRequests.push(route.request().url());
			await route.continue();
		});

		// Use a new context to avoid admin cookies affecting tracking behavior.
		const frontendContext = await page.context().browser()!.newContext();
		const frontendPage = await frontendContext.newPage();

		const frontendHits: string[] = [];
		frontendPage.on('request', (req) => {
			if (req.url().includes('statnive/v1/hit')) {
				frontendHits.push(req.url());
			}
		});

		await frontendPage.goto(env.baseUrl, { waitUntil: 'networkidle' });

		// In disabled-until-consent mode, no hit should fire.
		expect(frontendHits.length).toBe(0);

		await frontendContext.close();

		// Step 4: Restore default consent mode (cookieless).
		await page.request.post(`${env.restUrl}/statnive/v1/settings`, {
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': nonce,
			},
			data: JSON.stringify({ consent_mode: 'cookieless' }),
		});
	});
});
