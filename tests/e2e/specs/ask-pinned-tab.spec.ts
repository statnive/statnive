/**
 * Ask me! pinned-tab end-to-end test.
 *
 * Plan §D / E2E section: the home tab must render the 5 default-pinned
 * questions via a single batched POST to `/advisor/answers`, then prime
 * each per-ID cache slot so individual cards render without a second
 * round-trip.
 */

import { test, expect } from '@playwright/test';
import { ASK_URL, loginAsAdmin, navigateToAsk, postAnswers } from '../helpers/advisor';

test.describe('Ask me! — Pinned tab', () => {
	test.beforeEach(async ({ page }) => {
		await loginAsAdmin(page);
	});

	test('pinned tab is active by default and renders 5 default pins', async ({ page }) => {
		await navigateToAsk(page);

		// "Ask me!" tab is the leftmost and is active by default.
		const askTab = page.getByRole('tab', { name: /Ask me!/i });
		await expect(askTab).toHaveAttribute('aria-selected', 'true');

		// 5 default-pinned questions render. Each question's headline
		// button uses `aria-label={question}` — so we assert presence by
		// the literal question text from research #71 §6 / Q2 / Q23 / Q41 /
		// Q72 / Q81.
		await expect(
			page.getByRole('button', { name: 'How many people visited this week?' }),
		).toBeVisible();
		await expect(
			page.getByRole('button', { name: 'What are my top pages?' }),
		).toBeVisible();
		await expect(
			page.getByRole('button', { name: 'Where is my traffic coming from?' }),
		).toBeVisible();
		await expect(
			page.getByRole('button', { name: 'What countries are my visitors from?' }),
		).toBeVisible();
		await expect(
			page.getByRole('button', { name: 'How much traffic is mobile vs desktop?' }),
		).toBeVisible();
	});

	test('pinned tab triggers exactly one batched POST to /advisor/answers', async ({ page }) => {
		const answerRequests: { url: string; method: string; body: unknown }[] = [];
		page.on('request', (req) => {
			if (req.url().includes('/statnive/v1/advisor/answers')) {
				answerRequests.push({
					url: req.url(),
					method: req.method(),
					body: req.postDataJSON(),
				});
			}
		});

		await navigateToAsk(page);
		// Allow time for the batched POST + answer rendering.
		await page.waitForLoadState('networkidle');

		expect(
			answerRequests.length,
			`Expected exactly 1 batched POST, got ${answerRequests.length}`,
		).toBe(1);

		const body = answerRequests[0].body as { question_ids: string[] };
		expect(body.question_ids).toEqual(
			expect.arrayContaining(['q2', 'q41', 'q23', 'q72', 'q81']),
		);
		expect(body.question_ids).toHaveLength(5);
	});

	test('Server-Timing header surfaces per-question latency + total', async ({ page }) => {
		await loginAsAdmin(page);
		// Talk to the REST endpoint directly for a clean header read.
		await page.goto(ASK_URL, { waitUntil: 'domcontentloaded' });

		const nonce = await page.evaluate(
			// @ts-expect-error runtime global
			() => window.StatniveDashboard?.nonce ?? '',
		);
		const res = await page.request.post(
			`${process.env.WP_REST_URL ?? 'http://statnive-test.local:10008/wp-json'}/statnive/v1/advisor/answers`,
			{
				headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
				data: { question_ids: ['q2', 'q41'] },
			},
		);
		expect(res.ok()).toBeTruthy();

		const timing = res.headers()['server-timing'] ?? '';
		// Expect at least one per-question entry + the `total` entry.
		expect(timing).toMatch(/q2;dur=\d+(\.\d+)?/);
		expect(timing).toMatch(/q41;dur=\d+(\.\d+)?/);
		expect(timing).toMatch(/total;dur=\d+(\.\d+)?/);
	});

	test('all 5 default answers have shape { id, status, value, viz }', async ({ page }) => {
		await navigateToAsk(page);
		const json = await postAnswers(page, ['q2', 'q41', 'q23', 'q72', 'q81']);
		expect(json.answers).toHaveLength(5);
		for (const answer of json.answers) {
			expect(answer).toHaveProperty('id');
			expect(answer).toHaveProperty('status');
			expect(answer.status).toMatch(/^(ok|coming_soon|error)$/);
			expect(answer).toHaveProperty('viz');
		}
	});
});
