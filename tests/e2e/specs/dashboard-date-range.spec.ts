// E2E tests for shared date range state (Roadmap 3.24).
// Validates that the date range persists across tabs, reloads, and URL entry.

import { test, expect, type Page } from '@playwright/test';
import { env } from '../env';

async function loginAsAdmin(page: Page): Promise<void> {
	await page.goto(`${env.baseUrl}/wp-login.php`);
	await page.fill('#user_login', env.adminUser);
	await page.fill('#user_pass', env.adminPassword);
	await page.click('#wp-submit');
	await page.waitForURL('**/wp-admin/**', { timeout: 10000 });
}

async function navigateToDashboard(page: Page): Promise<void> {
	await page.goto(`${env.baseUrl}/wp-admin/admin.php?page=statnive`, {
		waitUntil: 'networkidle',
	});
}

/** Get the date range picker group. */
function dateRangeGroup(page: Page) {
	return page.getByRole('group', { name: 'Date range' });
}

/** Get a specific preset button by label within the date range picker. */
function presetButton(page: Page, label: string) {
	return dateRangeGroup(page).getByRole('button', { name: label });
}

/** Assert that a preset button has the active (primary) style. */
async function expectPresetActive(page: Page, label: string) {
	const btn = presetButton(page, label);
	await expect(btn).toBeVisible();
	await expect(btn).toHaveClass(/bg-primary/);
}

/** Assert that a preset button does NOT have the active style. */
async function expectPresetInactive(page: Page, label: string) {
	const btn = presetButton(page, label);
	await expect(btn).not.toHaveClass(/bg-primary/);
}

test.describe('Dashboard Date Range — Shared State', () => {
	test.beforeEach(async ({ page }) => {
		await loginAsAdmin(page);
	});

	test('default range is 7 Days on fresh load', async ({ page }) => {
		await navigateToDashboard(page);
		await expectPresetActive(page, '7 Days');
	});

	test('date range persists across tab navigation', async ({ page }) => {
		await navigateToDashboard(page);

		// Select "30 Days"
		await presetButton(page, '30 Days').click();
		await expectPresetActive(page, '30 Days');

		// Click Referrers tab
		await page.getByRole('link', { name: /Referrers/ }).click();
		await expectPresetActive(page, '30 Days');

		// Click Geography tab
		await page.getByRole('link', { name: /Geography/ }).click();
		await expectPresetActive(page, '30 Days');

		// Go back to Overview
		await page.getByRole('link', { name: /Overview/ }).click();
		await expectPresetActive(page, '30 Days');
	});

	test('date range survives page reload', async ({ page }) => {
		await navigateToDashboard(page);

		// Select "This Month"
		await presetButton(page, 'This Month').click();
		await expectPresetActive(page, 'This Month');

		// Verify URL contains the range param
		expect(page.url()).toContain('range=this-month');

		// Reload the page
		await page.reload({ waitUntil: 'networkidle' });
		await expectPresetActive(page, 'This Month');
	});

	test('URL reflects selected date range', async ({ page }) => {
		await navigateToDashboard(page);

		await presetButton(page, 'Today').click();
		expect(page.url()).toContain('range=today');

		await presetButton(page, '30 Days').click();
		expect(page.url()).toContain('range=30d');

		await presetButton(page, 'Last Month').click();
		expect(page.url()).toContain('range=last-month');
	});

	test('direct URL with range param loads correct preset', async ({ page }) => {
		await page.goto(
			`${env.baseUrl}/wp-admin/admin.php?page=statnive#/geography?range=last-month`,
			{ waitUntil: 'networkidle' },
		);
		await expectPresetActive(page, 'Last Month');
		await expectPresetInactive(page, '7 Days');
	});

	test('invalid range in URL falls back to 7 Days', async ({ page }) => {
		await page.goto(
			`${env.baseUrl}/wp-admin/admin.php?page=statnive#/?range=invalid`,
			{ waitUntil: 'networkidle' },
		);
		await expectPresetActive(page, '7 Days');
	});

	test('realtime page has no date picker', async ({ page }) => {
		await page.goto(
			`${env.baseUrl}/wp-admin/admin.php?page=statnive#/realtime`,
			{ waitUntil: 'networkidle' },
		);
		await expect(dateRangeGroup(page)).not.toBeVisible();
	});

	test('API requests include correct date params when range changes', async ({ page }) => {
		await navigateToDashboard(page);

		// Intercept summary API calls.
		const apiCalls: string[] = [];
		await page.route('**/wp-json/statnive/v1/summary*', async (route) => {
			apiCalls.push(route.request().url());
			await route.continue();
		});

		// Select "Today" — should trigger API calls with today's date as both from and to.
		await presetButton(page, 'Today').click();

		// Wait for at least one API call.
		await page.waitForTimeout(1000);

		const todayStr = new Date().toISOString().slice(0, 10);
		const hasTodayCall = apiCalls.some(
			(url) => url.includes(`from=${todayStr}`) && url.includes(`to=${todayStr}`),
		);
		expect(hasTodayCall).toBe(true);
	});
});
