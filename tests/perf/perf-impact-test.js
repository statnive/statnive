/**
 * Performance impact test — measures Core Web Vitals overhead per plugin config.
 *
 * Runs real Chromium browser visits collecting TTFB, FCP, LCP, CLS, INP,
 * plus protocol-level HTTP VUs to create server-side load pressure.
 *
 * Designed to be called by perf-impact-runner.sh which toggles plugins
 * via WP-CLI between runs. Each run tests ONE configuration.
 *
 * Usage:
 *   K6_BROWSER_ENABLED=true k6 run perf-impact-test.js \
 *     -e BASE_URL=http://localhost:10013 \
 *     -e CONFIG_LABEL=baseline \
 *     -e LOAD_TIER=medium
 */

import http from 'k6/http';
import { sleep } from 'k6';
import { browser } from 'k6/browser';
import { Trend, Counter } from 'k6/metrics';
import { BASE_URL, ADMIN_USER, ADMIN_PASS } from './lib/config.js';
import { injectObservers, harvestVitals, simulateInteraction, computeNetworkStats } from './lib/web-vitals.js';
import { generatePerfSummaryOutput } from './lib/perf-reporter.js';

// ---------------------------------------------------------------------------
// Config
// ---------------------------------------------------------------------------
const CONFIG_LABEL = __ENV.CONFIG_LABEL || 'unknown';
const LOAD_TIER = __ENV.LOAD_TIER || 'medium';
const ITERATIONS_PER_VU = parseInt(__ENV.ITERATIONS_PER_VU || '3', 10);

// Load tier presets.
const TIERS = {
	light:  { browserVUs: 3,  protocolVUs: 10, duration: '2m', browserIterations: 2 },
	medium: { browserVUs: 5,  protocolVUs: 25, duration: '3m', browserIterations: 3 },
	heavy:  { browserVUs: 10, protocolVUs: 50, duration: '5m', browserIterations: 4 },
};
const tier = TIERS[LOAD_TIER] || TIERS.medium;

// ---------------------------------------------------------------------------
// Custom metrics
// ---------------------------------------------------------------------------
const webVitalTTFB = new Trend('web_vital_ttfb', true);
const webVitalFCP = new Trend('web_vital_fcp', true);
const webVitalLCP = new Trend('web_vital_lcp', true);
const webVitalCLS = new Trend('web_vital_cls', false);
const webVitalINP = new Trend('web_vital_inp', true);
const serverResponseTime = new Trend('server_response_time', true);
const pageTotalRequests = new Trend('page_total_requests', false);
const trackerScriptSize = new Trend('tracker_script_size', false);
const browserPageLoads = new Counter('browser_page_loads');

