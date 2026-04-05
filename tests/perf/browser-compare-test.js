/**
 * Production-realistic browser comparison test.
 *
 * Sends REAL Chromium browser visits to a WordPress site so that
 * every installed analytics plugin's JS tracker and PHP hooks fire.
 * Then queries all plugin databases and prints a comparison report.
 *
 * Reusable: auto-discovers pages via /ground-truth/v1/site-pages.
 *
 * Usage:
 *   K6_BROWSER_ENABLED=true k6 run browser-compare-test.js \
 *     -e BASE_URL=http://localhost:10013 \
 *     -e ADMIN_USER=root -e ADMIN_PASS=q1w2e3
 */

import http from 'k6/http';
import { check, sleep } from 'k6';
import { browser } from 'k6/browser';
import { Counter } from 'k6/metrics';

// ---------------------------------------------------------------------------
// Config (from env vars)
// ---------------------------------------------------------------------------
const BASE_URL = __ENV.BASE_URL || 'http://localhost:10013';
const ADMIN_USER = __ENV.ADMIN_USER || 'root';
const ADMIN_PASS = __ENV.ADMIN_PASS || 'q1w2e3';

const pageVisits = new Counter('page_visits');
const trackersDetected = new Counter('trackers_detected');

// ---------------------------------------------------------------------------
// k6 options — sequential browser visits (1 VU at a time)
// ---------------------------------------------------------------------------
export const options = {
	scenarios: {
		visitors: {
			executor: 'per-vu-iterations',
			vus: 1,
			iterations: 1,
			maxDuration: '10m',
			options: { browser: { type: 'chromium' } },
		},
	},
};

