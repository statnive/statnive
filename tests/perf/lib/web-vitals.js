/**
 * Reusable Web Vitals collection helper for k6 browser tests.
 *
 * Injects PerformanceObserver scripts before navigation and harvests
 * TTFB, FCP, LCP, CLS, INP after the page stabilizes.
 *
 * Usage:
 *   import { injectObservers, harvestVitals } from './lib/web-vitals.js';
 *   await injectObservers(page);
 *   await page.goto(url, { waitUntil: 'networkidle' });
 *   await page.waitForTimeout(3000);
 *   const vitals = await harvestVitals(page);
 */

/**
 * Inject PerformanceObserver scripts into the page context.
 *
 * k6 browser module does not support addInitScript(), so we inject
 * observers via page.evaluate() AFTER navigation. We use `buffered: true`
 * to capture entries that occurred before observer registration.
 *
 * Call this AFTER page.goto() completes.
 *
 * @param {import('k6/browser').Page} page
 */
export async function injectObservers(page) {
	await page.evaluate(() => {
		window.__webVitals = { lcp: 0, cls: 0, inp: 0 };

		// LCP — track the last largest-contentful-paint entry.
		// buffered: true gives us entries from before this observer was created.
		try {
			new PerformanceObserver((list) => {
				const entries = list.getEntries();
				if (entries.length > 0) {
					window.__webVitals.lcp = entries[entries.length - 1].startTime;
				}
			}).observe({ type: 'largest-contentful-paint', buffered: true });
		} catch (e) {
			// LCP not supported in this browser context.
		}

		// CLS — sum layout shift values (exclude those with recent input).
		try {
			new PerformanceObserver((list) => {
				for (const entry of list.getEntries()) {
					if (!entry.hadRecentInput) {
						window.__webVitals.cls += entry.value;
					}
				}
			}).observe({ type: 'layout-shift', buffered: true });
		} catch (e) {
			// CLS not supported.
		}

		// INP — track the worst interaction duration.
		try {
			new PerformanceObserver((list) => {
				for (const entry of list.getEntries()) {
					if (entry.duration > window.__webVitals.inp) {
						window.__webVitals.inp = entry.duration;
					}
				}
			}).observe({ type: 'event', buffered: true, durationThreshold: 16 });
		} catch (e) {
			// INP/event timing not supported.
		}
	});
}

/**
 * Harvest all Web Vitals from the page context.
 * Call after page load + stabilization wait (3s recommended).
 *
 * @param {import('k6/browser').Page} page
 * @returns {Promise<{ttfb: number, fcp: number, lcp: number, cls: number, inp: number}>}
 */
export async function harvestVitals(page) {
	return page.evaluate(() => {
		const nav = performance.getEntriesByType('navigation')[0];
		const paintEntries = performance.getEntriesByType('paint');
		const fcp = paintEntries.find((e) => e.name === 'first-contentful-paint');

		return {
			ttfb: nav ? nav.responseStart : 0,
			fcp: fcp ? fcp.startTime : 0,
			lcp: window.__webVitals ? window.__webVitals.lcp : 0,
			cls: window.__webVitals ? window.__webVitals.cls : 0,
			inp: window.__webVitals ? window.__webVitals.inp : 0,
		};
	});
}

/**
 * Count network requests and compute transfer sizes from intercepted responses.
 *
 * @param {Array<{url: string, size: number}>} responses — collected via page.on('response')
 * @returns {{totalRequests: number, totalTransferKB: number, trackerScriptKB: number}}
 */
export function computeNetworkStats(responses) {
	const TRACKER_PATTERNS = [
		'statnive', 'wp-statistics', 'tracker.js',
		'koko-analytics', 'burst', 'wp-slimstat',
		'slimstat', 'iawp', 'jetpack', 'gtag',
		'google-analytics', 'analytics.js',
	];

	let totalBytes = 0;
	let trackerBytes = 0;

	for (const r of responses) {
		totalBytes += r.size || 0;
		const isTracker = TRACKER_PATTERNS.some((p) => r.url.includes(p));
		if (isTracker) {
			trackerBytes += r.size || 0;
		}
	}

	return {
		totalRequests: responses.length,
		totalTransferKB: Math.round(totalBytes / 1024 * 10) / 10,
		trackerScriptKB: Math.round(trackerBytes / 1024 * 10) / 10,
	};
}

/**
 * Perform a user interaction (scroll + click) to generate INP data.
 * Call after page load but before harvesting vitals.
 *
 * @param {import('k6/browser').Page} page
 */
export async function simulateInteraction(page) {
	try {
		// Scroll down to trigger layout shifts and load lazy content.
		await page.evaluate(() => window.scrollBy(0, 300));
		await page.waitForTimeout(500);

		// Click the first visible link to generate an interaction event.
		const link = await page.$('a[href]:not([href^="#"]):not([href^="javascript"])');
		if (link) {
			// Use dispatchEvent instead of click() to avoid navigation.
			await page.evaluate((el) => {
				el.dispatchEvent(new PointerEvent('pointerdown', { bubbles: true }));
				el.dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));
				el.dispatchEvent(new PointerEvent('pointerup', { bubbles: true }));
				el.dispatchEvent(new MouseEvent('mouseup', { bubbles: true }));
				el.dispatchEvent(new MouseEvent('click', { bubbles: true }));
			}, link);
		}

		await page.waitForTimeout(500);
	} catch (e) {
		// Interaction failed — INP will be 0, which is fine.
	}
}
