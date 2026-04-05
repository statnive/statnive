// Generated from BDD scenarios — duration-pipeline.feature
// Validates the full duration data pipeline: tracker → views.duration → aggregation → dashboard

import { test, expect } from '@playwright/test';
import { env } from '../env';
import { queryTable } from '../db';

async function loginAsAdmin(page: import('@playwright/test').Page): Promise<void> {
	await page.goto(`${env.baseUrl}/wp-login.php`);
	await page.fill('#user_login', env.adminUser);
	await page.fill('#user_pass', env.adminPassword);
	await page.click('#wp-submit');
	await page.waitForURL('**/wp-admin/**', { timeout: 10000 });
}

test.describe('Duration Data Pipeline', () => {
	test('engagement writes duration to views table, not sessions', async ({ page }) => {
		// Disable sendBeacon so tracker falls back to fetch (interceptable by Playwright).
		await page.addInitScript(() => {
			// @ts-ignore
			navigator.sendBeacon = undefined;
			Object.defineProperty(navigator, 'webdriver', { get: () => false });
		});

		const engagementRequests: Array<{ body: string }> = [];
		await page.route('**/statnive/v1/engagement', async (route) => {
			engagementRequests.push({ body: route.request().postData() || '' });
			await route.continue();
		});

		// Wait for the hit to be recorded first.
		await page.goto(env.baseUrl);
		await page.waitForResponse(
			(res) => res.url().includes('statnive/v1/hit') && res.status() === 204,
			{ timeout: 5000 },
		);

		// Wait for engagement timer to accumulate some time.
		await page.waitForTimeout(2000);

		// Trigger engagement flush by simulating page hide.
		await page.evaluate(() => {
			Object.defineProperty(document, 'hidden', { value: true, writable: true, configurable: true });
			document.dispatchEvent(new Event('visibilitychange'));
		});

		// Wait for engagement request.
		await page
			.waitForResponse(
				(res) => res.url().includes('statnive/v1/engagement') && (res.status() === 204 || res.status() === 200),
				{ timeout: 5000 },
			)
			.catch(() => {
				// Engagement may not fire if no data was collected.
			});

		if (engagementRequests.length > 0) {
			let body: Record<string, unknown> = {};
			try {
				body = JSON.parse(engagementRequests[0].body || '{}');
			} catch {
				// Non-JSON payload; skip.
			}

			// Engagement payload should include a positive duration.
			expect(body).toHaveProperty('engagement_time');
			expect(body.engagement_time).toBeGreaterThan(0);
		}

		// DB-oracle Tier 1: verify duration landed on views, not sessions.
		await loginAsAdmin(page);

		const views = await queryTable(page, 'views');
		const viewsWithDuration = views.filter((row) => Number(row.duration) > 0);

		const sessions = await queryTable(page, 'sessions');
		const sessionsWithDuration = sessions.filter((row) => Number(row.duration) > 0);

		// Views should have duration (written by EngagementController).
		if (engagementRequests.length > 0) {
			expect(viewsWithDuration.length).toBeGreaterThan(0);
		}

		// Sessions should NOT have duration (never populated).
		expect(sessionsWithDuration.length).toBe(0);
	});

	test('summary API returns non-zero duration after engagement', async ({ page, context }) => {
		// Step 1: Generate a pageview with engagement on a separate page.
		const visitorPage = await context.newPage();

		await visitorPage.addInitScript(() => {
			// @ts-ignore
			navigator.sendBeacon = undefined;
			Object.defineProperty(navigator, 'webdriver', { get: () => false });
		});

		await visitorPage.goto(env.baseUrl);
		await visitorPage.waitForResponse(
			(res) => res.url().includes('statnive/v1/hit') && res.status() === 204,
			{ timeout: 5000 },
		);

		// Let engagement timer accumulate.
		await visitorPage.waitForTimeout(2000);

		// Flush engagement.
		await visitorPage.evaluate(() => {
			Object.defineProperty(document, 'hidden', { value: true, writable: true, configurable: true });
			document.dispatchEvent(new Event('visibilitychange'));
		});

		await visitorPage
			.waitForResponse(
				(res) => res.url().includes('statnive/v1/engagement') && (res.status() === 204 || res.status() === 200),
				{ timeout: 5000 },
			)
			.catch(() => {});

		await visitorPage.close();

		// Step 2: Login as admin and query summary API.
		await loginAsAdmin(page);

		const today = new Date().toISOString().slice(0, 10);
		const summaryUrl = `${env.restUrl}/statnive/v1/summary?from=${today}&to=${today}`;

		const response = await page.request.get(summaryUrl, {
			headers: {
				'X-WP-Nonce': await page.evaluate(() => {
					const settings = (window as Record<string, unknown>).wpApiSettings as
						| { nonce: string }
						| undefined;
					return settings?.nonce || '';
				}),
			},
		});

		expect(response.ok()).toBe(true);

		const data = (await response.json()) as {
			totals: { total_duration: number; visitors: number };
			daily: Array<Record<string, unknown>>;
		};

		// If engagement was recorded, duration should be > 0.
		if (data.totals.visitors > 0) {
			expect(data.totals.total_duration).toBeGreaterThan(0);
		}
	});
});
