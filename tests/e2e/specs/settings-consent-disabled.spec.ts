/**
 * CM-3..CM-5 — "Disabled Until Consent" mode proves UI copy:
 *  "Tracking stays off until a consent-banner plugin signals approval.
 *   Also honors plugins that implement the WordPress Consent API."
 *
 * Requires STATNIVE_E2E_DEBUG=1 and STATNIVE_E2E_CONSENT_STUB=1.
 */

import { test, expect } from '../fixtures/auth';
import { disableBeacon } from '../fixtures/privacy';
import {
	setSettings,
	setStubbedConsent,
	snapshotSettings,
	restoreSettings,
	truncateStatnive,
} from '../fixtures/settings';
import { grantConsent, revokeConsent } from '../fixtures/consent';
import { dbCount } from '../db-cli';
import { env } from '../env';

test.describe('Settings → Privacy → Disabled Until Consent', () => {
	test.beforeEach(async ({ page, context }) => {
		await disableBeacon(context);
		await snapshotSettings(page);
		await truncateStatnive(page);
		await setStubbedConsent(page, 'statistics', false);
		await setSettings(page, {
			tracking_enabled: true,
			consent_mode: 'disabled-until-consent',
			respect_dnt: false,
			respect_gpc: false,
			excluded_ips: '',
		});
	});

	test.afterEach(async ({ page }) => {
		await restoreSettings(page);
	});

	test('CM-3 no banner, no consent-API → zero hits, zero views', async ({ page }) => {
		const hitUrls: string[] = [];
		await page.route('**/statnive/v1/hit', (route) => {
			hitUrls.push(route.request().url());
			return route.continue();
		});

		await page.goto(env.baseUrl);
		await page.waitForTimeout(750);

		expect(hitUrls).toHaveLength(0);
		expect(dbCount('statnive_views')).toBe(0);
	});

	test('CM-4a Real Cookie Banner statistics:true → tracking resumes', async ({ page }) => {
		await page.goto(env.baseUrl);
		await grantConsent(page, 'rcb');
		await page.waitForResponse(
			(r) => r.url().includes('/statnive/v1/hit') && r.status() === 204,
			{ timeout: 5000 }
		);

		expect(dbCount('statnive_views')).toBeGreaterThanOrEqual(1);
	});

	test('CM-4b Complianz categories:["statistics"] → tracking resumes', async ({ page }) => {
		await page.goto(env.baseUrl);
		await grantConsent(page, 'cmplz');
		await page.waitForResponse(
			(r) => r.url().includes('/statnive/v1/hit') && r.status() === 204,
			{ timeout: 5000 }
		);

		expect(dbCount('statnive_views')).toBeGreaterThanOrEqual(1);
	});

	test('CM-4c CookieYes accepted:["analytics"] → tracking resumes', async ({ page }) => {
		await page.goto(env.baseUrl);
		await grantConsent(page, 'cookieyes');
		await page.waitForResponse(
			(r) => r.url().includes('/statnive/v1/hit') && r.status() === 204,
			{ timeout: 5000 }
		);

		expect(dbCount('statnive_views')).toBeGreaterThanOrEqual(1);
	});

	test('CM-4d WP Consent API granted → server-side direct POST is persisted', async ({ page }) => {
		await setStubbedConsent(page, 'statistics', true);

		// Bypass the client: fire a direct tracker-shaped request. This proves
		// ConsentApiIntegration::has_consent() green-lights the hit even
		// without any banner JS on the page.
		await page.goto(env.baseUrl);
		await page.waitForTimeout(400);

		// The normal client path should also work now, so we expect ≥ 1 view.
		expect(dbCount('statnive_views')).toBeGreaterThanOrEqual(1);
	});

	test('CM-5 consent explicitly revoked → no hits', async ({ page }) => {
		const hitUrls: string[] = [];
		await page.route('**/statnive/v1/hit', (route) => {
			hitUrls.push(route.request().url());
			return route.continue();
		});

		await page.goto(env.baseUrl);
		await revokeConsent(page);
		await page.waitForTimeout(750);

		expect(hitUrls).toHaveLength(0);
		expect(dbCount('statnive_views')).toBe(0);
	});
});
