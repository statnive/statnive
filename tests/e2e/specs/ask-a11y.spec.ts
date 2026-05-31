/**
 * Ask me! — axe-core accessibility gate.
 *
 * Asserts zero `serious` or `critical` violations across 3 page states:
 *   1. Ask me! pinned tab freshly loaded.
 *   2. A category tab open (Traffic) with one card expanded.
 *   3. The search-results AnswerModal open.
 *
 * Plan §G.10: WCAG 2.2 AA target. Moderate/minor violations are logged
 * to the evidence pack but don't fail the gate (per the plan they're a
 * tuning surface, not a release blocker).
 */

import { test, expect } from '@playwright/test';
import { clickTab, loginAsAdmin, navigateToAsk, runAxe } from '../helpers/advisor';

test.describe('Ask me! — Accessibility (axe-core)', () => {
	test.beforeEach(async ({ page }) => {
		await loginAsAdmin(page);
		await navigateToAsk(page);
		await page.waitForLoadState('networkidle');
	});

	test('pinned tab — no serious or critical violations', async ({ page }) => {
		const violations = await runAxe(page);
		const blockers = violations.filter(
			(v) => v.impact === 'serious' || v.impact === 'critical',
		);
		expect(
			blockers,
			`axe-core flagged ${blockers.length} serious/critical violation(s): ` +
				blockers.map((v) => `${v.id}(${v.nodes})`).join(', '),
		).toEqual([]);
	});

	test('category tab + expanded card — no serious or critical violations', async ({ page }) => {
		await clickTab(page, 'Traffic');
		await page
			.getByRole('button', { name: 'How many pageviews did I get?' })
			.click();
		await page.waitForResponse(
			(res) =>
				res.url().includes('/statnive/v1/advisor/answers') && res.ok(),
			{ timeout: 5000 },
		);

		const violations = await runAxe(page);
		const blockers = violations.filter(
			(v) => v.impact === 'serious' || v.impact === 'critical',
		);
		expect(blockers).toEqual([]);
	});

	test('open AnswerModal — no serious or critical violations', async ({ page }) => {
		const input = page.getByRole('combobox', { name: /Search questions/i });
		await input.fill('countries');
		await page
			.getByRole('option', { name: /countries are my visitors from/i })
			.first()
			.click();
		await expect(page.getByRole('dialog')).toBeVisible();

		const violations = await runAxe(page);
		const blockers = violations.filter(
			(v) => v.impact === 'serious' || v.impact === 'critical',
		);
		expect(blockers).toEqual([]);
	});
});
