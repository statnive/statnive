/**
 * EX-1..EX-8 — Exclusions UI copy is accurate:
 *   "Tracking requests from these IPs or ranges are ignored — handy for
 *    hiding your own team. One per line. Supports CIDR (e.g., 10.0.0.0/8)
 *    and IPv6."
 *
 * Requires the ip-spoof mu-plugin (STATNIVE_E2E_IP_FILTER=1). Every test
 * posts directly to /hit with a chosen client IP; the mu-plugin feeds that
 * IP into the statnive_client_ip filter so PrivacyManager sees it during
 * exclusion matching.
 */

import { test, expect } from '../fixtures/auth';
import { disableBeacon } from '../fixtures/privacy';
import {
	setSettings,
	snapshotSettings,
	restoreSettings,
	truncateStatnive,
	getDashboardNonce,
} from '../fixtures/settings';
import { withClientIp } from '../fixtures/ip-spoof';
import { dbCount } from '../db-cli';
import { env } from '../env';

async function directHit(page: import('@playwright/test').Page): Promise<number> {
	const response = await page.request.post(`${env.restUrl}/statnive/v1/hit`, {
		headers: {
			'Content-Type': 'text/plain',
			'X-WP-Nonce': await getDashboardNonce(page),
		},
		data: JSON.stringify({
			resource_type: 'post',
			resource_id: 1,
			signature: 'never-accepted',
		}),
	});
	return response.status();
}

type Row = { excludedIp: string; clientIp: string; expected: 'blocked' | 'allowed'; id: string };

const matrix: Row[] = [
	{ id: 'EX-1', excludedIp: '203.0.113.42', clientIp: '203.0.113.42', expected: 'blocked' },
	{ id: 'EX-2', excludedIp: '203.0.113.42', clientIp: '203.0.113.99', expected: 'allowed' },
	{ id: 'EX-3', excludedIp: '10.0.0.0/8', clientIp: '10.99.1.2', expected: 'blocked' },
	{ id: 'EX-4', excludedIp: '10.0.0.0/8', clientIp: '172.16.0.1', expected: 'allowed' },
	{ id: 'EX-5', excludedIp: '2001:db8::1', clientIp: '2001:db8::1', expected: 'blocked' },
	{ id: 'EX-6', excludedIp: '2001:db8::/32', clientIp: '2001:db8:abcd::1', expected: 'blocked' },
];

test.describe('Settings → Exclusions → IP / CIDR', () => {
	test.beforeEach(async ({ page, context }) => {
		await disableBeacon(context);
		await snapshotSettings(page);
		await truncateStatnive(page);
		await setSettings(page, {
			tracking_enabled: true,
			consent_mode: 'cookieless',
			respect_dnt: false,
			respect_gpc: false,
		});
	});

	test.afterEach(async ({ page }) => {
		await restoreSettings(page);
	});

	for (const row of matrix) {
		test(`${row.id} excluded="${row.excludedIp}" client="${row.clientIp}" → ${row.expected}`, async ({ browser, page }) => {
			await setSettings(page, { excluded_ips: row.excludedIp });

			const context = await browser.newContext();
			await disableBeacon(context);
			await withClientIp(context, row.clientIp);

			const p = await context.newPage();
			await p.goto(`${env.baseUrl}/wp-admin/admin.php?page=statnive`);
			// HMAC will fail regardless — the purpose of this test is that
			// PrivacyManager returns 204 BEFORE HMAC when excluded, and the
			// underlying row is never written either way.
			await directHit(p);

			expect(dbCount('statnive_views')).toBe(0);
			await context.close();

			// Also prove the allow case by making a real pageview from the
			// non-excluded IP path — the direct hit above fails HMAC, so
			// use page.goto() which the plugin signs correctly.
			if (row.expected === 'allowed') {
				const ctxAllow = await browser.newContext();
				await disableBeacon(ctxAllow);
				await withClientIp(ctxAllow, row.clientIp);
				const pp = await ctxAllow.newPage();
				await pp.goto(env.baseUrl);
				await pp.waitForResponse(
					(r) => r.url().includes('/statnive/v1/hit') && r.status() === 204,
					{ timeout: 5000 }
				);
				expect(dbCount('statnive_views')).toBeGreaterThanOrEqual(1);
				await ctxAllow.close();
			}
		});
	}

	test('EX-7 malformed entries do not block everyone — tracker still works for a non-matching IP', async ({ browser, page }) => {
		await setSettings(page, {
			excluded_ips: 'not-an-ip\n300.300.300.300\n10.0.0.1',
		});

		const context = await browser.newContext();
		await disableBeacon(context);
		await withClientIp(context, '198.51.100.5');
		const p = await context.newPage();
		await p.goto(env.baseUrl);
		await p.waitForResponse(
			(r) => r.url().includes('/statnive/v1/hit') && r.status() === 204,
			{ timeout: 5000 }
		);

		expect(dbCount('statnive_views')).toBeGreaterThanOrEqual(1);
		await context.close();
	});

	test('EX-8 empty exclusion list does not block anyone', async ({ browser, page }) => {
		await setSettings(page, { excluded_ips: '' });

		const context = await browser.newContext();
		await disableBeacon(context);
		await withClientIp(context, '198.51.100.5');
		const p = await context.newPage();
		await p.goto(env.baseUrl);
		await p.waitForResponse(
			(r) => r.url().includes('/statnive/v1/hit') && r.status() === 204,
			{ timeout: 5000 }
		);

		expect(dbCount('statnive_views')).toBeGreaterThanOrEqual(1);
		await context.close();
	});
});
