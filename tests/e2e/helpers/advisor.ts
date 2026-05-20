/**
 * Shared helpers for Ask me! Advisor E2E specs.
 *
 * Keeps the per-spec boilerplate small: login → navigate to /ask →
 * wait for the inventory load → assert basic chrome. Then specs layer
 * on their own assertions.
 */

import type { Page } from '@playwright/test';
import { expect } from '@playwright/test';
import { env } from '../env';

export const ASK_URL = `${env.baseUrl}/wp-admin/admin.php?page=statnive-ask`;

export async function loginAsAdmin(page: Page): Promise<void> {
	await page.goto(`${env.baseUrl}/wp-login.php`);
	const userField = page.locator('#user_login');
	if (await userField.isVisible({ timeout: 1000 }).catch(() => false)) {
		await userField.fill(env.adminUser);
		await page.fill('#user_pass', env.adminPassword);
		await page.click('#wp-submit');
		await page.waitForURL('**/wp-admin/**', { timeout: 10000 });
	}
}

export async function navigateToAsk(page: Page): Promise<void> {
	await page.goto(ASK_URL, { waitUntil: 'networkidle' });
	// Wait for the tab strip to render (`role="tablist"`).
	await expect(page.getByRole('tablist')).toBeVisible({ timeout: 10000 });
}

/**
 * Helper: click the named tab in the 11-tab strip on the Ask me! page.
 *
 * `name` is the short label rendered by `QuestionTabs.SHORT_LABEL`
 * (e.g., "Ask me!", "Traffic", "Real-time", "Pages", ...).
 */
export async function clickTab(page: Page, name: string): Promise<void> {
	const tabList = page.getByRole('tablist');
	await tabList.getByRole('tab', { name }).first().click();
}

/**
 * Read the REST nonce that the dashboard bootstrap inlines as
 * `window.StatniveDashboard.nonce`. Used by tests that want to talk
 * directly to the `/advisor/*` REST endpoints.
 */
export async function readRestNonce(page: Page): Promise<string> {
	return await page.evaluate(() => {
		// @ts-expect-error — runtime global injected by ReactHandler.
		return window.StatniveDashboard?.nonce ?? '';
	});
}

/** POST /advisor/answers and return the JSON body. */
export async function postAnswers(
	page: Page,
	questionIds: string[],
	from?: string,
	to?: string,
): Promise<{
	answers: Array<{ id: string; status: string; value: unknown }>;
	from: string;
	to: string;
}> {
	const nonce = await readRestNonce(page);
	const res = await page.request.post(`${env.restUrl}/statnive/v1/advisor/answers`, {
		headers: {
			'X-WP-Nonce': nonce,
			'Content-Type': 'application/json',
		},
		data: {
			question_ids: questionIds,
			...(from ? { from } : {}),
			...(to ? { to } : {}),
		},
	});
	expect(res.ok(), `POST /advisor/answers failed: ${res.status()}`).toBeTruthy();
	return await res.json();
}

/**
 * Run an axe-core accessibility scan on the current page and return
 * the violations array. We inject axe at runtime via a CDN-free
 * approach: load the `axe-core` package script from `node_modules`.
 *
 * Callers usually filter the violations to `serious` / `critical`
 * before asserting because color contrast checks on brand tokens
 * can produce moderate-impact noise.
 */
export async function runAxe(page: Page): Promise<
	Array<{ id: string; impact: string; nodes: number }>
> {
	// Inject axe-core runtime. Path is relative to the plugin root.
	await page.addScriptTag({
		path: 'node_modules/axe-core/axe.min.js',
	});
	const results = await page.evaluate(async () => {
		// @ts-expect-error — runtime injection of axe global.
		const r = await window.axe.run(document, {
			runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag22aa'] },
		});
		return r.violations.map(
			(v: { id: string; impact: string; nodes: unknown[] }) => ({
				id: v.id,
				impact: v.impact,
				nodes: v.nodes.length,
			}),
		);
	});
	return results;
}
