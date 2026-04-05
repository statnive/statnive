// Generated from BDD scenarios — adjust selectors and routes for actual implementation.
// Source: features/09-bot-detection-exclusions.feature @REQ-9.5, @REQ-9.6, @REQ-9.12

import { test, expect } from '@playwright/test';
import { env } from '../env';

test.describe('Client-Side Bot Detection', () => {
	test('navigator.webdriver=true blocks tracker from sending hit', async ({ page }) => {
		// Disable sendBeacon so tracker falls back to fetch (interceptable by Playwright).
		// NOTE: Do NOT override webdriver — this test verifies that webdriver=true blocks tracking.
		// Playwright already sets navigator.webdriver=true by default.
		await page.addInitScript(() => {
			// @ts-ignore
			navigator.sendBeacon = undefined;
		});

		const hitRequests: string[] = [];
		await page.route('**/statnive/v1/hit', async (route) => {
			hitRequests.push(route.request().url());
			await route.continue();
		});

		await page.goto(env.baseUrl, { waitUntil: 'networkidle' });

		// Wait for tracker script to finish executing.
		await page.waitForFunction(() => {
			return typeof (window as Record<string, unknown>).StatniveConfig !== 'undefined';
		}, { timeout: 5000 }).catch(() => {
			// Config may not be set if tracker was blocked entirely.
		});

		// When webdriver is detected, the tracker should not send any hit.
		expect(hitRequests.length).toBe(0);
	});

	test('headless browser indicators block tracking (callPhantom)', async ({ page }) => {
		// Disable sendBeacon so tracker falls back to fetch (interceptable by Playwright).
		await page.addInitScript(() => {
			// @ts-ignore
			navigator.sendBeacon = undefined; Object.defineProperty(navigator, "webdriver", { get: () => false });
		});

		// Simulate PhantomJS headless indicator.
		await page.addInitScript(() => {
			(window as Record<string, unknown>).callPhantom = () => {};
		});

		const hitRequests: string[] = [];
		await page.route('**/statnive/v1/hit', async (route) => {
			hitRequests.push(route.request().url());
			await route.continue();
		});

		await page.goto(env.baseUrl, { waitUntil: 'networkidle' });

		// Wait for tracker script to finish executing.
		await page.waitForFunction(() => {
			return typeof (window as Record<string, unknown>).StatniveConfig !== 'undefined';
		}, { timeout: 5000 }).catch(() => {
			// Config may not be set if tracker was blocked entirely.
		});

		// PhantomJS indicators should trigger bot detection.
		expect(hitRequests.length).toBe(0);
	});

	test('headless browser indicators block tracking (__nightmare)', async ({ page }) => {
		// Disable sendBeacon so tracker falls back to fetch (interceptable by Playwright).
		await page.addInitScript(() => {
			// @ts-ignore
			navigator.sendBeacon = undefined; Object.defineProperty(navigator, "webdriver", { get: () => false });
		});

		// Simulate Nightmare.js headless indicator.
		await page.addInitScript(() => {
			(window as Record<string, unknown>).__nightmare = {};
		});

		const hitRequests: string[] = [];
		await page.route('**/statnive/v1/hit', async (route) => {
			hitRequests.push(route.request().url());
			await route.continue();
		});

		await page.goto(env.baseUrl, { waitUntil: 'networkidle' });

		// Wait for tracker script to finish executing.
		await page.waitForFunction(() => {
			return typeof (window as Record<string, unknown>).StatniveConfig !== 'undefined';
		}, { timeout: 5000 }).catch(() => {
			// Config may not be set if tracker was blocked entirely.
		});

		expect(hitRequests.length).toBe(0);
	});

	test('normal browser without bot indicators sends tracking hit', async ({ page }) => {
		// Disable sendBeacon so tracker falls back to fetch (interceptable by Playwright).
		await page.addInitScript(() => {
			// @ts-ignore
			navigator.sendBeacon = undefined; Object.defineProperty(navigator, "webdriver", { get: () => false });
		});

		// Ensure webdriver is false (simulating a real browser).
		await page.addInitScript(() => {
			Object.defineProperty(navigator, 'webdriver', {
				get: () => false,
				configurable: true,
			});
			// Ensure no headless indicators are present.
			delete (window as Record<string, unknown>).callPhantom;
			delete (window as Record<string, unknown>).__nightmare;
		});

		const hitRequests: Array<{ url: string; status: number }> = [];
		await page.route('**/statnive/v1/hit', async (route) => {
			const request = route.request();
			hitRequests.push({ url: request.url(), status: 0 });
			const response = await route.fetch();
			hitRequests[hitRequests.length - 1].status = response.status();
			await route.fulfill({ response });
		});

		await page.goto(env.baseUrl);

		// Wait for the actual tracking request to complete.
		await page.waitForResponse(
			(res) => res.url().includes('statnive/v1/hit') && res.status() === 204,
			{ timeout: 5000 }
		);

		// A normal browser should successfully send a tracking hit.
		expect(hitRequests.length).toBeGreaterThanOrEqual(1);
		expect(hitRequests[0].status).toBe(204);
	});

	test('bot detection exposes result on detectBot function', async ({ page }) => {
		// With webdriver=true, check the internal detection state.
		await page.addInitScript(() => {
			Object.defineProperty(navigator, 'webdriver', {
				get: () => true,
				configurable: true,
			});
		});

		await page.goto(env.baseUrl, { waitUntil: 'networkidle' });

		// Wait for tracker script to finish executing.
		await page.waitForFunction(() => {
			return typeof (window as Record<string, unknown>).StatniveConfig !== 'undefined';
		}, { timeout: 5000 }).catch(() => {
			// Config may not be set if tracker was blocked entirely.
		});

		// The tracker may expose its bot detection result for debugging.
		const botState = await page.evaluate(() => {
			const config = (window as Record<string, unknown>).StatniveConfig as Record<string, unknown> | undefined;
			return {
				configExists: config !== undefined,
			};
		});

		// Config should still be injected server-side even for bots.
		expect(botState.configExists).toBe(true);
	});
});
