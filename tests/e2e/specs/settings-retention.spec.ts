/**
 * R-1..R-6 — Retention dropdown copy is accurate:
 *   30 / 90 / 180 / 365 days → aged rows deleted by data-purge.
 *   Forever (3650 + mode=forever) → nothing deleted.
 */

import { test, expect } from '../fixtures/auth';
import {
	setSettings,
	snapshotSettings,
	restoreSettings,
	truncateStatnive,
	backdate,
	runPurge,
} from '../fixtures/settings';
import { dbCount, dbQuery } from '../db-cli';
import { env } from '../env';

async function seedOneAndOne(page: import('@playwright/test').Page): Promise<void> {
	// Two fresh pageviews before backdating, then backdate one of them.
	await page.goto(env.baseUrl);
	await page.waitForResponse(
		(r) => r.url().includes('/statnive/v1/hit') && r.status() === 204,
		{ timeout: 5000 }
	);
	await page.goto(`${env.baseUrl}/?cachebuster=${Date.now()}`);
	await page.waitForResponse(
		(r) => r.url().includes('/statnive/v1/hit') && r.status() === 204,
		{ timeout: 5000 }
	);
}

test.describe('Settings → Data Retention', () => {
	test.beforeEach(async ({ page, context }) => {
		await context.addInitScript(() => {
			// @ts-expect-error force fetch fallback
			navigator.sendBeacon = undefined;
		});
		await snapshotSettings(page);
		await truncateStatnive(page);
		await setSettings(page, {
			tracking_enabled: true,
			consent_mode: 'cookieless',
			respect_dnt: false,
			respect_gpc: false,
			excluded_ips: '',
		});
	});

	test.afterEach(async ({ page }) => {
		await restoreSettings(page);
	});

	for (const days of [30, 90, 180, 365] as const) {
		test(`R-${days} retention=${days} + mode=delete → rows older than ${days}d are purged`, async ({ page }) => {
			await setSettings(page, { retention_days: days, retention_mode: 'delete' });
			await seedOneAndOne(page);

			// Pick the first inserted view and age it past the cutoff.
			const [older] = dbQuery<{ ID: string }>(
				`SELECT ID FROM ${env.tablePrefix}statnive_views ORDER BY ID ASC LIMIT 1`
			);
			await backdate(page, 'views', 'viewed_at', days + 2, { ID: Number(older.ID) });

			await runPurge(page);

			const remaining = dbCount('statnive_views');
			// Exactly the fresh row survives — the aged one is gone.
			expect(remaining).toBe(1);
		});
	}

	test('R-3650 retention=Forever + mode=forever → purge runs but deletes nothing', async ({ page }) => {
		await setSettings(page, { retention_days: 3650, retention_mode: 'forever' });
		await seedOneAndOne(page);
		const before = dbCount('statnive_views');
		expect(before).toBeGreaterThanOrEqual(2);

		// Age one row to simulate the edge — Forever must still protect it.
		const [older] = dbQuery<{ ID: string }>(
			`SELECT ID FROM ${env.tablePrefix}statnive_views ORDER BY ID ASC LIMIT 1`
		);
		await backdate(page, 'views', 'viewed_at', 4000, { ID: Number(older.ID) });

		await runPurge(page);

		expect(dbCount('statnive_views')).toBe(before);
	});
});
