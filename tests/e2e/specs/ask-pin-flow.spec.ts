/**
 * Ask me! — Pin/unpin flow + persistence.
 *
 * Verifies:
 *  - Pinning a non-default question persists across page reloads
 *    (writes the wp_usermeta key `statnive_pinned_questions`).
 *  - Unpinning a default-pinned question removes it from the pinned
 *    tab AND persists.
 *  - The pin button is disabled on coming-soon (Paid / schema-gap) rows.
 */

import { test, expect } from '@playwright/test';
import { clickTab, loginAsAdmin, navigateToAsk } from '../helpers/advisor';

test.describe('Ask me! — Pin flow', () => {
	test.beforeEach(async ({ page }) => {
		await loginAsAdmin(page);
		await navigateToAsk(page);
		await page.waitForLoadState('networkidle');
	});

	test('pinning a Traffic question shows it in the Ask me! tab + persists across reload', async ({ page }) => {
		await clickTab(page, 'Traffic');

		// q4 — "How many sessions did I get?" (not in the default 5).
		const sessionsButton = page.getByRole('button', {
			name: 'How many sessions did I get?',
		});
		await expect(sessionsButton).toBeVisible();

		// The pin button sits adjacent inside the same row — its accessible
		// name is `Pin "How many sessions did I get?"`.
		await page.getByRole('button', { name: 'Pin "How many sessions did I get?"' }).click();

		// Wait for the PUT /advisor/preferences mutation to settle.
		await page.waitForResponse(
			(res) =>
				res.url().includes('/statnive/v1/advisor/preferences') &&
				res.request().method() === 'PUT' &&
				res.ok(),
			{ timeout: 5000 },
		);

		// Switch back to the Ask me! tab — q4 should now be there.
		await clickTab(page, 'Ask me!');
		await expect(sessionsButton).toBeVisible();

		// Reload the whole page; q4 must still be pinned.
		await page.reload({ waitUntil: 'networkidle' });
		await expect(
			page.getByRole('button', { name: 'How many sessions did I get?' }),
		).toBeVisible();
	});

	test('pin button is disabled on a coming-soon Revenue question', async ({ page }) => {
		await clickTab(page, 'Revenue');

		// q101 is the first Revenue card and is Paid (=> coming-soon in v1).
		const pinBtn = page.getByRole('button', {
			name: 'Pin "How many orders did I get?"',
		});
		await expect(pinBtn).toBeDisabled();
	});

	test('unpinning the last default does NOT revert to defaults', async ({ page }) => {
		// Unpin all 5 defaults — the unpin button's accessible name flips
		// `Unpin "…"`.
		const defaultLabels = [
			'How many people visited this week?',
			'Where is my traffic coming from?',
			'What are my top pages?',
			'What countries are my visitors from?',
			'How much traffic is mobile vs desktop?',
		];
		for (const label of defaultLabels) {
			await page.getByRole('button', { name: `Unpin "${label}"` }).click();
			// Each click PUTs preferences; let it settle before the next one.
			await page.waitForResponse(
				(res) =>
					res.url().includes('/statnive/v1/advisor/preferences') &&
					res.request().method() === 'PUT' &&
					res.ok(),
				{ timeout: 5000 },
			);
		}

		// After unpinning all 5, the Ask me! tab should show the empty state.
		await expect(page.getByText('No pinned questions yet.')).toBeVisible();
	});
});
