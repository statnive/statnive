/**
 * Real browser simulation using k6 browser module.
 *
 * Hybrid test: protocol-level bulk traffic + real browser journeys.
 * Browser VUs create BrowserContexts with realistic fingerprints,
 * navigate real pages, and intercept tracking payloads.
 *
 * Requires k6 with browser module (k6 v0.56+).
 *
 * Usage:
 *   k6 run tests/perf/browser-journeys.js \
 *     -e BASE_URL=http://localhost:10013 \
 *     -e ADMIN_USER=root -e ADMIN_PASS=q1w2e3
 */

import http from 'k6/http';
import { check, sleep } from 'k6';
import { browser } from 'k6/browser';
import { Counter, Trend } from 'k6/metrics';
import {
	BASE_URL, HMAC_SECRET, PAGES, ADMIN_USER, ADMIN_PASS,
} from './lib/config.js';
import { computeSignature } from './lib/hmac.js';
import { getProfile, getRandomReferrer } from './lib/profiles.js';
import { authenticate } from './lib/wp-auth.js';
import { recordHit } from './lib/ground-truth.js';

const browserPageLoads = new Counter('browser_page_loads');
const trackerPayloadsCaptured = new Counter('tracker_payloads_captured');
const pageLoadTime = new Trend('browser_page_load_ms');

export const options = {
	scenarios: {
		// Protocol-level bulk traffic (90% of load).
		protocolTraffic: {
			executor: 'ramping-vus',
			startVUs: 0,
			stages: [
				{ duration: '30s', target: 10 },
				{ duration: '4m', target: 10 },
				{ duration: '30s', target: 0 },
			],
			exec: 'protocolHit',
		},
		// Real browser journeys (10% of load).
		browserJourneys: {
			executor: 'constant-vus',
			vus: 3,
			duration: '5m',
			exec: 'browserJourney',
			options: {
				browser: {
					type: 'chromium',
				},
			},
		},
	},
	thresholds: {
		browser_page_loads: ['count>10'],
		http_req_duration: ['p(95)<1000'],
	},
};

export function setup() {
	const auth = authenticate(ADMIN_USER, ADMIN_PASS, BASE_URL);
	return { headers: auth.headers, success: auth.success };
}

// ---------------------------------------------------------------------------
// Protocol-level hits (fast, high volume)
// ---------------------------------------------------------------------------
export function protocolHit(setupData) {
	const profile = getProfile(__VU);
	const page = PAGES[Math.floor(Math.random() * PAGES.length)];
	const referrer = getRandomReferrer();
	const signature = computeSignature(HMAC_SECRET, page.type, page.id);

	http.post(
		`${BASE_URL}/wp-json/statnive/v1/hit`,
		JSON.stringify({
			resource_type: page.type,
			resource_id: page.id,
			referrer: referrer.url,
			screen_width: profile.viewport_w,
			screen_height: profile.viewport_h,
			language: profile.locale,
			timezone: profile.timezone,
			signature,
			page_query: '',
		}),
		{
			headers: {
				'Content-Type': 'text/plain',
				'User-Agent': profile.user_agent,
			},
		}
	);

	sleep(Math.random() * 2 + 1);
}

// ---------------------------------------------------------------------------
// Real browser journeys
// ---------------------------------------------------------------------------
const JOURNEY_TYPES = ['anonymous_browse', 'admin_login', 'utm_landing', 'deep_browse'];

