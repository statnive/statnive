/**
 * Ask me! — Coming-soon questions are non-expandable and not pinnable.
 *
 * Per plan §"Free vs Paid handling (v1)", every Paid-per-CSV question
 * (cat 8 / 9 / 10 plus the cross-filter Paid rows in 2-7) and every
 * schema-gap question (cat 3 + cat 8) renders a quiet italic
 * "Coming soon" chip in lieu of an answer. The header button must be
 * non-expandable (aria-disabled) and the pin button must be disabled.
 */

import { test, expect } from '@playwright/test';
import { clickTab, loginAsAdmin, navigateToAsk, postAnswers } from '../helpers/advisor';

test.describe('Ask me! — Coming-soon questions', () => {
	test.beforeEach(async ({ page }) => {
		await loginAsAdmin(page);
		await navigateToAsk(page);
		await page.waitForLoadState('networkidle');
	});

	test('every Revenue question renders coming-soon (Paid → Growth v2)', async ({ page }) => {
		await clickTab(page, 'Revenue');

		// 15 Revenue questions. Each header button is aria-disabled.
		const triggers = page.locator(
			'[role="tabpanel"] button[aria-expanded="false"][aria-disabled="true"]',
		);
		await expect(triggers).toHaveCount(15);

		// Pin buttons on Revenue rows are disabled (HTML `disabled` attr).
		const disabledPins = page.locator(
			'[role="tabpanel"] button[aria-pressed][disabled]',
		);
		expect(await disabledPins.count()).toBeGreaterThan(0);
	});

	test('clicking a coming-soon Revenue card does NOT expand or POST', async ({ page }) => {
		await clickTab(page, 'Revenue');

		const answerCalls: string[] = [];
		page.on('request', (req) => {
			if (req.url().includes('/statnive/v1/advisor/answers')) {
				answerCalls.push(req.url());
			}
		});

		const trigger = page.getByRole('button', {
			name: 'How many orders did I get?',
		});
		await trigger.click();
		await page.waitForTimeout(400);

		await expect(trigger).toHaveAttribute('aria-expanded', 'false');
		expect(
			answerCalls.length,
			'Coming-soon click must not trigger /advisor/answers',
		).toBe(0);
	});

	test('POST /advisor/answers returns a coming_soon envelope (no DB work)', async ({ page }) => {
		// Hit the resolver directly. q101 = "How many orders did I get?"
		// (Paid). Expect `status: 'coming_soon'`, `reason: 'paid_growth_v2'`.
		const json = await postAnswers(page, ['q101']);
		expect(json.answers).toHaveLength(1);
		const answer = json.answers[0];
		expect(answer.status).toBe('coming_soon');
		// reason field carries either 'paid_growth_v2' or 'schema_gap_v1_1'.
		expect((answer as unknown as { reason: string }).reason).toBe('paid_growth_v2');
		// `value` is null on coming-soon.
		expect(answer.value).toBeNull();
	});

	test('schema-gap question (cat 3 q40) returns coming_soon with reason schema_gap_v1_1', async ({ page }) => {
		const json = await postAnswers(page, ['q40']);
		const answer = json.answers[0];
		expect(answer.status).toBe('coming_soon');
		expect((answer as unknown as { reason: string }).reason).toBe('schema_gap_v1_1');
	});

	test('coming-soon caption text differs by reason', async ({ page }) => {
		// Revenue tab (Paid) — "Unlocks in Statnive Growth v2."
		await clickTab(page, 'Revenue');
		await expect(
			page.getByText('Unlocks in Statnive Growth v2.').first(),
		).toBeVisible();

		// Pages tab includes q40 (schema-gap) — "Live in v1.1, auto-enables …"
		await clickTab(page, 'Pages');
		await expect(
			page.getByText(/Live in v1\.1/i).first(),
		).toBeVisible();
	});
});
