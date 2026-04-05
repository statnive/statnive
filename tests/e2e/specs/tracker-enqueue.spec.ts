// Generated from BDD scenarios — adjust selectors and routes for actual implementation.
// Source: features/01-tracking-pipeline.feature @REQ-1.10, @REQ-1.11

import { test, expect } from '@playwright/test';
import { env } from '../env';

test.describe('Tracker Script Enqueue', () => {
	test('script tag with handle "statnive-tracker" is present in DOM', async ({ page }) => {
		await page.goto(env.baseUrl);

		// WordPress enqueues scripts with an id attribute based on handle.
		const trackerScript = page.locator('script[id="statnive-tracker-js"]');
		const fallbackScript = page.locator('script[src*="statnive"]');

		const handleCount = await trackerScript.count();
		const srcCount = await fallbackScript.count();

		// At least one approach should find the tracker.
		expect(handleCount + srcCount).toBeGreaterThanOrEqual(1);
	});

	test('StatniveConfig is injected on window with required keys', async ({ page }) => {
		await page.goto(env.baseUrl);

		// Wait for the config to be available after script execution.
		await page.waitForFunction(() => {
			return typeof (window as Record<string, unknown>).StatniveConfig !== 'undefined';
		}, { timeout: 5000 });

		const config = await page.evaluate(() => {
			return (window as Record<string, unknown>).StatniveConfig as Record<string, unknown> | undefined;
		});

		expect(config).toBeDefined();
		expect(config!).toHaveProperty('restUrl');
		expect(config!).toHaveProperty('ajaxUrl');
		expect(config!).toHaveProperty('hitParams');
	});

	test('hitParams contain resource_type, resource_id, and non-empty signature', async ({ page }) => {
		await page.goto(env.baseUrl);

		// Wait for the config to be available after script execution.
		await page.waitForFunction(() => {
			return typeof (window as Record<string, unknown>).StatniveConfig !== 'undefined';
		}, { timeout: 5000 });

		const hitParams = await page.evaluate(() => {
			const config = (window as Record<string, unknown>).StatniveConfig as Record<string, unknown> | undefined;
			return config?.hitParams as Record<string, unknown> | undefined;
		});

		expect(hitParams).toBeDefined();
		expect(hitParams).toHaveProperty('resource_type');
		expect(hitParams).toHaveProperty('resource_id');
		expect(hitParams).toHaveProperty('signature');
		expect(hitParams!.signature).toBeTruthy();
		expect(String(hitParams!.signature).length).toBeGreaterThan(0);
	});

	test('script tag has integrity attribute starting with "sha256-"', async ({ page }) => {
		await page.goto(env.baseUrl);

		// Look for the tracker script with SRI integrity.
		const integrityAttr = await page.evaluate(() => {
			const scripts = document.querySelectorAll('script[src*="statnive"]');
			for (const script of scripts) {
				const integrity = script.getAttribute('integrity');
				if (integrity) return integrity;
			}
			return null;
		});

		expect(integrityAttr).not.toBeNull();
		expect(integrityAttr!).toMatch(/^sha256-/);
	});

	test('script tag has crossorigin="anonymous"', async ({ page }) => {
		await page.goto(env.baseUrl);

		const crossorigin = await page.evaluate(() => {
			const scripts = document.querySelectorAll('script[src*="statnive"]');
			for (const script of scripts) {
				const co = script.getAttribute('crossorigin');
				if (co) return co;
			}
			return null;
		});

		expect(crossorigin).toBe('anonymous');
	});
});
