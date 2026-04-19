/**
 * Anonymous-visitor helpers for tracker-side specs.
 *
 * The admin session we load from `fixtures/auth.ts` gives us nonce-aware
 * REST access for set-up (snapshotSettings / setSettings / truncate) but
 * it also poisons frontend navigation — a logged-in admin hitting "/"
 * often redirects to wp-admin on sites that customise the home URL. The
 * tracker does not fire on wp-admin, so tests that expect a `/hit`
 * request must visit the frontend in a fresh context that carries no
 * cookies.
 *
 * Usage:
 *
 *   const anon = await newAnonContext(browser);
 *   await anon.page.goto(env.baseUrl);
 *   …
 *   await anon.close();
 */

import type { Browser, BrowserContext, BrowserContextOptions, Page } from '@playwright/test';
import { disableBeacon } from './privacy';
import { dbCount } from '../db-cli';

export interface AnonContext {
	context: BrowserContext;
	page: Page;
	close(): Promise<void>;
}

/**
 * Poll `statnive_views` for at least `min` rows.
 *
 * `page.waitForResponse` races the tracker's fetch, which completes during
 * page load; once the response is delivered the listener attached after
 * goto() misses it. The DB is the source of truth anyway — poll it.
 */
export async function waitForViewCount(min: number, timeoutMs = 8000): Promise<number> {
	const deadline = Date.now() + timeoutMs;
	let last = 0;
	while (Date.now() < deadline) {
		last = dbCount('statnive_views');
		if (last >= min) {
			return last;
		}
		await new Promise((r) => setTimeout(r, 200));
	}
	return last;
}

export async function newAnonContext(
	browser: Browser,
	options: BrowserContextOptions = {}
): Promise<AnonContext> {
	const context = await browser.newContext({
		...options,
		storageState: undefined,
	});
	await disableBeacon(context);
	const page = await context.newPage();
	return {
		context,
		page,
		close: () => context.close(),
	};
}