// ---------------------------------------------------------------------------
// Visitor profiles (same across all configs for apples-to-apples)
// ---------------------------------------------------------------------------
const VISITORS = [
	{ ua: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36', vw: 1920, vh: 1080, locale: 'en-US', tz: 'America/New_York' },
	{ ua: 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Mobile/15E148 Safari/604.1', vw: 390, vh: 844, locale: 'de-DE', tz: 'Europe/Berlin' },
	{ ua: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:125.0) Gecko/20100101 Firefox/125.0', vw: 1440, vh: 900, locale: 'ja-JP', tz: 'Asia/Tokyo' },
	{ ua: 'Mozilla/5.0 (iPad; CPU OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Mobile/15E148 Safari/604.1', vw: 1024, vh: 1366, locale: 'fr-FR', tz: 'Europe/Paris' },
	{ ua: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36', vw: 1440, vh: 900, locale: 'en-GB', tz: 'Europe/London' },
	{ ua: 'Mozilla/5.0 (Linux; Android 14; SM-S918B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.6367.82 Mobile Safari/537.36', vw: 360, vh: 800, locale: 'es-ES', tz: 'Europe/Madrid' },
	{ ua: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36 Edg/124.0.0.0', vw: 1920, vh: 1200, locale: 'pt-BR', tz: 'America/Sao_Paulo' },
	{ ua: 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.3 Mobile/15E148 Safari/604.1', vw: 375, vh: 812, locale: 'zh-CN', tz: 'Asia/Shanghai' },
	{ ua: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Safari/605.1.15', vw: 1680, vh: 1050, locale: 'ko-KR', tz: 'Asia/Seoul' },
	{ ua: 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36', vw: 1920, vh: 1080, locale: 'en-US', tz: 'America/Chicago' },
];

// ---------------------------------------------------------------------------
// k6 options — dual scenario: browser vitals + protocol load
// ---------------------------------------------------------------------------
export const options = {
	scenarios: {
		browserVitals: {
			executor: 'per-vu-iterations',
			vus: tier.browserVUs,
			iterations: tier.browserIterations,
			maxDuration: '10m',
			exec: 'browserTest',
			options: { browser: { type: 'chromium' } },
		},
		protocolLoad: {
			executor: 'ramping-vus',
			exec: 'protocolTest',
			startVUs: 0,
			stages: [
				{ duration: rampDuration(0.15), target: tier.protocolVUs },
				{ duration: rampDuration(0.70), target: tier.protocolVUs },
				{ duration: rampDuration(0.15), target: 0 },
			],
		},
	},
	thresholds: {
		web_vital_ttfb: ['p(95)<3000'],
		web_vital_lcp: ['p(95)<5000'],
		browser_page_loads: ['count>0'],
	},
};

// ---------------------------------------------------------------------------
// Setup: discover pages
// ---------------------------------------------------------------------------
export function setup() {
	// Discover site pages via ground-truth API.
	const loginRes = http.post(`${BASE_URL}/wp-login.php`, {
		log: ADMIN_USER,
		pwd: ADMIN_PASS,
		'wp-submit': 'Log In',
		redirect_to: `${BASE_URL}/wp-admin/`,
		testcookie: '1',
	}, {
		redirects: 0,
		headers: {
			'Content-Type': 'application/x-www-form-urlencoded',
			Cookie: 'wordpress_test_cookie=WP%20Cookie%20check',
		},
	});

	const setCookies = loginRes.headers['Set-Cookie'];
	const cookieArray = Array.isArray(setCookies) ? setCookies : (setCookies ? [setCookies] : []);
	const cookies = cookieArray.map((c) => c.split(';')[0]).filter((c) => c.startsWith('wordpress_')).join('; ');

	const nonce = http.get(`${BASE_URL}/wp-admin/admin-ajax.php?action=rest-nonce`, {
		headers: { Cookie: cookies },
	}).body.trim();

	const authHeaders = {};
	if (nonce && nonce !== '0') authHeaders['X-WP-Nonce'] = nonce;
	if (cookies) authHeaders['Cookie'] = cookies;

	let testPages = [
		BASE_URL + '/',
		BASE_URL + '/sample-page/',
		BASE_URL + '/hello-world/',
		BASE_URL + '/shop/',
	];

	const pagesRes = http.get(`${BASE_URL}/wp-json/ground-truth/v1/site-pages`, {
		headers: authHeaders,
	});

	if (pagesRes.status === 200) {
		const sitePages = JSON.parse(pagesRes.body);
		const home = sitePages.find((p) => p.type === 'home')?.url || testPages[0];
		const page = sitePages.filter((p) => p.type === 'page')[0]?.url || testPages[1];
		const post = sitePages.filter((p) => p.type === 'post')[0]?.url || testPages[2];
		const product = sitePages.filter((p) => p.type === 'product')[0]?.url || testPages[3];
		testPages = [home, page, post, product];
	}

	console.log(`\n  Config: ${CONFIG_LABEL} | Tier: ${LOAD_TIER} | Browser VUs: ${tier.browserVUs} | Protocol VUs: ${tier.protocolVUs}`);
	console.log(`  Pages: ${testPages.join(', ')}\n`);

	return { testPages };
}

// ---------------------------------------------------------------------------
// Browser scenario: collect Web Vitals
// ---------------------------------------------------------------------------
export async function browserTest(data) {
	const { testPages } = data;
	const vuId = __VU - 1;
	const visitor = VISITORS[vuId % VISITORS.length];

	const context = await browser.newContext({
		userAgent: visitor.ua,
		viewport: { width: visitor.vw, height: visitor.vh },
		locale: visitor.locale,
		timezoneId: visitor.tz,
	});

	const page = await context.newPage();

	// Collect response data for network stats.
	const responses = [];
	page.on('response', (res) => {
		try {
			const headers = res.headers();
			const size = parseInt(headers['content-length'] || '0', 10);
			responses.push({ url: res.url(), size });
		} catch (e) {
			// Ignore response errors.
		}
	});

	try {
		for (const url of testPages) {
			// Clear response buffer for this page.
			responses.length = 0;

			await page.goto(url, { waitUntil: 'networkidle', timeout: 30000 });

			// Inject observers AFTER page load (uses buffered: true to catch past entries).
			await injectObservers(page);

			// Stabilization wait for LCP.
			await page.waitForTimeout(3000);

			// Simulate user interaction for INP.
			await simulateInteraction(page);

			// Harvest vitals.
			const vitals = await harvestVitals(page);

			// Record metrics.
			if (vitals.ttfb > 0) webVitalTTFB.add(vitals.ttfb);
			if (vitals.fcp > 0) webVitalFCP.add(vitals.fcp);
			if (vitals.lcp > 0) webVitalLCP.add(vitals.lcp);
			webVitalCLS.add(vitals.cls);
			webVitalINP.add(vitals.inp);

			// Network stats.
			const netStats = computeNetworkStats(responses);
			pageTotalRequests.add(netStats.totalRequests);
			trackerScriptSize.add(netStats.trackerScriptKB);

			browserPageLoads.add(1);

			// Think time between pages.
			await page.waitForTimeout(1000 + Math.floor(Math.random() * 2000));
		}
	} catch (err) {
		console.error(`  Browser VU ${__VU} error: ${err.message}`);
	} finally {
		await page.close();
		await context.close();
	}
}

// ---------------------------------------------------------------------------
// Protocol scenario: HTTP load to stress the server
// ---------------------------------------------------------------------------
export function protocolTest(data) {
	const { testPages } = data;

	for (const url of testPages) {
		const res = http.get(url, { tags: { type: 'protocol' } });
		serverResponseTime.add(res.timings.duration);
		sleep(0.5 + Math.random());
	}
}

// ---------------------------------------------------------------------------
// Summary
// ---------------------------------------------------------------------------
export function handleSummary(data) {
	return generatePerfSummaryOutput(data, CONFIG_LABEL);
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/** Compute a stage duration as fraction of total tier duration. */
function rampDuration(fraction) {
	const totalSeconds = parseDuration(TIERS[LOAD_TIER]?.duration || '3m');
	const stageSeconds = Math.max(10, Math.round(totalSeconds * fraction));
	return `${stageSeconds}s`;
}

/** Parse a k6 duration string to seconds. */
function parseDuration(d) {
	const match = d.match(/^(\d+)(s|m|h)$/);
	if (!match) return 180;
	const val = parseInt(match[1], 10);
	const unit = match[2];
	if (unit === 'h') return val * 3600;
	if (unit === 'm') return val * 60;
	return val;
}
