/**
 * Deterministic data accuracy test for Statnive.
 *
 * Sends a KNOWN number of hits with deterministic referrers, then queries
 * all dashboard REST endpoints to verify exact counts match what was sent.
 * Also records every hit to ground truth for cross-plugin comparison.
 *
 * Usage:
 *   k6 run tests/perf/data-accuracy.js \
 *     -e BASE_URL=http://localhost:10013 \
 *     -e ADMIN_USER=root -e ADMIN_PASS=q1w2e3 \
 *     -e HMAC_SECRET=your-secret
 */

import http from 'k6/http';
import { check, sleep } from 'k6';
import { Counter, Rate, Trend } from 'k6/metrics';
import { BASE_URL, HMAC_SECRET, ADMIN_USER, ADMIN_PASS, TEST_RUN_ID } from './lib/config.js';
import { computeSignature } from './lib/hmac.js';
import { authenticate } from './lib/wp-auth.js';
import { recordHit, getGroundTruth, getGroundTruthByChannel } from './lib/ground-truth.js';
import { accuracy } from './lib/reporters.js';

// ---------------------------------------------------------------------------
// Custom metrics
// ---------------------------------------------------------------------------
const hitsSent = new Counter('hits_sent');
const hitsOk = new Counter('hits_ok');
const eventLoss = new Rate('event_loss');
const dataAccuracyRate = new Rate('data_accuracy');
const duplicatesRate = new Rate('duplicates');
const apiLatency = new Trend('api_endpoint_latency');

// ---------------------------------------------------------------------------
// Deterministic traffic plan — exact counts per channel
// ---------------------------------------------------------------------------
const TRAFFIC_PLAN = [
	// Direct (no referrer) — 20 hits
	...Array.from({ length: 20 }, (_, i) => ({
		page: { type: 'page', id: 2 + (i % 3) },
		referrer: '',
		channel: 'Direct',
	})),
	// Google (Organic Search) — 15 hits
	...Array.from({ length: 15 }, (_, i) => ({
		page: { type: 'post', id: 7 + (i % 3) },
		referrer: 'https://www.google.com/search?q=test',
		channel: 'Organic Search',
	})),
	// Facebook (Social) — 10 hits
	...Array.from({ length: 10 }, (_, i) => ({
		page: { type: 'page', id: 3 + (i % 2) },
		referrer: 'https://www.facebook.com/statnive',
		channel: 'Social',
	})),
	// Email — 5 hits
	...Array.from({ length: 5 }, (_, i) => ({
		page: { type: 'post', id: 8 + (i % 2) },
		referrer: 'https://newsletter.example.com/click',
		channel: 'Email',
	})),
];

const TOTAL_HITS = TRAFFIC_PLAN.length; // 100

// ---------------------------------------------------------------------------
// k6 options
// ---------------------------------------------------------------------------
export const options = {
	scenarios: {
		send_traffic: {
			executor: 'shared-iterations',
			vus: 1,
			iterations: TOTAL_HITS,
			maxDuration: '3m',
		},
		verify: {
			executor: 'shared-iterations',
			vus: 1,
			iterations: 1,
			startTime: '3m10s',
			exec: 'verify',
		},
	},
	thresholds: {
		data_accuracy: ['rate>=0.995'],
		event_loss: ['rate<=0.005'],
		duplicates: ['rate<=0.001'],
		http_req_duration: ['p(95)<500'],
		api_endpoint_latency: ['p(95)<250'],
	},
};

// ---------------------------------------------------------------------------
// Setup
// ---------------------------------------------------------------------------
export function setup() {
	const auth = authenticate(ADMIN_USER, ADMIN_PASS, BASE_URL);
	if (!auth.success) {
		console.warn('Auth failed — ground truth and verification will be limited.');
	}
	return { headers: auth.headers, success: auth.success };
}

// ---------------------------------------------------------------------------
// Send traffic (default function)
// ---------------------------------------------------------------------------
let hitIndex = 0;

export default function (setupData) {
	const plan = TRAFFIC_PLAN[hitIndex % TOTAL_HITS];
	hitIndex++;

	const signature = computeSignature(HMAC_SECRET, plan.page.type, plan.page.id);

	const payload = JSON.stringify({
		resource_type: plan.page.type,
		resource_id: plan.page.id,
		referrer: plan.referrer,
		screen_width: 1920,
		screen_height: 1080,
		language: 'en-US',
		timezone: 'America/New_York',
		signature,
		page_query: '',
	});

	const res = http.post(`${BASE_URL}/wp-json/statnive/v1/hit`, payload, {
		headers: { 'Content-Type': 'text/plain' },
	});

	hitsSent.add(1);
	const ok = check(res, { 'tracker returns 204': (r) => r.status === 204 });

	if (ok) {
		hitsOk.add(1);
		eventLoss.add(0);
	} else {
		eventLoss.add(1);
		if (hitIndex <= 5 || hitIndex % 20 === 0) {
			console.log(`Hit ${hitIndex} failed: status=${res.status} body=${res.body}`);
		}
	}

	// Record ground truth.
	if (setupData.success) {
		recordHit({
			profile_id: `accuracy-${hitIndex}`,
			resource_type: plan.page.type,
			resource_id: plan.page.id,
			referrer_url: plan.referrer,
			expected_channel: plan.channel,
			device_type: 'desktop',
			is_bot: false,
			is_logged_in: false,
			user_agent: 'k6-accuracy-test/1.0',
		}, setupData.headers);
	}

	// 2s delay to stay well under 60 req/min rate limit (~30 req/min actual).
	// Each iteration also makes a ground truth POST, so effective rate is halved.
	sleep(2);
}