export async function browserJourney(setupData) {
	const profile = getProfile(__VU);
	const journeyType = JOURNEY_TYPES[__ITER % JOURNEY_TYPES.length];

	const context = await browser.newContext({
		userAgent: profile.user_agent,
		viewport: { width: profile.viewport_w, height: profile.viewport_h },
		locale: profile.locale,
		timezoneId: profile.timezone,
	});

	const page = await context.newPage();

	// Capture tracker payloads.
	const capturedPayloads = [];
	page.on('request', (req) => {
		const url = req.url();
		if (url.includes('/statnive/v1/hit') || url.includes('statnive.js')) {
			capturedPayloads.push({
				url,
				method: req.method(),
				time: Date.now(),
			});
			trackerPayloadsCaptured.add(1);
		}
	});

	try {
		switch (journeyType) {
			case 'anonymous_browse':
				await anonymousBrowse(page, profile);
				break;
			case 'admin_login':
				await adminLogin(page, profile);
				break;
			case 'utm_landing':
				await utmLanding(page, profile);
				break;
			case 'deep_browse':
				await deepBrowse(page, profile);
				break;
		}

		// Log captured payloads.
		if (capturedPayloads.length > 0) {
			console.log(
				`  [${journeyType}] Captured ${capturedPayloads.length} tracker payload(s)`
			);
		}
	} catch (err) {
		console.error(`  [${journeyType}] Error: ${err.message}`);
	} finally {
		await page.close();
		await context.close();
	}
}

// ---------------------------------------------------------------------------
// Journey implementations
// ---------------------------------------------------------------------------

async function anonymousBrowse(page, profile) {
	// Homepage.
	const start = Date.now();
	await page.goto(BASE_URL, { waitUntil: 'networkidle' });
	pageLoadTime.add(Date.now() - start);
	browserPageLoads.add(1);

	await sleep(Math.random() * 3 + 1);

	// Click a link to navigate deeper.
	const links = page.locator('a[href*="' + BASE_URL + '"]');
	const count = await links.count();
	if (count > 0) {
		const randomLink = links.nth(Math.floor(Math.random() * Math.min(count, 10)));
		await randomLink.click();
		await page.waitForLoadState('networkidle');
		browserPageLoads.add(1);
	}

	await sleep(Math.random() * 2 + 1);
}

async function adminLogin(page, profile) {
	// Navigate to login page.
	await page.goto(`${BASE_URL}/wp-login.php`, { waitUntil: 'networkidle' });
	browserPageLoads.add(1);

	// Fill login form.
	await page.locator('#user_login').fill(ADMIN_USER);
	await page.locator('#user_pass').fill(ADMIN_PASS);
	await page.locator('#wp-submit').click();
	await page.waitForLoadState('networkidle');
	browserPageLoads.add(1);

	await sleep(Math.random() * 2 + 1);

	// Navigate to Statnive dashboard.
	await page.goto(`${BASE_URL}/wp-admin/admin.php?page=statnive`, {
		waitUntil: 'networkidle',
	});
	browserPageLoads.add(1);

	// Verify dashboard loaded.
	check(page, {
		'statnive dashboard loaded': (p) => p.url().includes('page=statnive'),
	});

	await sleep(Math.random() * 3 + 2);
}

async function utmLanding(page, profile) {
	const utmParams = '?utm_source=google&utm_medium=cpc&utm_campaign=spring_sale';

	const start = Date.now();
	await page.goto(`${BASE_URL}/${utmParams}`, { waitUntil: 'networkidle' });
	pageLoadTime.add(Date.now() - start);
	browserPageLoads.add(1);

	await sleep(Math.random() * 4 + 2);

	// Browse to another page (internal navigation).
	const links = page.locator('a[href*="' + BASE_URL + '"]:not([href*="wp-admin"])');
	const count = await links.count();
	if (count > 0) {
		await links.nth(0).click();
		await page.waitForLoadState('networkidle');
		browserPageLoads.add(1);
	}

	await sleep(Math.random() * 2 + 1);
}

async function deepBrowse(page, profile) {
	// Visit 3-5 pages in sequence.
	const numPages = Math.floor(Math.random() * 3) + 3;

	await page.goto(BASE_URL, { waitUntil: 'networkidle' });
	browserPageLoads.add(1);

	for (let i = 0; i < numPages - 1; i++) {
		await sleep(Math.random() * 3 + 1);

		const links = page.locator('a[href*="' + BASE_URL + '"]:not([href*="wp-admin"]):not([href*="wp-login"])');
		const count = await links.count();
		if (count > 0) {
			const idx = Math.floor(Math.random() * Math.min(count, 10));
			await links.nth(idx).click();
			await page.waitForLoadState('networkidle');
			browserPageLoads.add(1);
		} else {
			break;
		}
	}
}
