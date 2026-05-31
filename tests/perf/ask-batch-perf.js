/**
 * Ask me! — POST /advisor/answers perf gate.
 *
 * Hits the batched-answer endpoint with the 5 default-pinned question IDs
 * at 100 virtual users for 60 seconds. The p95 must stay under 500ms (per
 * the plan's hardening §performance budget) — k6 thresholds fail the run
 * on regression so the gate is CI-actionable.
 *
 * Usage:
 *   k6 run tests/perf/ask-batch-perf.js \
 *     -e BASE_URL=http://localhost:10013 \
 *     -e ADMIN_USER=root -e ADMIN_PASS=q1w2e3
 */

import http from 'k6/http';
import { check, sleep } from 'k6';
import { Trend } from 'k6/metrics';
import { BASE_URL, ADMIN_USER, ADMIN_PASS, REST_URL } from './lib/config.js';
import { authenticate } from './lib/wp-auth.js';

const batchLatency = new Trend('advisor_batch_latency_ms');

export const options = {
	scenarios: {
		batch_pinned_tab: {
			executor: 'constant-vus',
			vus: 100,
			duration: '60s',
		},
	},
	// Plan §G.14 performance budgets: p95 batched POST under 500ms cold,
	// under 100ms warm. We assert the looser 500ms cap here because k6's
	// VU pool produces both hits.
	thresholds: {
		http_req_duration: ['p(95)<500', 'p(99)<1000'],
		advisor_batch_latency_ms: ['p(95)<500', 'p(99)<1000'],
		'http_req_failed{name:advisor-answers}': ['rate<0.01'],
	},
};

const DEFAULT_PINS = ['q2', 'q41', 'q23', 'q72', 'q81'];

export function setup() {
	const auth = authenticate(ADMIN_USER, ADMIN_PASS, BASE_URL);
	if (!auth.success) {
		throw new Error(
			`k6 setup: WordPress login failed for user "${ADMIN_USER}". ` +
				'Set BASE_URL / ADMIN_USER / ADMIN_PASS env vars.',
		);
	}
	return { headers: auth.headers, cookies: auth.cookies };
}

export default function (data) {
	const url = `${REST_URL}/statnive/v1/advisor/answers`;
	const body = JSON.stringify({
		question_ids: DEFAULT_PINS,
		from: yesterday(7),
		to: today(),
	});

	const res = http.post(url, body, {
		headers: { ...data.headers, 'Content-Type': 'application/json' },
		tags: { name: 'advisor-answers' },
	});

	batchLatency.add(res.timings.duration);

	check(res, {
		'status is 200': (r) => r.status === 200,
		'returns 5 answers': (r) => {
			try {
				const j = JSON.parse(r.body);
				return Array.isArray(j.answers) && j.answers.length === 5;
			} catch (_e) {
				return false;
			}
		},
		'Server-Timing header present': (r) =>
			typeof r.headers['Server-Timing'] === 'string' &&
			r.headers['Server-Timing'].includes('total;dur='),
	});

	sleep(0.5);
}

function today() {
	return new Date().toISOString().slice(0, 10);
}

function yesterday(daysAgo) {
	const d = new Date();
	d.setUTCDate(d.getUTCDate() - daysAgo);
	return d.toISOString().slice(0, 10);
}
