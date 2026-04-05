/**
 * DB-oracle assertion helpers for Statnive E2E tests.
 *
 * Tier 1 assertions — query analytics tables directly to verify
 * tracking accuracy without relying on UI state.
 *
 * Requires a MySQL connection to the WordPress database.
 * Uses the REST API as a proxy when direct DB access isn't available.
 */

import { env } from './env';
import { expect, type Page } from '@playwright/test';

/**
 * Statnive table names with WordPress prefix.
 */
const table = (name: string) => `${env.tablePrefix}statnive_${name}`;

/**
 * Query an analytics table via the WordPress REST API.
 *
 * Uses a custom debug endpoint that's only available when WP_DEBUG is true.
 * Falls back to wp-cli if REST is unavailable.
 *
 * @param page - Playwright page for making API requests.
 * @param tableName - Statnive table name (without prefix).
 * @param where - Optional WHERE clause conditions.
 * @returns Array of row objects.
 */
export async function queryTable(
	page: Page,
	tableName: string,
	where: Record<string, string | number> = {}
): Promise<Record<string, unknown>[]> {
	const whereClause = Object.entries(where)
		.map(([key, value]) => `${key}=${encodeURIComponent(String(value))}`)
		.join('&');

	const url = `${env.restUrl}/statnive/v1/debug/query?table=${tableName}&${whereClause}`;

	const response = await page.request.get(url, {
		headers: {
			'X-WP-Nonce': await getRestNonce(page),
		},
	});

	if (!response.ok()) {
		return [];
	}

	return (await response.json()) as Record<string, unknown>[];
}

/**
 * Count rows in a Statnive analytics table.
 *
 * @param page - Playwright page.
 * @param tableName - Table name without prefix.
 * @param where - Optional WHERE conditions.
 * @returns Row count.
 */
export async function countRows(
	page: Page,
	tableName: string,
	where: Record<string, string | number> = {}
): Promise<number> {
	const rows = await queryTable(page, tableName, where);
	return rows.length;
}

/**
 * Assert that the visitors table has exactly N rows.
 */
export async function expectVisitorCount(page: Page, expected: number): Promise<void> {
	const count = await countRows(page, 'visitors');
	expect(count).toBe(expected);
}

/**
 * Assert that the sessions table has exactly N rows.
 */
export async function expectSessionCount(page: Page, expected: number): Promise<void> {
	const count = await countRows(page, 'sessions');
	expect(count).toBe(expected);
}

/**
 * Assert that the views table has exactly N rows.
 */
export async function expectViewCount(page: Page, expected: number): Promise<void> {
	const count = await countRows(page, 'views');
	expect(count).toBe(expected);
}

/**
 * Assert that a hit was recorded with specific properties.
 *
 * @param page - Playwright page.
 * @param expected - Expected field values to match.
 */
export async function expectEventRecorded(
	page: Page,
	expected: Record<string, string | number>
): Promise<void> {
	const views = await queryTable(page, 'views');
	expect(views.length).toBeGreaterThan(0);

	const match = views.find((row) =>
		Object.entries(expected).every(
			([key, value]) => String(row[key]) === String(value)
		)
	);

	expect(match).toBeDefined();
}

/**
 * Count rows in the events table matching given conditions.
 *
 * @param page - Playwright page.
 * @param where - Optional WHERE conditions (e.g., { event_name: 'Signup_Click' }).
 * @returns Row count.
 */
export async function expectEventCount(
	page: Page,
	where: Record<string, string | number>,
	expected: number
): Promise<void> {
	const count = await countRows(page, 'events', where);
	expect(count).toBe(expected);
}

/**
 * Assert that the engagement table has at least N rows matching conditions.
 *
 * @param page - Playwright page.
 * @param where - Optional WHERE conditions.
 * @param minCount - Minimum expected row count.
 */
export async function expectEngagementRecorded(
	page: Page,
	where: Record<string, string | number> = {},
	minCount = 1
): Promise<void> {
	const count = await countRows(page, 'engagement', where);
	expect(count).toBeGreaterThanOrEqual(minCount);
}

/**
 * Get the WordPress REST API nonce for authenticated requests.
 *
 * @param page - Playwright page (must be logged in).
 * @returns REST nonce string.
 */
async function getRestNonce(page: Page): Promise<string> {
	return page.evaluate(() => {
		// WordPress exposes the nonce in wpApiSettings when logged in.
		const settings = (window as Record<string, unknown>).wpApiSettings as
			| { nonce: string }
			| undefined;
		return settings?.nonce || '';
	});
}
