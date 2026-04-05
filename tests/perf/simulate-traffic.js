/**
 * Realistic traffic simulator for WordPress analytics testing.
 *
 * Generates diverse, realistic visitor traffic using profiles from CSV data.
 * Supports multiple modes: burst, continuous, and accuracy.
 * Records ground truth for every hit to enable cross-plugin validation.
 *
 * Usage:
 *   # Burst mode (default) — 3-minute ramp-up test
 *   k6 run tests/perf/simulate-traffic.js -e BASE_URL=http://localhost:10013
 *
 *   # Continuous mode — low-rate always-on traffic
 *   k6 run tests/perf/simulate-traffic.js -e MODE=continuous -e RATE=5
 *
 *   # With auth for ground truth + verification
 *   k6 run tests/perf/simulate-traffic.js \
 *     -e BASE_URL=http://localhost:10013 \
 *     -e ADMIN_USER=root -e ADMIN_PASS=q1w2e3 \
 *     -e HMAC_SECRET=your-secret
 */

import http from 'k6/http';
import { check, sleep } from 'k6';
import { Counter, Rate, Trend } from 'k6/metrics';
import {
	BASE_URL, HMAC_SECRET, MODE, RATE, PAGES,
	ADMIN_USER, ADMIN_PASS, TEST_RUN_ID,
} from './lib/config.js';
import { computeSignature } from './lib/hmac.js';
import { getProfile, getRandomProfile, getRandomReferrer, getRandomUTM, getRandomProduct } from './lib/profiles.js';
import { getWeightedUA, getBotUA } from './lib/user-agents.js';
import { authenticate } from './lib/wp-auth.js';
import { recordHit } from './lib/ground-truth.js';
import {
	browsingScenario, botScenario, shoppingScenario,
	continuousScenario, verifyScenario, mergeScenarios,
} from './lib/scenarios.js';

// ---------------------------------------------------------------------------
// Custom metrics
// ---------------------------------------------------------------------------
const hitsSent = new Counter('hits_sent');
const hitsOk = new Counter('hits_ok');
const eventLoss = new Rate('event_loss');
const groundTruthRecorded = new Counter('ground_truth_recorded');
const apiLatency = new Trend('api_endpoint_latency');

// ---------------------------------------------------------------------------
// Dynamic scenario selection based on MODE
// ---------------------------------------------------------------------------
function buildOptions() {
	switch (MODE) {
		case 'continuous':
			return {
				...mergeScenarios(
					continuousScenario('traffic', RATE),
					botScenario('bots', '24h', Math.max(1, Math.floor(RATE / 10))),
				),
				thresholds: {
					http_req_duration: ['p(95)<1000'],
					http_req_failed: ['rate<0.10'],
				},
			};

		case 'accuracy':
			// Deterministic mode — use data-accuracy.js instead.
			return {
				scenarios: {
					traffic: {
						executor: 'shared-iterations',
						vus: 1,
						iterations: 100,
						maxDuration: '5m',
						exec: 'browsing',
					},
					verify: {
						executor: 'shared-iterations',
						vus: 1,
						iterations: 1,
						startTime: '5m10s',
						exec: 'verify',
					},
				},
				thresholds: {
					event_loss: ['rate<=0.005'],
					http_req_duration: ['p(95)<500'],
				},
			};

		default: // 'burst'
			return {
				...mergeScenarios(
					browsingScenario('browse', '3m', 20),
					botScenario('bots', '3m', 3),
					shoppingScenario('shop', '3m', 5, 3),
					verifyScenario('verify', '3m30s'),
				),
				thresholds: {
					http_req_duration: ['p(95)<500'],
					http_req_failed: ['rate<0.05'],
					hits_sent: ['count>100'],
				},
			};
	}
}

export const options = buildOptions();

// ---------------------------------------------------------------------------
// Auth setup (runs once)
// ---------------------------------------------------------------------------
let authHeaders = {};

export function setup() {
	const auth = authenticate(ADMIN_USER, ADMIN_PASS, BASE_URL);
	if (auth.success) {
		console.log(`Authenticated as ${ADMIN_USER}. Ground truth recording enabled.`);
	} else {
		console.warn('Authentication failed. Ground truth recording will be skipped.');
	}

	return {
		headers: auth.headers,
		testRunId: TEST_RUN_ID,
		startTime: new Date().toISOString(),
	};
}

