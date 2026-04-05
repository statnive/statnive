// Generated from BDD scenarios — adjust selectors and routes for actual implementation.
// Source: features/07-privacy-compliance.feature @REQ-7.7

import { test, expect } from '@playwright/test';
import { env } from '../env';

test.describe('Privacy Invariants — Zero Storage Footprint', () => {
	test('zero cookies set by Statnive after page load', async ({ page }) => {
		await page.goto(env.baseUrl, { waitUntil: 'networkidle' });

		const cookies = await page.context().cookies();
		const statniveCookies = cookies.filter(
			(c) =>
				c.name.toLowerCase().includes('statnive') ||
				c.name.toLowerCase().includes('stn_') ||
				c.name.toLowerCase().includes('_stn')
		);

		expect(statniveCookies.length).toBe(0);
	});

	test('zero localStorage keys from Statnive', async ({ page }) => {
		await page.goto(env.baseUrl, { waitUntil: 'networkidle' });

		const statniveLocalStorageKeys = await page.evaluate(() => {
			const keys: string[] = [];
			for (let i = 0; i < localStorage.length; i++) {
				const key = localStorage.key(i);
				if (key && (key.toLowerCase().includes('statnive') || key.toLowerCase().includes('stn_'))) {
					keys.push(key);
				}
			}
			return keys;
		});

		expect(statniveLocalStorageKeys.length).toBe(0);
	});

	test('zero sessionStorage keys from Statnive', async ({ page }) => {
		await page.goto(env.baseUrl, { waitUntil: 'networkidle' });

		const statniveSessionStorageKeys = await page.evaluate(() => {
			const keys: string[] = [];
			for (let i = 0; i < sessionStorage.length; i++) {
				const key = sessionStorage.key(i);
				if (key && (key.toLowerCase().includes('statnive') || key.toLowerCase().includes('stn_'))) {
					keys.push(key);
				}
			}
			return keys;
		});

		expect(statniveSessionStorageKeys.length).toBe(0);
	});

	test('no canvas fingerprinting via toDataURL', async ({ page }) => {
		// Intercept toDataURL calls before page loads.
		await page.addInitScript(() => {
			(window as Record<string, unknown>).__canvasToDataURLCalls = 0;
			(window as Record<string, unknown>).__canvasStacks = [];

			const originalToDataURL = HTMLCanvasElement.prototype.toDataURL;
			HTMLCanvasElement.prototype.toDataURL = function (...args: Parameters<typeof originalToDataURL>) {
				(window as Record<string, unknown>).__canvasToDataURLCalls =
					((window as Record<string, unknown>).__canvasToDataURLCalls as number) + 1;

				const stack = new Error().stack || '';
				((window as Record<string, unknown>).__canvasStacks as string[]).push(stack);

				return originalToDataURL.apply(this, args);
			};
		});

		await page.goto(env.baseUrl, { waitUntil: 'networkidle' });

		// Check stacks for any Statnive-originated canvas calls.
		const canvasStacks = await page.evaluate(() => {
			return ((window as Record<string, unknown>).__canvasStacks as string[]) || [];
		});

		const statniveCanvasCalls = canvasStacks.filter(
			(stack) => stack.includes('statnive') || stack.includes('stn_')
		);

		expect(statniveCanvasCalls.length).toBe(0);
	});

	test('no document.cookie writes by Statnive tracker', async ({ page }) => {
		// Monitor cookie setter calls.
		await page.addInitScript(() => {
			(window as Record<string, unknown>).__cookieWrites = [];

			const originalDescriptor = Object.getOwnPropertyDescriptor(Document.prototype, 'cookie');
			if (originalDescriptor && originalDescriptor.set) {
				const originalSet = originalDescriptor.set;
				Object.defineProperty(document, 'cookie', {
					get: originalDescriptor.get,
					set(value: string) {
						const stack = new Error().stack || '';
						((window as Record<string, unknown>).__cookieWrites as Array<{ value: string; stack: string }>).push({
							value,
							stack,
						});
						originalSet.call(document, value);
					},
					configurable: true,
				});
			}
		});

		await page.goto(env.baseUrl, { waitUntil: 'networkidle' });

		const cookieWrites = await page.evaluate(() => {
			return (window as Record<string, unknown>).__cookieWrites as Array<{ value: string; stack: string }>;
		});

		const statniveCookieWrites = cookieWrites.filter(
			(cw) => cw.stack.includes('statnive') || cw.value.toLowerCase().includes('statnive')
		);

		expect(statniveCookieWrites.length).toBe(0);
	});
});