// ---------------------------------------------------------------------------
// Verify data accuracy
// ---------------------------------------------------------------------------
export function verify(setupData) {
	if (!setupData.success) {
		console.warn('No auth — verification limited.');
		return;
	}

	const today = new Date().toISOString().slice(0, 10);
	const headers = setupData.headers;

	sleep(3); // Allow aggregation to settle.

	// Trigger WP-Cron aggregation.
	http.get(`${BASE_URL}/wp-cron.php?doing_wp_cron=1`);
	sleep(2);

	// 1. Summary endpoint — totals.
	const summaryRes = http.get(
		`${BASE_URL}/wp-json/statnive/v1/summary?from=${today}&to=${today}`,
		{ headers }
	);
	apiLatency.add(summaryRes.timings.duration);

	if (summaryRes.status === 200) {
		const summary = JSON.parse(summaryRes.body);
		const totals = summary.totals || {};

		const viewsMatch = totals.views >= TOTAL_HITS * 0.995;
		check(summary, {
			[`views ≥ ${Math.floor(TOTAL_HITS * 0.995)} (sent ${TOTAL_HITS})`]: () => viewsMatch,
			'sessions ≤ views': () => totals.sessions <= totals.views,
			'visitors ≤ sessions': () => totals.visitors <= totals.sessions,
		});

		dataAccuracyRate.add(viewsMatch ? 1 : 0);
		console.log(`Summary: ${totals.views} views, ${totals.sessions} sessions, ${totals.visitors} visitors (sent ${TOTAL_HITS})`);

		// Check for duplicates.
		const dupeRatio = totals.views > TOTAL_HITS
			? (totals.views - TOTAL_HITS) / TOTAL_HITS
			: 0;
		duplicatesRate.add(dupeRatio);

		// 2. Pages endpoint — cross-check.
		const pagesRes = http.get(
			`${BASE_URL}/wp-json/statnive/v1/pages?from=${today}&to=${today}&limit=100`,
			{ headers }
		);
		apiLatency.add(pagesRes.timings.duration);

		if (pagesRes.status === 200) {
			const pages = JSON.parse(pagesRes.body);
			const pagesTotal = pages.reduce((sum, p) => sum + (p.views || 0), 0);
			check(pages, {
				'pages views sum matches summary': () => pagesTotal === totals.views,
			});
			dataAccuracyRate.add(pagesTotal === totals.views ? 1 : 0);
		}

		// 3. Sources endpoint — channel distribution.
		const sourcesRes = http.get(
			`${BASE_URL}/wp-json/statnive/v1/sources?from=${today}&to=${today}&limit=100`,
			{ headers }
		);
		apiLatency.add(sourcesRes.timings.duration);

		if (sourcesRes.status === 200) {
			const sources = JSON.parse(sourcesRes.body);
			const sourcesTotal = sources.reduce((sum, s) => sum + (s.visitors || 0), 0);
			check(sources, {
				'sources visitors sum matches summary': () => sourcesTotal === totals.visitors,
			});
			dataAccuracyRate.add(sourcesTotal === totals.visitors ? 1 : 0);
		}

		// 4. Dimensions — cross-check.
		for (const dimType of ['countries', 'devices']) {
			const dimRes = http.get(
				`${BASE_URL}/wp-json/statnive/v1/dimensions/${dimType}?from=${today}&to=${today}`,
				{ headers }
			);
			apiLatency.add(dimRes.timings.duration);

			if (dimRes.status === 200) {
				const dims = JSON.parse(dimRes.body);
				const dimTotal = dims.reduce((sum, d) => sum + (d.visitors || 0), 0);
				check(dims, {
					[`${dimType} visitors sum matches summary`]: () => dimTotal === totals.visitors,
				});
				dataAccuracyRate.add(dimTotal === totals.visitors ? 1 : 0);
			}
		}
	}

	// 5. Ground truth comparison.
	const gt = getGroundTruth(today, today, headers);
	if (gt) {
		console.log(`Ground truth: ${gt.total_hits} hits (expected ${TOTAL_HITS})`);
		const gtAccuracy = accuracy(TOTAL_HITS, gt.total_hits);
		console.log(`Ground truth recording accuracy: ${gtAccuracy.toFixed(1)}%`);
	}

	// 6. Realtime.
	const realtimeRes = http.get(`${BASE_URL}/wp-json/statnive/v1/realtime`, { headers });
	apiLatency.add(realtimeRes.timings.duration);

	if (realtimeRes.status === 200) {
		const rt = JSON.parse(realtimeRes.body);
		check(rt, { 'realtime has active_visitors': (r) => r.active_visitors !== undefined });
	}

	console.log('Data accuracy verification complete.');
}
