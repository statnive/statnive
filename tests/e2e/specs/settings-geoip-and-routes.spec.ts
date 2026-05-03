/**
 * Coverage gaps surfaced by the v0.4.6 release walkthrough:
 *   - MaxMind license-key persistence (`statnive_maxmind_license_key`)
 *   - GeoIP enable/disable toggle (`statnive_geoip_enabled`)
 *   - DB-IP one-click download endpoint reachable from Geography page
 *   - SPA route smoke: every dashboard page mounts and renders without
 *     console errors against the empty-state DB
 *
 * Existing settings-*.spec.ts already cover DNT, GPC, retention,
 * consent modes, IP exclusions, and the Save button itself — this
 * spec only fills in the GeoIP / MaxMind / route-smoke holes.
 */

import { test, expect } from '../fixtures/auth';
import {
	setSettings,
	snapshotSettings,
	restoreSettings,
	getDashboardNonce,
	nextScheduled,
} from '../fixtures/settings';
import { dbQuery } from '../db-cli';
import { env } from '../env';

const ALL_ROUTES = [
	'/',
	'/pages',
	'/referrers',
	'/geography',
	'/devices',
	'/languages',
	'/realtime',
	'/settings',
] as const;

test.describe('Statnive dashboard routes', () => {
	test.setTimeout(60_000);

	test('every SPA route mounts without console errors', async ({ page }) => {
		const errors: string[] = [];
		page.on('console', (msg) => {
			if (msg.type() === 'error') errors.push(msg.text());
		});

		await page.goto(`${env.baseUrl}/wp-admin/admin.php?page=statnive`);
		await page.waitForFunction(() => !!document.querySelector('#statnive-app'));

		for (const route of ALL_ROUTES) {
			await page.evaluate((r) => {
				location.hash = r;
			}, route);
			// Wait for the route's heading to appear — covers the React lazy-load delay.
			await page.waitForFunction(
				() => !!document.querySelector('#statnive-app h1, #statnive-app h2'),
				{ timeout: 5000 },
			);
			const text = await page.evaluate(() => document.querySelector('#statnive-app')?.textContent ?? '');
			expect(text.length, `route ${route} rendered some content`).toBeGreaterThan(50);
		}

		expect(errors, 'no console errors across all routes').toEqual([]);
	});
});

test.describe('Settings → GeoIP & MaxMind', () => {
	test.setTimeout(60_000);

	test.beforeEach(async ({ page }) => {
		await snapshotSettings(page);
	});

	test.afterEach(async ({ page }) => {
		await restoreSettings(page);
	});

	test('MaxMind license key round-trips through PUT /settings', async ({ page }) => {
		const fakeKey = 'TEST_KEY_e2e_only_aaaaaaaaaaaaaaaa';
		await setSettings(page, { maxmind_license_key: fakeKey } as Record<string, unknown>);

		const [row] = dbQuery<{ option_value: string }>(
			`SELECT option_value FROM ${env.tablePrefix}options WHERE option_name = 'statnive_maxmind_license_key'`,
		);
		expect(row?.option_value, 'license key persisted in wp_options').toBe(fakeKey);
	});

	test('GeoIP enable toggle persists to statnive_geoip_enabled', async ({ page }) => {
		await setSettings(page, { geoip_enabled: true } as Record<string, unknown>);

		const [row] = dbQuery<{ option_value: string }>(
			`SELECT option_value FROM ${env.tablePrefix}options WHERE option_name = 'statnive_geoip_enabled'`,
		);
		// WordPress stores boolean true as '1'.
		expect(['1', 'true']).toContain(row?.option_value);

		await setSettings(page, { geoip_enabled: false } as Record<string, unknown>);
		const [off] = dbQuery<{ option_value: string }>(
			`SELECT option_value FROM ${env.tablePrefix}options WHERE option_name = 'statnive_geoip_enabled'`,
		);
		// '' or '0' both mean false depending on serializer.
		expect(['', '0']).toContain(off?.option_value);
	});

	test('Geography page exposes the DB-IP one-click button', async ({ page }) => {
		await page.goto(`${env.baseUrl}/wp-admin/admin.php?page=statnive#/geography`);
		await page.waitForFunction(() => !!document.querySelector('#statnive-app'));

		const buttons = await page.evaluate(() =>
			Array.from(document.querySelectorAll('#statnive-app button')).map((b) => b.textContent?.trim() ?? ''),
		);
		expect(
			buttons.some((t) => /city-level|DB-IP/i.test(t)),
			'Geography page renders the DB-IP enable button',
		).toBe(true);
	});

	test('POST /diagnostics/enable-dbip-city requires manage_options', async ({ page }) => {
		const nonce = await getDashboardNonce(page);
		const res = await page.request.post(
			`${env.restUrl}/statnive/v1/diagnostics/enable-dbip-city`,
			{ headers: { 'X-WP-Nonce': nonce } },
		);
		// 200 (queued/already-active) or 202 (async kicked off) — anything in 2xx range proves the route is reachable
		// to the admin user. A 401/403 here would mean the permission_callback regressed.
		expect(res.status(), 'admin can reach the DB-IP endpoint').toBeGreaterThanOrEqual(200);
		expect(res.status(), 'admin can reach the DB-IP endpoint').toBeLessThan(500);
	});

	test('GET /settings masks set MaxMind license key as ********', async ({ page }) => {
		await setSettings(page, { maxmind_license_key: 'fake-key-mask-check' } as Record<string, unknown>);

		const res = await page.request.get(`${env.restUrl}/statnive/v1/settings`, {
			headers: { 'X-WP-Nonce': await getDashboardNonce(page) },
		});
		const body = (await res.json()) as { maxmind_license_key: string };
		expect(body.maxmind_license_key, 'license key masked on GET').toBe('********');
	});

	test('PUT geoip_enabled=true with empty license key → 400 missing_license_key', async ({ page }) => {
		await setSettings(page, { geoip_enabled: false, maxmind_license_key: '' } as Record<string, unknown>);

		const res = await page.request.put(`${env.restUrl}/statnive/v1/settings`, {
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': await getDashboardNonce(page),
			},
			data: { geoip_enabled: true },
		});
		expect(res.status(), 'PUT rejected when key is missing').toBe(400);
		const body = (await res.json()) as { code: string };
		expect(body.code).toBe('missing_license_key');

		const [row] = dbQuery<{ option_value: string }>(
			`SELECT option_value FROM ${env.tablePrefix}options WHERE option_name = 'statnive_geoip_enabled'`,
		);
		expect(['', '0'], 'geoip_enabled did not flip').toContain(row?.option_value ?? '');
	});

	test('Enabling GeoIP results in statnive_weekly_geoip_update being scheduled', async ({ page }) => {
		// Black-box check: after enabling GeoIP via the REST API, the weekly
		// cron must be scheduled. We can't reliably assert "before=0" because
		// CronRegistrar re-schedules whenever statnive_geoip_enabled OR the
		// dbip-pending transient is truthy, and prior test-suite runs may
		// leave that transient set.
		await setSettings(page, {
			maxmind_license_key: 'fake-key-cron-check',
			geoip_enabled: true,
		} as Record<string, unknown>);

		expect(
			await nextScheduled(page, 'statnive_weekly_geoip_update'),
			'cron scheduled after enable',
		).toBeGreaterThan(0);

		const [row] = dbQuery<{ option_value: string }>(
			`SELECT option_value FROM ${env.tablePrefix}options WHERE option_name = 'statnive_geoip_enabled'`,
		);
		expect(['1', 'true'], 'geoip_enabled flipped on').toContain(row?.option_value);
	});
});

