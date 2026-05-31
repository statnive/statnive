/**
 * Ask me! — Search escape-hatch.
 *
 * Verifies:
 *  - Cmd+K / Ctrl+K focuses the search input.
 *  - Typing 2+ characters reveals the suggestions listbox.
 *  - Selecting a suggestion opens the AnswerModal.
 *  - Esc closes the modal and restores focus to the search input.
 *  - The search input retains its query after the modal closes
 *    (so the user can keep refining).
 */

import { test, expect } from '@playwright/test';
import { loginAsAdmin, navigateToAsk } from '../helpers/advisor';

test.describe('Ask me! — Search', () => {
	test.beforeEach(async ({ page }) => {
		await loginAsAdmin(page);
		await navigateToAsk(page);
		await page.waitForLoadState('networkidle');
	});

	test('Cmd+K (or Ctrl+K) focuses the search input', async ({ page }) => {
		const input = page.getByRole('combobox', { name: /Search questions/i });
		await expect(input).toBeVisible();

		// Press the OS-appropriate modifier+K.
		await page.keyboard.press(
			process.platform === 'darwin' ? 'Meta+K' : 'Control+K',
		);

		await expect(input).toBeFocused();
	});

	test('typing 2+ chars surfaces suggestions; clicking opens the answer modal', async ({ page }) => {
		const input = page.getByRole('combobox', { name: /Search questions/i });
		await input.fill('mobile');

		const suggestions = page.getByRole('listbox');
		await expect(suggestions).toBeVisible({ timeout: 5000 });

		// q81 — "How much traffic is mobile vs desktop?"
		await page.getByRole('option', { name: /mobile vs desktop/i }).first().click();

		const dialog = page.getByRole('dialog');
		await expect(dialog).toBeVisible();
		await expect(dialog).toHaveAttribute('aria-modal', 'true');
	});

	test('Esc closes the modal, restores focus to the search input, retains query', async ({ page }) => {
		const input = page.getByRole('combobox', { name: /Search questions/i });
		await input.fill('countries');

		await page.getByRole('option', { name: /countries are my visitors from/i })
			.first()
			.click();

		const dialog = page.getByRole('dialog');
		await expect(dialog).toBeVisible();

		await page.keyboard.press('Escape');
		await expect(dialog).not.toBeVisible();
		await expect(input).toBeFocused();
		await expect(input).toHaveValue('countries');
	});

	test('suggestions for a coming-soon Paid question render the coming-soon chip', async ({ page }) => {
		const input = page.getByRole('combobox', { name: /Search questions/i });
		await input.fill('revenue');

		const suggestions = page.getByRole('listbox');
		await expect(suggestions).toBeVisible({ timeout: 5000 });

		// At least one option is a Paid Revenue question and should carry
		// the italic "Coming soon" hint.
		await expect(suggestions.getByText('Coming soon').first()).toBeVisible();
	});
});
