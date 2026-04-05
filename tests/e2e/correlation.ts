/**
 * Ground-truth correlation helpers for Statnive E2E tests.
 *
 * Compares tracker-recorded data against a ground-truth mu-plugin
 * that independently records every page load.
 *
 * This enables Tier 1 (DB-oracle) assertions:
 *   ground_truth_count == analytics_count ± threshold
 */

import { env } from './env';
import type { Page } from '@playwright/test';

/**
 * Query the ground-truth table recorded by the mu-plugin.
 *
 * @param page - Playwright page.
 * @returns Array of ground-truth records.
 */
export async function queryGroundTruth(
	page: Page
): Promise<Array<{ url: string; timestamp: string; user_id: number }>> {
	const url = `${env.restUrl}/statnive/v1/debug/ground-truth`;

	const response = await page.request.get(url, {
		headers: {
			'X-WP-Nonce': await getRestNonce(page),
		},
	});

	if (!response.ok()) {
		return [];
	}

	return (await response.json()) as Array<{
		url: string;
		timestamp: string;
		user_id: number;
	}>;
}

/**
 * Calculate correlation between ground-truth and analytics data.
 *
 * @param groundTruthCount - Number of page loads recorded by mu-plugin.
 * @param analyticsCount - Number of views recorded by Statnive tracker.
 * @returns Correlation ratio (1.0 = perfect, 0.0 = no correlation).
 */
export function correlationRatio(
	groundTruthCount: number,
	analyticsCount: number
): number {
	if (groundTruthCount === 0) {
		return analyticsCount === 0 ? 1.0 : 0.0;
	}
	return Math.min(analyticsCount / groundTruthCount, 1.0);
}

/**
 * Get REST nonce (shared helper).
 */
async function getRestNonce(page: Page): Promise<string> {
	return page.evaluate(() => {
		const settings = (window as Record<string, unknown>).wpApiSettings as
			| { nonce: string }
			| undefined;
		return settings?.nonce || '';
	});
}
