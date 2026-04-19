/**
 * EX-1..EX-8 — Exclusions UI copy is accurate:
 *   "Tracking requests from these IPs or ranges are ignored — handy for
 *    hiding your own team. One per line. Supports CIDR (e.g., 10.0.0.0/8)
 *    and IPv6."
 *
 * The ip-filter mu-plugin reads X-Test-Client-IP and feeds it to
 * statnive_client_ip. Real frontend visits (anon context) are needed so
 * the tracker runs — the admin context redirects "/" to wp-admin.
 */

import { test, expect } from '../fixtures/auth';
import { setSettings, snapshotSettings, restoreSettings, truncateStatnive } from '../fixtures/settings';
import { newAnonContext, waitForViewCount } from '../fixtures/anon';
import { withClientIp } from '../fixtures/ip-spoof';
import { dbCount } from '../db-cli';
import { env } from '../env';

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
	test.beforeEach(async ({ page }) => {
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
		test(`${row.id} excluded="${row.excludedIp}" client="${row.clientIp}" → ${row.expected}`, async ({ page, browser }) => {
			await setSettings(page, { excluded_ips: row.excludedIp });

			const anon = await newAnonContext(browser);
			await withClientIp(anon.context, row.clientIp);
			await anon.page.goto(env.baseUrl);

			if (row.expected === 'blocked') {
				// Give the tracker time to try firing then confirm nothing landed.
				await anon.page.waitForTimeout(1500);
				expect(dbCount('statnive_views')).toBe(0);
			} else {
				await waitForViewCount(1);
				expect(dbCount('statnive_views')).toBeGreaterThanOrEqual(1);
			}

			await anon.close();
		});
	}

	test('EX-7 malformed entries do not block everyone — tracker still works for a non-matching IP', async ({ page, browser }) => {
		await setSettings(page, {
			excluded_ips: 'not-an-ip\n300.300.300.300\n10.0.0.1',
		});

		const anon = await newAnonContext(browser);
		await withClientIp(anon.context, '198.51.100.5');
		await anon.page.goto(env.baseUrl);
		await waitForViewCount(1);

		expect(dbCount('statnive_views')).toBeGreaterThanOrEqual(1);
		await anon.close();
	});

	test('EX-8 empty exclusion list does not block anyone', async ({ page, browser }) => {
		await setSettings(page, { excluded_ips: '' });

		const anon = await newAnonContext(browser);
		await withClientIp(anon.context, '198.51.100.5');
		await anon.page.goto(env.baseUrl);
		await waitForViewCount(1);

		expect(dbCount('statnive_views')).toBeGreaterThanOrEqual(1);
		await anon.close();
	});
});