// ---------------------------------------------------------------------------
// Visitor profiles — 10 distinct visitors with realistic fingerprints
// ---------------------------------------------------------------------------
const VISITORS = [
	{ id: 'v01', ua: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36', vw: 1920, vh: 1080, locale: 'en-US', tz: 'America/New_York', device: 'Desktop Chrome' },
	{ id: 'v02', ua: 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Mobile/15E148 Safari/604.1', vw: 390, vh: 844, locale: 'de-DE', tz: 'Europe/Berlin', device: 'Mobile Safari' },
	{ id: 'v03', ua: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:125.0) Gecko/20100101 Firefox/125.0', vw: 1440, vh: 900, locale: 'ja-JP', tz: 'Asia/Tokyo', device: 'Desktop Firefox' },
	{ id: 'v04', ua: 'Mozilla/5.0 (iPad; CPU OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Mobile/15E148 Safari/604.1', vw: 1024, vh: 1366, locale: 'fr-FR', tz: 'Europe/Paris', device: 'Tablet iPad' },
	{ id: 'v05', ua: 'Mozilla/5.0 (Linux; Android 14; SM-S918B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.6367.82 Mobile Safari/537.36', vw: 360, vh: 800, locale: 'es-ES', tz: 'Europe/Madrid', device: 'Mobile Android' },
	{ id: 'v06', ua: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36', vw: 1440, vh: 900, locale: 'en-GB', tz: 'Europe/London', device: 'Desktop Chrome' },
	{ id: 'v07', ua: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36 Edg/124.0.0.0', vw: 1920, vh: 1200, locale: 'pt-BR', tz: 'America/Sao_Paulo', device: 'Desktop Edge' },
	{ id: 'v08', ua: 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.3 Mobile/15E148 Safari/604.1', vw: 375, vh: 812, locale: 'zh-CN', tz: 'Asia/Shanghai', device: 'Mobile iPhone' },
	{ id: 'v09', ua: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Safari/605.1.15', vw: 1680, vh: 1050, locale: 'ko-KR', tz: 'Asia/Seoul', device: 'Desktop Safari' },
	{ id: 'v10', ua: 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36', vw: 1920, vh: 1080, locale: 'en-US', tz: 'America/Chicago', device: 'Desktop Linux' },
];

// ---------------------------------------------------------------------------
// Known tracker URL patterns to detect per plugin
// ---------------------------------------------------------------------------
const TRACKER_PATTERNS = {
	'statnive':    ['/statnive/v1/hit', 'statnive.js', 'statnive.min.js'],
	'wp-statistics': ['tracker.js', 'wp-statistics', 'background-process-tracker'],
	'koko-analytics': ['koko-analytics', 'koko_analytics'],
	'burst-statistics': ['burst', '/burst/v1/'],
	'wp-slimstat': ['wp-slimstat', 'slimstat'],
	'independent-analytics': ['iawp'],
};

function detectPlugin(url) {
	for (const [plugin, patterns] of Object.entries(TRACKER_PATTERNS)) {
		if (patterns.some(p => url.includes(p))) return plugin;
	}
	return null;
}

// ---------------------------------------------------------------------------
// Setup: authenticate + discover site pages
// ---------------------------------------------------------------------------
export function setup() {
	// Login to get cookies + nonce.
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
	const cookies = cookieArray.map(c => c.split(';')[0]).filter(c => c.startsWith('wordpress_')).join('; ');

	const nonce = http.get(`${BASE_URL}/wp-admin/admin-ajax.php?action=rest-nonce`, {
		headers: { Cookie: cookies },
	}).body.trim();

	const authHeaders = {};
	if (nonce && nonce !== '0') authHeaders['X-WP-Nonce'] = nonce;
	if (cookies) authHeaders['Cookie'] = cookies;

	// Discover site pages.
	const pagesRes = http.get(`${BASE_URL}/wp-json/ground-truth/v1/site-pages`, {
		headers: authHeaders,
	});

	let sitePages = [];
	if (pagesRes.status === 200) {
		sitePages = JSON.parse(pagesRes.body);
		console.log(`Discovered ${sitePages.length} pages on the site.`);
	} else {
		console.warn('Could not discover site pages — using defaults.');
		sitePages = [
			{ url: BASE_URL + '/', type: 'home', title: 'Homepage' },
			{ url: BASE_URL + '/sample-page/', type: 'page', title: 'Sample Page' },
			{ url: BASE_URL + '/hello-world/', type: 'post', title: 'Hello World' },
			{ url: BASE_URL + '/shop/', type: 'page', title: 'Shop' },
		];
	}

	// Get baseline counts BEFORE the test.
	const baselineRes = http.get(
		`${BASE_URL}/wp-json/ground-truth/v1/compare-db?from=${todayStr()}&to=${todayStr()}`,
		{ headers: authHeaders }
	);
	let baseline = {};
	if (baselineRes.status === 200) {
		baseline = JSON.parse(baselineRes.body);
		console.log('Baseline counts captured (pre-test).');
	}

	// Build the visit plan from discovered pages.
	const home = sitePages.find(p => p.type === 'home')?.url || BASE_URL + '/';
	const pages = sitePages.filter(p => p.type === 'page');
	const posts = sitePages.filter(p => p.type === 'post');
	const products = sitePages.filter(p => p.type === 'product');

	const samplePage = pages.find(p => p.title === 'Sample Page')?.url || pages[0]?.url || home;
	const shop = pages.find(p => p.title === 'Shop')?.url || home;
	const cart = pages.find(p => p.title === 'Cart')?.url || home;
	const post1 = posts[0]?.url || home;
	const product1 = products[0]?.url || shop;
	const product2 = products[1]?.url || shop;

	// The visit plan: 10 visitors, each with a defined journey.
	const visitPlan = [
		{ visitor: 0, pages: [home, samplePage, post1] },
		{ visitor: 1, pages: [home, product1, cart] },
		{ visitor: 2, pages: [post1, samplePage, home] },
		{ visitor: 3, pages: [home, shop] },
		{ visitor: 4, pages: [home, post1] },
		{ visitor: 5, pages: [product1, product2, shop], referrer: 'https://www.google.com/search?q=test' },
		{ visitor: 6, pages: [home, samplePage], referrer: 'https://www.facebook.com/example' },
		{ visitor: 7, pages: [home] },  // Bounce visit.
		{ visitor: 8, pages: [home, shop, product1, post1, samplePage] },
		{ visitor: 9, pages: [home + '?utm_source=newsletter&utm_medium=email&utm_campaign=test'] },
	];

	const totalPageViews = visitPlan.reduce((sum, v) => sum + v.pages.length, 0);
	console.log(`\nVisit plan: ${visitPlan.length} visitors, ${totalPageViews} total page views.`);
	console.log('');

	return {
		authHeaders,
		baseline,
		visitPlan,
		totalPageViews,
		startTime: Date.now(),
	};
}

// ---------------------------------------------------------------------------
// Main test: execute all visitors sequentially
// ---------------------------------------------------------------------------
export default async function (setupData) {
	const { visitPlan, authHeaders } = setupData;

	console.log('=== Starting Production-Realistic Browser Visits ===\n');

	const pluginHits = {};
	let totalVisits = 0;

	for (let i = 0; i < visitPlan.length; i++) {
		const plan = visitPlan[i];
		const visitor = VISITORS[plan.visitor];

		console.log(`Visitor ${i + 1}/${visitPlan.length}: ${visitor.device} (${visitor.locale}) — ${plan.pages.length} page(s)`);

		// Create a fresh browser context for this visitor.
		const context = await browser.newContext({
			userAgent: visitor.ua,
			viewport: { width: visitor.vw, height: visitor.vh },
			locale: visitor.locale,
			timezoneId: visitor.tz,
		});

		const page = await context.newPage();

		// Track which plugin trackers fire.
		const visitorPluginHits = {};
		page.on('request', (req) => {
			const url = req.url();
			const plugin = detectPlugin(url);
			if (plugin) {
				visitorPluginHits[plugin] = (visitorPluginHits[plugin] || 0) + 1;
				trackersDetected.add(1);
			}
		});

		try {
			for (let j = 0; j < plan.pages.length; j++) {
				let url = plan.pages[j];

				// Set referrer for first page if specified.
				const gotoOpts = { waitUntil: 'networkidle', timeout: 30000 };
				if (j === 0 && plan.referrer) {
					// Navigate to referrer first to set document.referrer.
					// k6 browser doesn't support referer option, so we set extraHTTPHeaders.
					await page.setExtraHTTPHeaders({ 'Referer': plan.referrer });
				}

				await page.goto(url, gotoOpts);
				pageVisits.add(1);
				totalVisits++;

				// Wait for all trackers to fire (give them 2s after networkidle).
				await page.waitForTimeout(2000);

				// Human-like think time between pages.
				if (j < plan.pages.length - 1) {
					const thinkTime = 2000 + Math.floor(Math.random() * 3000);
					await page.waitForTimeout(thinkTime);
				}
			}

			// Log which plugins fired for this visitor.
			const pluginList = Object.entries(visitorPluginHits)
				.map(([p, c]) => `${p}(${c})`)
				.join(', ');
			console.log(`  Trackers fired: ${pluginList || 'none detected'}`);

			// Accumulate.
			for (const [p, c] of Object.entries(visitorPluginHits)) {
				pluginHits[p] = (pluginHits[p] || 0) + c;
			}
		} catch (err) {
			console.error(`  Error: ${err.message}`);
		} finally {
			await page.close();
			await context.close();
		}

		// Brief pause between visitors.
		sleep(1);
	}

	console.log(`\n=== All ${totalVisits} page visits complete ===\n`);
	console.log('Tracker detection summary (client-side):');
	for (const [p, c] of Object.entries(pluginHits).sort((a, b) => b[1] - a[1])) {
		console.log(`  ${p}: ${c} tracker requests`);
	}

	// Wait for async processing (some plugins batch-process).
	console.log('\nWaiting 10s for async plugin processing...');
	sleep(10);

	// Trigger WP-Cron for plugins that defer processing.
	http.get(`${BASE_URL}/wp-cron.php?doing_wp_cron=1`);
	sleep(3);

	// Query the comparison endpoint.
	console.log('\nQuerying all plugin databases...\n');
	const compareRes = http.get(
		`${BASE_URL}/wp-json/ground-truth/v1/compare-db?from=${todayStr()}&to=${todayStr()}`,
		{ headers: setupData.authHeaders }
	);

	if (compareRes.status === 200) {
		const results = JSON.parse(compareRes.body);
		const baseline = setupData.baseline;

		// Calculate delta (new views from THIS test only).
		const delta = {};
		for (const [plugin, data] of Object.entries(results)) {
			const base = baseline[plugin] || { views: 0, sessions: 0, visitors: 0 };
			delta[plugin] = {
				views: data.views - base.views,
				sessions: data.sessions - base.sessions,
				visitors: data.visitors - base.visitors,
			};
		}

		// Print report.
		const expected = setupData.totalPageViews;
		console.log('╔══════════════════════════════════════════════════════════════════════╗');
		console.log('║          CROSS-PLUGIN COMPARISON (This Test Only)                   ║');
		console.log('╠══════════════════════════════════════════════════════════════════════╣');
		console.log(`║  Expected: ${expected} page views from ${visitPlan.length} visitors                           ║`);
		console.log('╠═══════════════════════════╦═════════╦══════════╦══════════╦══════════╣');
		console.log('║ Plugin                    ║  Views  ║ Sessions ║ Visitors ║ Accuracy ║');
		console.log('╠═══════════════════════════╬═════════╬══════════╬══════════╬══════════╣');

		for (const [plugin, d] of Object.entries(delta)) {
			const acc = expected > 0 ? Math.min(100, (d.views / expected * 100)).toFixed(1) : '0.0';
			const name = plugin.padEnd(25);
			const views = String(d.views).padStart(7);
			const sessions = String(d.sessions).padStart(8);
			const visitors = String(d.visitors).padStart(8);
			const accuracy = (acc + '%').padStart(8);
			console.log(`║ ${name} ║ ${views} ║ ${sessions} ║ ${visitors} ║ ${accuracy} ║`);
		}

		console.log('╚═══════════════════════════╩═════════╩══════════╩══════════╩══════════╝');
		console.log('');

		// Validate.
		for (const [plugin, d] of Object.entries(delta)) {
			check(d, {
				[`${plugin} tracked views > 0`]: (d) => d.views > 0,
			});
		}
	} else {
		console.error(`compare-db failed: ${compareRes.status} ${compareRes.body}`);
	}
}

function todayStr() {
	return new Date().toISOString().slice(0, 10);
}

export function handleSummary(data) {
	const ts = Date.now();
	return {
		[`./results/browser-compare-${ts}.json`]: JSON.stringify(data, null, 2),
	};
}
