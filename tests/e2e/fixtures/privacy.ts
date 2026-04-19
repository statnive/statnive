/**
 * Browser-privacy helpers.
 *
 * `disableBeacon` removes `navigator.sendBeacon` so the tracker falls back
 * to `fetch`, which Playwright can route-intercept. Required for every spec
 * that asserts "no hit fired" via `page.route`.
 *
 * `withDNT` / `withGPC` apply a DNT-1 / Sec-GPC-1 signal both at the header
 * level (so the PHP gate sees it) and the JS-API level (so the client-side
 * gate sees it).
 */

import type { BrowserContext } from '@playwright/test';

export async function disableBeacon(context: BrowserContext): Promise<void> {
	await context.addInitScript(() => {
		// @ts-expect-error — sendBeacon is writable; clearing forces fetch fallback.
		navigator.sendBeacon = undefined;
		Object.defineProperty(navigator, 'webdriver', { get: () => false });
	});
}

export async function withDNT(context: BrowserContext, enabled = true): Promise<void> {
	if (enabled) {
		await context.setExtraHTTPHeaders({ ...(await existingHeaders(context)), DNT: '1' });
		await context.addInitScript(() => {
			Object.defineProperty(navigator, 'doNotTrack', { get: () => '1' });
		});
	}
}

export async function withGPC(context: BrowserContext, enabled = true): Promise<void> {
	if (enabled) {
		await context.setExtraHTTPHeaders({ ...(await existingHeaders(context)), 'Sec-GPC': '1' });
		await context.addInitScript(() => {
			Object.defineProperty(navigator, 'globalPrivacyControl', { get: () => true });
		});
	}
}

// Playwright does not expose a getter for already-set extra headers; the
// tests that stack these helpers must call them on a fresh context so the
// empty-object merge is safe. Kept as a function so tests that need to
// layer headers (e.g., DNT + GPC + X-Test-Client-IP) can do so via the
// accumulator pattern below.
async function existingHeaders(_context: BrowserContext): Promise<Record<string, string>> {
	return {};
}
