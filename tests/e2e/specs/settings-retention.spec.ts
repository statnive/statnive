/**
 * R-30..R-3650 — Retention dropdown copy is accurate:
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
import { newAnonContext, waitForViewCount } from '../fixtures/anon';
import { dbCount, dbQuery } from '../db-cli';
import { env } from '../env';

async function seedTwoPageviews(browser: import('@playwright/test').Browser): Promise<void> {
	const a = await newAnonContext(browser);
	await a.page.goto(env.baseUrl);
	await waitForViewCount(1);
	await a.close();

	const b = await newAnonContext(browser);
	await b.page.goto(`${env.baseUrl}/?cachebuster=${Date.now()}`);
	await waitForViewCount(1);
	await b.close();
}

test.describe('Settings → Data Retention', () => {
	// Each test does admin REST setup + 2 anon pageviews + backdate + purge.
	// Comfortably exceeds the 30s default on Local.
	test.setTimeout(60_000);

	test.beforeEach(async ({ page }) => {
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
		test(`R-${days} retention=${days} + mode=delete → rows older than ${days}d are purged`, async ({ page, browser }) => {
			await setSettings(page, { retention_days: days, retention_mode: 'delete' });
			await seedTwoPageviews(browser);

			const [older] = dbQuery<{ ID: string }>(
				`SELECT ID FROM ${env.tablePrefix}statnive_views ORDER BY ID ASC LIMIT 1`
			);
			await backdate(page, 'views', 'viewed_at', days + 2, { ID: Number(older.ID) });

			await runPurge(page);

			expect(dbCount('statnive_views')).toBe(1);
		});
	}

	test('R-3650 retention=Forever + mode=forever → purge runs but deletes nothing', async ({ page, browser }) => {
		await setSettings(page, { retention_days: 3650, retention_mode: 'forever' });
		await seedTwoPageviews(browser);
		const before = dbCount('statnive_views');
		expect(before).toBeGreaterThanOrEqual(2);

		const [older] = dbQuery<{ ID: string }>(
			`SELECT ID FROM ${env.tablePrefix}statnive_views ORDER BY ID ASC LIMIT 1`
		);
		await backdate(page, 'views', 'viewed_at', 4000, { ID: Number(older.ID) });

		await runPurge(page);

		expect(dbCount('statnive_views')).toBe(before);
	});
});