// ---------------------------------------------------------------------------
// Browsing scenario — human visitors
// ---------------------------------------------------------------------------
export function browsing(setupData) {
	const profile = getProfile(__VU);
	const page = PAGES[Math.floor(Math.random() * PAGES.length)];
	const referrer = getRandomReferrer();
	const utm = getRandomUTM(0.3);

	const signature = computeSignature(HMAC_SECRET, page.type, page.id);

	const payload = {
		resource_type: page.type,
		resource_id: page.id,
		referrer: referrer.url,
		screen_width: profile.viewport_w,
		screen_height: profile.viewport_h,
		language: profile.locale,
		timezone: profile.timezone,
		signature,
		page_query: '',
	};

	// Attach UTM if selected.
	if (utm) {
		payload.utm_source = utm.source;
		payload.utm_medium = utm.medium;
		payload.utm_campaign = utm.campaign;
	}

	const res = http.post(`${BASE_URL}/wp-json/statnive/v1/hit`, JSON.stringify(payload), {
		headers: {
			'Content-Type': 'text/plain',
			'User-Agent': profile.user_agent,
			'Accept-Language': profile.accept_language,
		},
	});

	hitsSent.add(1);
	const ok = check(res, { 'tracker returns 204': (r) => r.status === 204 });
	if (ok) {
		hitsOk.add(1);
		eventLoss.add(0);
	} else {
		eventLoss.add(1);
	}

	// Record ground truth.
	if (setupData.headers && Object.keys(setupData.headers).length > 0) {
		const gtOk = recordHit({
			profile_id: profile.id,
			resource_type: page.type,
			resource_id: page.id,
			referrer_url: referrer.url,
			expected_channel: referrer.expected_channel,
			utm_source: utm?.source || '',
			utm_medium: utm?.medium || '',
			utm_campaign: utm?.campaign || '',
			device_type: profile.device_type,
			is_bot: false,
			is_logged_in: profile.is_logged_in,
			user_agent: profile.user_agent,
		}, setupData.headers);
		if (gtOk) groundTruthRecorded.add(1);
	}

	// Human-like think time: 1-8 seconds.
	sleep(Math.random() * 7 + 1);
}

// ---------------------------------------------------------------------------
// Bot crawl scenario
// ---------------------------------------------------------------------------
export function botCrawl(setupData) {
	const page = PAGES[Math.floor(Math.random() * PAGES.length)];
	const botUA = getBotUA();
	const signature = computeSignature(HMAC_SECRET, page.type, page.id);

	const payload = JSON.stringify({
		resource_type: page.type,
		resource_id: page.id,
		referrer: '',
		screen_width: 1920,
		screen_height: 1080,
		language: 'en-US',
		timezone: 'America/New_York',
		signature,
		page_query: '',
	});

	const res = http.post(`${BASE_URL}/wp-json/statnive/v1/hit`, payload, {
		headers: {
			'Content-Type': 'text/plain',
			'User-Agent': botUA,
		},
	});

	hitsSent.add(1);
	if (res.status === 204) {
		hitsOk.add(1);
		eventLoss.add(0);
	} else {
		eventLoss.add(1);
	}

	// Record ground truth as bot.
	if (setupData.headers && Object.keys(setupData.headers).length > 0) {
		recordHit({
			profile_id: `bot-${__VU}`,
			resource_type: page.type,
			resource_id: page.id,
			referrer_url: '',
			expected_channel: 'Direct',
			device_type: 'desktop',
			is_bot: true,
			is_logged_in: false,
			user_agent: botUA,
		}, setupData.headers);
	}

	// Bots crawl fast: 0.1-0.5 second delay.
	sleep(Math.random() * 0.4 + 0.1);
}

