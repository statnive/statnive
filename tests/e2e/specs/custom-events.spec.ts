// Generated from BDD scenarios — adjust selectors and routes for actual implementation.
// Source: features/08-custom-events-engagement.feature @REQ-5.1, @REQ-5.12

import { test, expect } from '@playwright/test';
import { env } from '../env';

test.describe('Custom Events via JavaScript API', () => {
	test('statnive() call sends event with name and properties', async ({ page }) => {
		// Disable sendBeacon so tracker falls back to fetch (interceptable by Playwright).
		await page.addInitScript(() => {
			// @ts-ignore
			navigator.sendBeacon = undefined; Object.defineProperty(navigator, "webdriver", { get: () => false });
		});

		const eventRequests: Array<{ url: string; body: string; status: number }> = [];
		await page.route('**/statnive/v1/event', async (route) => {
			const request = route.request();
			eventRequests.push({
				url: request.url(),
				body: request.postData() || '',
				status: 0,
			});
			const response = await route.fetch();
			eventRequests[eventRequests.length - 1].status = response.status();
			await route.fulfill({ response });
		});

		await page.goto(env.baseUrl, { waitUntil: 'networkidle' });

		// Trigger a custom event via the Statnive JS API.
		await page.evaluate(() => {
			if (typeof (window as Record<string, unknown>).statnive === 'function') {
				(window as Record<string, (...args: unknown[]) => void>).statnive('Signup_Click', { plan: 'pro', source: 'header' });
			}
		});

		// Wait for the event request to complete.
		await page.waitForResponse(
			(res) => res.url().includes('statnive/v1/event') && (res.status() === 204 || res.status() === 200),
			{ timeout: 5000 }
		);

		// Verify an event request was sent.
		expect(eventRequests.length).toBeGreaterThanOrEqual(1);

		// Verify the payload contains expected fields.
		let body: Record<string, unknown> = {};
		try {
			body = JSON.parse(eventRequests[0].body || '{}');
		} catch {
			// Non-JSON payload (e.g., form-encoded); skip.
		}
		expect(body).toHaveProperty('event_name', 'Signup_Click');
		expect(body).toHaveProperty('properties');
		expect((body.properties as Record<string, unknown>)).toHaveProperty('plan', 'pro');
		expect((body.properties as Record<string, unknown>)).toHaveProperty('source', 'header');
	});

	test('event request includes HMAC signature', async ({ page }) => {
		// Disable sendBeacon so tracker falls back to fetch (interceptable by Playwright).
		await page.addInitScript(() => {
			// @ts-ignore
			navigator.sendBeacon = undefined; Object.defineProperty(navigator, "webdriver", { get: () => false });
		});

		const eventRequests: Array<{ body: string }> = [];
		await page.route('**/statnive/v1/event', async (route) => {
			eventRequests.push({ body: route.request().postData() || '' });
			await route.continue();
		});

		await page.goto(env.baseUrl, { waitUntil: 'networkidle' });

		await page.evaluate(() => {
			if (typeof (window as Record<string, unknown>).statnive === 'function') {
				(window as Record<string, (...args: unknown[]) => void>).statnive('Test_Event', { key: 'value' });
			}
		});

		// Wait for the event request to complete.
		await page.waitForResponse(
			(res) => res.url().includes('statnive/v1/event') && (res.status() === 204 || res.status() === 200),
			{ timeout: 5000 }
		);

		expect(eventRequests.length).toBeGreaterThanOrEqual(1);

		let body: Record<string, unknown> = {};
		try {
			body = JSON.parse(eventRequests[0].body || '{}');
		} catch {
			// Non-JSON payload (e.g., form-encoded); skip.
		}
		expect(body).toHaveProperty('signature');
		expect(body.signature).toBeTruthy();
	});

	test('event endpoint returns 204 for valid event', async ({ page }) => {
		// Disable sendBeacon so tracker falls back to fetch (interceptable by Playwright).
		await page.addInitScript(() => {
			// @ts-ignore
			navigator.sendBeacon = undefined; Object.defineProperty(navigator, "webdriver", { get: () => false });
		});

		const eventStatuses: number[] = [];
		await page.route('**/statnive/v1/event', async (route) => {
			const response = await route.fetch();
			eventStatuses.push(response.status());
			await route.fulfill({ response });
		});

		await page.goto(env.baseUrl, { waitUntil: 'networkidle' });

		await page.evaluate(() => {
			if (typeof (window as Record<string, unknown>).statnive === 'function') {
				(window as Record<string, (...args: unknown[]) => void>).statnive('Button_Click', { position: 'hero' });
			}
		});

		// Wait for the event response.
		await page.waitForResponse(
			(res) => res.url().includes('statnive/v1/event') && (res.status() === 204 || res.status() === 200),
			{ timeout: 5000 }
		);

		expect(eventStatuses.length).toBeGreaterThan(0);
		expect(eventStatuses[0]).toBe(204);
	});

	test('event with invalid signature returns 403', async ({ request }) => {
		const response = await request.post(`${env.restUrl}/statnive/v1/event`, {
			headers: { 'Content-Type': 'text/plain' },
			data: JSON.stringify({
				event_name: 'Fake_Event',
				properties: { key: 'value' },
				resource_type: 'post',
				resource_id: 1,
				signature: 'invalid-tampered-signature',
			}),
		});

		expect(response.status()).toBe(403);
	});
});