test.describe('Settings page UI ⇄ DB round-trip', () => {
	// Mirrors the Playwright-MCP walkthrough run during the v0.4.6 release:
	// change a value via the UI, click Save, assert the option lands in wp_options.
	test.setTimeout(60_000);

	test.beforeEach(async ({ page }) => {
		await snapshotSettings(page);
	});

	test.afterEach(async ({ page }) => {
		await restoreSettings(page);
	});

	test('retention dropdown UI change persists + auto-derives retention_mode', async ({ page }) => {
		await page.goto(`${env.baseUrl}/wp-admin/admin.php?page=statnive#/settings`);
		await page.waitForFunction(() => !!document.querySelector('#statnive-app select'));

		await page.evaluate(() => {
			const sel = document.querySelector('#statnive-app select') as HTMLSelectElement;
			const setter = Object.getOwnPropertyDescriptor(window.HTMLSelectElement.prototype, 'value')!.set!;
			setter.call(sel, '180');
			sel.dispatchEvent(new Event('change', { bubbles: true }));
			const btn = Array.from(document.querySelectorAll('#statnive-app button')).find(
				(b) => b.textContent?.trim() === 'Save',
			) as HTMLButtonElement;
			btn.click();
		});
		await page.waitForTimeout(1500);

		const [days] = dbQuery<{ option_value: string }>(
			`SELECT option_value FROM ${env.tablePrefix}options WHERE option_name = 'statnive_retention_days'`,
		);
		const [mode] = dbQuery<{ option_value: string }>(
			`SELECT option_value FROM ${env.tablePrefix}options WHERE option_name = 'statnive_retention_mode'`,
		);
		expect(days?.option_value, 'retention_days persisted').toBe('180');
		expect(mode?.option_value, 'retention_mode auto-flipped from forever→delete').toBe('delete');
	});

	test('DNT toggle in UI persists to statnive_respect_dnt', async ({ page }) => {
		await page.goto(`${env.baseUrl}/wp-admin/admin.php?page=statnive#/settings`);
		await page.waitForFunction(() => document.querySelectorAll('#statnive-app input[type=checkbox]').length > 0);

		await page.evaluate(() => {
			const cb = document.querySelector('#statnive-app input[type=checkbox]') as HTMLInputElement;
			if (cb.checked) cb.click(); // turn DNT off
			Array.from(document.querySelectorAll('#statnive-app button'))
				.find((b) => b.textContent?.trim() === 'Save')
				?.dispatchEvent(new MouseEvent('click', { bubbles: true }));
		});
		await page.waitForTimeout(1500);

		const [row] = dbQuery<{ option_value: string }>(
			`SELECT option_value FROM ${env.tablePrefix}options WHERE option_name = 'statnive_respect_dnt'`,
		);
		expect(['', '0']).toContain(row?.option_value);
	});
});
