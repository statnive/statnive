/**
 * Ask me! — Category tab navigation + lazy-load on expand.
 *
 * Verifies:
 *  - Switching to a category tab does NOT fire any /advisor/answers calls
 *    (cards are collapsed by default).
 *  - Expanding a single card lazily POSTs that question and renders the
 *    answer.
 *  - The user's pinned questions surface at the TOP of every category
 *    body (`pins first` ordering).
 */

import { test, expect } from '@playwright/test';
import { clickTab, loginAsAdmin, navigateToAsk } from '../helpers/advisor';

test.describe('Ask me! — Category tabs', () => {
	test.beforeEach(async ({ page }) => {
		await loginAsAdmin(page);
	});

	test('switching to Traffic tab fires no answer POSTs', async ({ page }) => {
		await navigateToAsk(page);
		await page.waitForLoadState('networkidle'); // Lets the pinned-tab batch complete.

		const after: string[] = [];
		page.on('request', (req) => {
			if (req.url().includes('/statnive/v1/advisor/answers')) after.push(req.url());
		});

		await clickTab(page, 'Traffic');
		await page.waitForTimeout(500); // Let any deferred work flush.

		expect(after.length, 'Category-tab activation must be lazy').toBe(0);
	});

	test('expanding a Traffic question lazy-loads exactly one answer', async ({ page }) => {
		await navigateToAsk(page);
		await page.waitForLoadState('networkidle');
		await clickTab(page, 'Traffic');

		const requests: Array<{ body: unknown }> = [];
		page.on('request', (req) => {
			if (req.url().includes('/statnive/v1/advisor/answers')) {
				requests.push({ body: req.postDataJSON() });
			}
		});

		// Expand "How many pageviews did I get?" (q3).
		await page
			.getByRole('button', { name: 'How many pageviews did I get?' })
			.click();

		await page.waitForResponse(
			(res) =>
				res.url().includes('/statnive/v1/advisor/answers') && res.ok(),
			{ timeout: 5000 },
		);

		expect(requests).toHaveLength(1);
		const body = requests[0].body as { question_ids: string[] };
		expect(body.question_ids).toEqual(['q3']);
	});

	test('pins appear first in every category tab', async ({ page }) => {
		await navigateToAsk(page);
		await page.waitForLoadState('networkidle');

		// "Traffic" includes q2 (pinned by default) — assert q2's title is
		// the first interactive question in the tabpanel.
		await clickTab(page, 'Traffic');
		const buttons = await page.locator(
			'[role="tabpanel"] button[aria-expanded]',
		).all();
		expect(buttons.length).toBeGreaterThan(0);
		const firstLabel = await buttons[0].getAttribute('aria-label');
		expect(firstLabel).toBe('How many people visited this week?'); // q2

		// "Referrers" includes q41 (pinned by default).
		await clickTab(page, 'Referrers');
		const refButtons = await page.locator(
			'[role="tabpanel"] button[aria-expanded]',
		).all();
		const firstRefLabel = await refButtons[0].getAttribute('aria-label');
		expect(firstRefLabel).toBe('Where is my traffic coming from?'); // q41
	});

	test('keyboard navigation: left/right arrow keys cycle tabs', async ({ page }) => {
		await navigateToAsk(page);
		const askTab = page.getByRole('tab', { name: /Ask me!/i });
		await askTab.focus();

		await page.keyboard.press('ArrowRight');
		await expect(
			page.getByRole('tab', { name: 'Traffic' }),
		).toHaveAttribute('aria-selected', 'true');

		await page.keyboard.press('ArrowRight');
		await expect(
			page.getByRole('tab', { name: 'Real-time' }),
		).toHaveAttribute('aria-selected', 'true');

		await page.keyboard.press('ArrowLeft');
		await expect(
			page.getByRole('tab', { name: 'Traffic' }),
		).toHaveAttribute('aria-selected', 'true');

		await page.keyboard.press('Home');
		await expect(askTab).toHaveAttribute('aria-selected', 'true');

		await page.keyboard.press('End');
		await expect(
			page.getByRole('tab', { name: 'Events' }),
		).toHaveAttribute('aria-selected', 'true');
	});
});
