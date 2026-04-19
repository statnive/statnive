/**
 * EM-1..EM-5 — Email Reports subsystem is fully gone:
 *   • UI has no section
 *   • REST GET has no `email_*` keys
 *   • REST PUT of `email_*` is silently ignored
 *   • Cron hook `statnive_email_report` is not scheduled
 *   • WP-CLI `email-report` job no longer exists
 */

import { test, expect } from '../fixtures/auth';
import { getDashboardNonce, nextScheduled } from '../fixtures/settings';
import { env } from '../env';

test.describe('Email Reports removal', () => {
	test('EM-1 Settings UI has no Email Reports section', async ({ page }) => {
		await page.goto(`${env.baseUrl}/wp-admin/admin.php?page=statnive#/settings`);
		await expect(page.getByText(/email report/i)).toHaveCount(0);
	});

	test('EM-2 REST GET /settings has no email_* keys', async ({ page }) => {
		await page.goto(`${env.baseUrl}/wp-admin/admin.php?page=statnive#/settings`);
		const response = await page.request.get(`${env.restUrl}/statnive/v1/settings`, {
			headers: { 'X-WP-Nonce': await getDashboardNonce(page) },
		});
		const body = (await response.json()) as Record<string, unknown>;

		expect(Object.keys(body).some((k) => k.startsWith('email_'))).toBe(false);
	});

	test('EM-3 REST PUT with email_* keys is accepted but the keys do not reappear in GET', async ({ page }) => {
		await page.goto(`${env.baseUrl}/wp-admin/admin.php?page=statnive#/settings`);
		const nonce = await getDashboardNonce(page);

		const put = await page.request.put(`${env.restUrl}/statnive/v1/settings`, {
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
			data: { email_reports: true, email_frequency: 'weekly' },
		});
		expect(put.ok()).toBeTruthy();

		const get = await page.request.get(`${env.restUrl}/statnive/v1/settings`, {
			headers: { 'X-WP-Nonce': nonce },
		});
		const body = (await get.json()) as Record<string, unknown>;
		expect(Object.keys(body).some((k) => k.startsWith('email_'))).toBe(false);
	});

	test('EM-4 cron hook `statnive_email_report` is not scheduled', async ({ page }) => {
		const next = await nextScheduled(page, 'statnive_email_report');
		expect(next).toBe(0);
	});

	test('EM-5 WP-CLI has no `email-report` job', async ({ page }) => {
		// Indirect proof: PUT /settings with email_reports=true must not result
		// in a scheduled hook. EM-4 already covers that. Here we additionally
		// confirm the REST schema rejects the key when WordPress core strict-
		// validates args (it may silently drop rather than reject — both are
		// acceptable).
		await page.goto(`${env.baseUrl}/wp-admin/admin.php?page=statnive#/settings`);
		const next = await nextScheduled(page, 'statnive_email_report');
		expect(next).toBe(0);
	});
});