// ---------------------------------------------------------------------------
// Shopping scenario — WooCommerce flows
// ---------------------------------------------------------------------------
export function shopping(setupData) {
	const profile = getProfile(__VU);
	const product = getRandomProduct();
	const signature = computeSignature(HMAC_SECRET, 'page', product.product_id);

	// Step 1: View product page.
	const viewPayload = JSON.stringify({
		resource_type: 'page',
		resource_id: product.product_id,
		referrer: 'https://www.google.com/search?q=' + encodeURIComponent(product.name),
		screen_width: profile.viewport_w,
		screen_height: profile.viewport_h,
		language: profile.locale,
		timezone: profile.timezone,
		signature,
		page_query: '',
	});

	http.post(`${BASE_URL}/wp-json/statnive/v1/hit`, viewPayload, {
		headers: { 'Content-Type': 'text/plain', 'User-Agent': profile.user_agent },
	});
	hitsSent.add(1);

	sleep(Math.random() * 3 + 2); // Browse product.

	// Step 2: Add to cart (simulate via WooCommerce REST if available).
	const cartRes = http.post(
		`${BASE_URL}/?wc-ajax=add_to_cart`,
		`product_id=${product.product_id}&quantity=1`,
		{
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded',
				'User-Agent': profile.user_agent,
			},
		}
	);

	sleep(Math.random() * 2 + 1);

	// Step 3: View checkout page.
	const checkoutSig = computeSignature(HMAC_SECRET, 'page', 0);
	http.post(
		`${BASE_URL}/wp-json/statnive/v1/hit`,
		JSON.stringify({
			resource_type: 'page',
			resource_id: 0, // Checkout page.
			referrer: '',
			screen_width: profile.viewport_w,
			screen_height: profile.viewport_h,
			language: profile.locale,
			timezone: profile.timezone,
			signature: checkoutSig,
			page_query: '',
		}),
		{ headers: { 'Content-Type': 'text/plain', 'User-Agent': profile.user_agent } }
	);
	hitsSent.add(1);

	// Record ground truth for shopping flow.
	if (setupData.headers && Object.keys(setupData.headers).length > 0) {
		recordHit({
			profile_id: profile.id,
			resource_type: 'page',
			resource_id: product.product_id,
			referrer_url: 'https://www.google.com/search?q=' + product.name,
			expected_channel: 'Organic Search',
			device_type: profile.device_type,
			is_bot: false,
			is_logged_in: profile.is_logged_in,
			user_agent: profile.user_agent,
		}, setupData.headers);
	}

	sleep(Math.random() * 3 + 1);
}

// ---------------------------------------------------------------------------
// Verify scenario — check data after traffic
// ---------------------------------------------------------------------------
export function verify(setupData) {
	if (!setupData.headers || Object.keys(setupData.headers).length === 0) {
		console.warn('No auth headers — skipping verification.');
		return;
	}

	const today = new Date().toISOString().slice(0, 10);
	const headers = setupData.headers;

	// Wait for data to settle.
	sleep(3);

	// Trigger WP-Cron aggregation.
	http.get(`${BASE_URL}/wp-cron.php?doing_wp_cron=1`);
	sleep(2);

	// Check summary.
	const summaryRes = http.get(
		`${BASE_URL}/wp-json/statnive/v1/summary?from=${today}&to=${today}`,
		{ headers }
	);
	apiLatency.add(summaryRes.timings.duration);

	if (summaryRes.status === 200) {
		const summary = JSON.parse(summaryRes.body);
		const totals = summary.totals || {};

		check(summary, {
			'summary has views > 0': () => totals.views > 0,
			'summary has sessions > 0': () => totals.sessions > 0,
			'summary has visitors > 0': () => totals.visitors > 0,
			'visitors ≤ sessions': () => totals.visitors <= totals.sessions,
			'sessions ≤ views': () => totals.sessions <= totals.views,
		});

		console.log(
			`Verification: ${totals.views} views, ${totals.sessions} sessions, ${totals.visitors} visitors`
		);
	}

	// Check ground truth vs Statnive.
	const gtRes = http.get(
		`${BASE_URL}/wp-json/ground-truth/v1/summary?from=${today}&to=${today}&test_run_id=${setupData.testRunId}`,
		{ headers }
	);

	if (gtRes.status === 200) {
		const gt = JSON.parse(gtRes.body);
		console.log(`Ground truth: ${gt.total_hits} hits, ${gt.unique_profiles} profiles`);
	}

	console.log('Verification complete.');
}
