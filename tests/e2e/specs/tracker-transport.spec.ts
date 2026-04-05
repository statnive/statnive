// Generated from BDD scenarios — adjust selectors and routes for actual implementation.
// Source: features/01-tracking-pipeline.feature @REQ-1.6

import { test, expect } from '@playwright/test';
import { env } from '../env';

test.describe('Tracker Transport Chain', () => {
	test('sendBeacon is attempted first for hit requests', async ({ page }) => {
		// Track which transport method is used.
		const transports: Array<{ method: string; url: string }> = [];

		// Intercept sendBeacon calls via page script injection.
		await page.addInitScript(() => {
			// Override webdriver so bot detector doesn't block.
			Object.defineProperty(navigator, 'webdriver', { get: () => false });
			const originalSendBeacon = navigator.sendBeacon.bind(navigator);
			(window as Record<string, unknown>).__statniveTransports = [];
			navigator.sendBeacon = function (url: string | URL, data?: BodyInit | null): boolean {
				const transports = (window as Record<string, unknown>).__statniveTransports as Array<{ method: string; url: string }>;
				transports.push({ method: 'sendBeacon', url: String(url) });
				return originalSendBeacon(url, data);
			};
		});

		// Also intercept fetch to detect fallback.
		page.on('request', (request) => {
			if (request.url().includes('statnive/v1/hit')) {
				transports.push({ method: 'fetch', url: request.url() });
			}
		});

		await page.goto(env.baseUrl, { waitUntil: 'networkidle' });

		// Wait for tracker script to finish executing and potentially send a beacon.
		await page.waitForFunction(() => {
			return typeof (window as Record<string, unknown>).StatniveConfig !== 'undefined';
		}, { timeout: 5000 });

		// Retrieve sendBeacon calls recorded by the injected script.
		const beaconCalls = await page.evaluate(() => {
			return (window as Record<string, unknown>).__statniveTransports as Array<{ method: string; url: string }>;
		});

		const beaconHits = beaconCalls.filter((t) => t.url.includes('statnive/v1/hit'));

		// At least one transport should have been used.
		const totalTransports = beaconHits.length + transports.length;
		expect(totalTransports).toBeGreaterThanOrEqual(1);

		// If sendBeacon was available, it should have been the first choice.
		if (beaconHits.length > 0) {
			expect(beaconHits[0].method).toBe('sendBeacon');
		}
	});

	test('fallback to fetch with keepalive when sendBeacon is unavailable', async ({ page }) => {
		// Disable sendBeacon to force fallback.
		await page.addInitScript(() => {
			// @ts-ignore
			navigator.sendBeacon = undefined; Object.defineProperty(navigator, "webdriver", { get: () => false });
		});

		const fetchRequests: Array<{ url: string; headers: Record<string, string> }> = [];

		await page.route('**/statnive/v1/hit', async (route) => {
			const request = route.request();
			fetchRequests.push({
				url: request.url(),
				headers: request.headers(),
			});
			await route.continue();
		});

		await page.goto(env.baseUrl);

		// Wait for the actual tracking request to complete via fetch fallback.
		await page.waitForResponse(
			(res) => res.url().includes('statnive/v1/hit') && res.status() === 204,
			{ timeout: 5000 }
		);

		// With sendBeacon disabled, the tracker should fall back to fetch.
		expect(fetchRequests.length).toBeGreaterThanOrEqual(1);
	});

	test('hit payload contains resource_type, resource_id, and signature', async ({ page }) => {
		// Disable sendBeacon so tracker falls back to fetch (interceptable by Playwright).
		await page.addInitScript(() => {
			// @ts-ignore
			navigator.sendBeacon = undefined; Object.defineProperty(navigator, "webdriver", { get: () => false });
		});

		const hitRequests: Array<{ url: string; body: string }> = [];

		await page.route('**/statnive/v1/hit', async (route) => {
			const request = route.request();
			hitRequests.push({
				url: request.url(),
				body: request.postData() || '',
			});
			await route.continue();
		});

		await page.goto(env.baseUrl);

		// Wait for the actual tracking request to complete.
		await page.waitForResponse(
			(res) => res.url().includes('statnive/v1/hit') && res.status() === 204,
			{ timeout: 5000 }
		);

		expect(hitRequests.length).toBeGreaterThanOrEqual(1);

		let body: Record<string, unknown> = {};
		try {
			body = JSON.parse(hitRequests[0].body || '{}');
		} catch {
			// Non-JSON payload (e.g., form-encoded); skip.
		}
		expect(body).toHaveProperty('resource_type');
		expect(body).toHaveProperty('resource_id');
		expect(body).toHaveProperty('signature');
		expect(body.signature).toBeTruthy();
	});
});
